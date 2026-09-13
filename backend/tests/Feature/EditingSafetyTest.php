<?php
namespace Tests\Feature;

use App\Domains\Content\Page;
use App\Domains\Websites\ProvisionWebsite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EditingSafetyTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Page $page;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role_id' => DB::table('roles')->where('name', 'Super Admin')->value('id')])->save();
        $site = app(ProvisionWebsite::class)->create(['name' => 'Editorial', 'domain' => 'editorial.example.com'], $this->admin->id);
        $site->update(['status' => 'published']);
        $this->page = $site->pages()->first();
        $this->page->update(['status' => 'published']);
        $this->base = "/api/websites/$site->id/pages/{$this->page->id}";
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return [...$this->page->toArray(), 'autosave_version' => 0, ...$overrides];
    }

    public function test_autosave_is_private_and_never_mutates_published_content(): void
    {
        $this->putJson($this->base.'/autosave', $this->payload(['title' => 'Private changes']))->assertOk()->assertJsonPath('autosave_version', 1);
        $this->getJson($this->base.'/editor')->assertOk()->assertJsonPath('autosave.snapshot.title', 'Private changes');
        $this->getJson("/api/public/websites/{$this->page->website_id}/home")->assertOk()->assertJsonPath('page.title', 'Home')->assertJsonPath('page.status', 'published');
        $other = User::factory()->create();
        $other->forceFill(['role_id' => $this->admin->role_id])->save();
        $this->actingAs($other)->getJson($this->base.'/editor')->assertOk()->assertJsonPath('autosave', null);
    }

    public function test_stale_page_save_fails_without_overwriting_newer_content(): void
    {
        $this->putJson($this->base, $this->payload(['title' => 'First author']))->assertOk()->assertJsonPath('version', 2);
        $this->putJson($this->base, $this->payload(['title' => 'Stale author']))->assertConflict();
        $this->assertDatabaseHas('pages', ['id' => $this->page->id, 'title' => 'First author', 'version' => 2]);
        $this->assertDatabaseCount('page_revisions', 1);
    }

    public function test_two_tabs_cannot_overwrite_a_newer_working_copy(): void
    {
        $this->putJson($this->base.'/autosave', $this->payload(['title' => 'First tab']))->assertOk();
        $this->putJson($this->base.'/autosave', $this->payload(['title' => 'Second tab']))->assertConflict();
        $this->deleteJson($this->base.'/autosave', ['autosave_version' => 0])->assertConflict();
        $this->getJson($this->base.'/editor')->assertJsonPath('autosave.snapshot.title', 'First tab');
    }

    public function test_autosaving_against_a_changed_page_returns_conflict(): void
    {
        $this->putJson($this->base, $this->payload(['title' => 'Published elsewhere']))->assertOk();
        $this->putJson($this->base.'/autosave', $this->payload())->assertConflict();
        $this->assertDatabaseCount('page_autosaves', 0);
    }

    public function test_publishing_consumes_only_the_matching_own_autosave(): void
    {
        $this->putJson($this->base.'/autosave', $this->payload(['title' => 'Working copy']))->assertOk();
        $this->putJson($this->base, $this->payload(['title' => 'Working copy', 'autosave_version' => 1]))->assertOk();
        $this->assertDatabaseCount('page_autosaves', 0);
    }

    public function test_restore_requires_current_version_and_increments_it(): void
    {
        $this->putJson($this->base, $this->payload(['title' => 'New version']))->assertOk();
        $revision = DB::table('page_revisions')->value('id');
        $this->postJson($this->base."/revisions/$revision/restore", ['version' => 1])->assertConflict();
        $this->postJson($this->base."/revisions/$revision/restore", ['version' => 2])->assertOk()->assertJsonPath('version', 3)->assertJsonPath('status', 'draft')->assertJsonPath('title', 'Home');
    }

    public function test_schedule_rejects_past_time_and_accepts_future_time(): void
    {
        $this->putJson($this->base, $this->payload(['status' => 'scheduled', 'publish_at' => now()->subHour()->toIso8601String()]))->assertUnprocessable();
        $this->putJson($this->base, $this->payload(['status' => 'scheduled', 'publish_at' => now()->addHour()->toIso8601String()]))->assertOk()->assertJsonPath('status', 'scheduled');
        $this->getJson("/api/public/websites/{$this->page->website_id}/home")->assertNotFound();
    }
}
