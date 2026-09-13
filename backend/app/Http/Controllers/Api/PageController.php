<?php
namespace App\Http\Controllers\Api;

use App\Domains\Access\{Access, WebsiteAccess};
use App\Domains\Content\Page;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageContentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    private function page(int $site, int $id, bool $lock = false): Page
    {
        $query = Page::where('website_id', $site);
        if ($lock) $query->lockForUpdate();
        return $query->findOrFail($id);
    }

    private function ensureVersion(Page $page, int $version): void
    {
        abort_if($page->version !== $version, 409, 'This page changed in another session. Reload the latest version before saving. Your local changes have not been overwritten.');
    }

    private function snapshot(Page $page): void
    {
        DB::table('page_revisions')->insert(['page_id' => $page->id, 'user_id' => request()->user()->id, 'snapshot' => json_encode($page->toArray()), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function editor(Request $request, int $site, int $page)
    {
        WebsiteAccess::check($site, 'pages');
        $record = $this->page($site, $page);
        $autosave = DB::table('page_autosaves')->where('page_id', $page)->where('user_id', $request->user()->id)->first();
        if ($autosave) $autosave->snapshot = json_decode($autosave->snapshot, true);
        return ['page' => $record, 'autosave' => $autosave];
    }

    public function save(PageContentRequest $request, int $site, ?int $page = null)
    {
        WebsiteAccess::check($site, 'pages');
        return DB::transaction(function () use ($request, $site, $page) {
            $data = $request->content();
            if ($data['status'] !== 'scheduled') $data['publish_at'] = null;
            if ($page) {
                $record = $this->page($site, $page, true);
                $this->ensureVersion($record, $request->integer('version'));
                $this->snapshot($record);
                $record->update([...$data, 'version' => $record->version + 1]);
                // Do not delete newer working copies from other tabs/users. Keep them recoverable as stale drafts.
                DB::table('page_autosaves')->where('page_id', $page)->where('user_id', $request->user()->id)->where('revision', $request->integer('autosave_version'))->delete();
            } else {
                $record = Page::create([...$data, 'version' => 1, 'website_id' => $site, 'author_id' => $request->user()->id]);
            }
            Access::audit($record->status === 'published' ? 'published a page' : 'saved a page', $record->title, $site);
            return $record;
        });
    }

    public function autosave(PageContentRequest $request, int $site, int $page)
    {
        WebsiteAccess::check($site, 'pages');
        return DB::transaction(function () use ($request, $site, $page) {
            $record = $this->page($site, $page, true);
            $this->ensureVersion($record, $request->integer('version'));
            $query = DB::table('page_autosaves')->where('page_id', $page)->where('user_id', $request->user()->id);
            $old = $query->first();
            abort_if(($old?->revision ?? 0) !== $request->integer('autosave_version'), 409, 'Your working copy changed in another tab. Reload before saving.');
            $revision = ($old?->revision ?? 0) + 1;
            $values = ['snapshot' => json_encode($request->content()), 'base_version' => $record->version, 'revision' => $revision, 'updated_at' => now()];
            if ($old) $query->update($values);
            else DB::table('page_autosaves')->insert([...$values, 'page_id' => $page, 'user_id' => $request->user()->id, 'created_at' => now()]);
            return ['autosave_version' => $revision, 'base_version' => $record->version, 'saved_at' => now()->toIso8601String()];
        });
    }

    public function discard(Request $request, int $site, int $page)
    {
        WebsiteAccess::check($site, 'pages');
        $request->validate(['autosave_version' => 'required|integer|min:0']);
        return DB::transaction(function () use ($request, $site, $page) {
            $this->page($site, $page, true);
            $query = DB::table('page_autosaves')->where('page_id', $page)->where('user_id', $request->user()->id);
            $old = $query->first();
            abort_if($old && $old->revision !== $request->integer('autosave_version'), 409, 'This working copy changed in another tab. Reload first.');
            $query->delete();
            return ['ok' => true];
        });
    }

    public function restore(Request $request, int $site, int $page, int $revision)
    {
        WebsiteAccess::check($site, 'pages');
        $request->validate(['version' => 'required|integer|min:1']);
        return DB::transaction(function () use ($request, $site, $page, $revision) {
            $record = $this->page($site, $page, true);
            $this->ensureVersion($record, $request->integer('version'));
            $entry = DB::table('page_revisions')->where('page_id', $page)->where('id', $revision)->first();
            abort_unless($entry, 404);
            $this->snapshot($record);
            $snapshot = json_decode($entry->snapshot, true);
            $record->update(['title' => $snapshot['title'], 'blocks' => $snapshot['blocks'], 'seo' => $snapshot['seo'], 'status' => 'draft', 'publish_at' => null, 'version' => $record->version + 1]);
            Access::audit('restored a revision as draft', $record->title, $site);
            return $record;
        });
    }
}
