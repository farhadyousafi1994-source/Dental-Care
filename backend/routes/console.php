<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::call(function () {
    \App\Domains\Content\Page::where('status', 'scheduled')->where('publish_at', '<=', now())->select('id')->chunkById(100, function ($pages) {
        foreach ($pages as $candidate) \Illuminate\Support\Facades\DB::transaction(function () use ($candidate) {
            $page = \App\Domains\Content\Page::lockForUpdate()->find($candidate->id);
            if (! $page || $page->status !== 'scheduled' || ! $page->publish_at || $page->publish_at->isFuture()) return;
            $page->update(['status' => 'published', 'publish_at' => null, 'version' => $page->version + 1]);
            \Illuminate\Support\Facades\DB::table('activity_logs')->insert(['website_id' => $page->website_id, 'action' => 'published scheduled page', 'subject' => $page->title, 'created_at' => now(), 'updated_at' => now()]);
        });
    });
})->name('cms:publish-scheduled-pages')->everyMinute()->withoutOverlapping();

\Illuminate\Support\Facades\Artisan::command('cms:doctor', function () {
    $failed = false;
    $report = function (string $label, bool $ok, string $hint = '') use (&$failed) {
        $this->line(($ok ? '[OK] ' : '[FAIL] ').$label.($ok || ! $hint ? '' : ': '.$hint));
        $failed = $failed || ! $ok;
    };
    $report('MySQL selected', config('database.default') === 'mysql', 'Set DB_CONNECTION=mysql and run php artisan config:clear.');
    $report('Application key configured', filled(config('app.key')), 'Run php artisan key:generate once on a new installation.');
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $report('Database reachable', true);
        $report('CMS migrations applied', \Illuminate\Support\Facades\Schema::hasTable('page_autosaves') && \Illuminate\Support\Facades\Schema::hasTable('permission_role'), 'Run php artisan migrate.');
        if (\Illuminate\Support\Facades\Schema::hasTable('roles') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
            $super = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'Super Admin')->value('id');
            $report('Active Super Admin exists', $super && \App\Models\User::where('role_id', $super)->where('is_active', true)->exists(), 'Configure CMS_ADMIN_EMAIL / CMS_ADMIN_PASSWORD and run php artisan db:seed once.');
        }
    } catch (\Throwable $e) {
        $report('Database check', false, 'Connection/schema check failed. Verify .env and phpMyAdmin; no credentials are printed.');
    }
    $report('Public storage linked', file_exists(public_path('storage')), 'Run php artisan storage:link.');
    $report('Storage writable', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')));
    if (app()->isProduction()) {
        $report('Debug disabled', ! config('app.debug'));
        $report('Secure session cookie enabled', (bool) config('session.secure'));
        $report('HTTPS URL configured', str_starts_with(config('app.url'), 'https://'));
    }
    $this->comment('This is an installation check, not a security audit. Run the MySQL feature tests against website_cms_test before deployment.');
    return $failed ? 1 : 0;
})->purpose('Check CMS installation without exposing credentials or changing data');
