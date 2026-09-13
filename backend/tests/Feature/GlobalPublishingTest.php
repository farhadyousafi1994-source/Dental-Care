<?php
namespace Tests\Feature;

use App\Domains\Websites\ProvisionWebsite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalPublishingTest extends TestCase
{
    use RefreshDatabase;
    public function test_global_components_and_translation_keys_reach_public_pages_without_private_config(): void
    {
        $this->seed(); $admin = User::factory()->create();
        $admin->forceFill(['role_id' => DB::table('roles')->where('name', 'Super Admin')->value('id')])->save();
        $site = app(ProvisionWebsite::class)->create(['name' => 'Shared', 'domain' => 'shared.example.com'], $admin->id);
        $site->update(['status' => 'published']); $site->pages()->first()->update(['status' => 'published']);
        $this->actingAs($admin)->postJson("/api/websites/$site->id/components", ['name' => 'Header', 'content' => ['title' => 'Shared brand', 'text' => 'Shared tagline']])->assertOk();
        $this->postJson("/api/websites/$site->id/translations", ['key' => 'components.Header.title', 'language' => 'en', 'value' => 'Localized brand'])->assertOk();
        $response = $this->getJson("/api/public/websites/$site->id/home")->assertOk();
        $this->assertSame('Shared brand', collect($response->json('components'))->firstWhere('name', 'Header')['content']['title']);
        $this->assertSame('Localized brand', $response->json('translations')['components.Header.title']);
        $response->assertJsonMissingPath('website.created_by')->assertJsonMissingPath('website.settings')->assertJsonMissingPath('page.author_id');
        $this->postJson("/api/websites/$site->id/components", ['name' => 'Global CTA', 'content' => ['link' => 'javascript:alert(1)']])->assertUnprocessable();
    }
    public function test_sitemap_excludes_drafts_noindex_pages_and_private_websites(): void
    {
        $this->seed(); $admin = User::factory()->create();
        $site = app(ProvisionWebsite::class)->create(['name' => 'Sitemap', 'domain' => 'map.example.com'], $admin->id);
        $this->get("/api/public/websites/$site->id/sitemap.xml")->assertNotFound();
        $site->update(['status' => 'published']); $page = $site->pages()->first();
        $this->get("/api/public/websites/$site->id/sitemap.xml")->assertOk()->assertDontSee('/home?lang=en', false);
        $page->update(['status' => 'published']);
        $this->get("/api/public/websites/$site->id/sitemap.xml")->assertOk()->assertSee('/home?lang=en', false);
        $page->update(['seo' => ['robots' => 'noindex,follow']]);
        $this->get("/api/public/websites/$site->id/sitemap.xml")->assertOk()->assertDontSee('/home?lang=en', false);
    }
}
