<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Domains\Websites\ProvisionWebsite;
class CmsTest extends TestCase {
 use RefreshDatabase;
 private function admin():User{$this->seed();$u=User::factory()->create();$u->role_id=DB::table('roles')->where('name','Super Admin')->value('id');$u->save();return $u;}
 public function test_authentication_is_required():void{$this->getJson('/api/dashboard')->assertUnauthorized();}
 public function test_website_provisions_all_core_resources_atomically():void{
  $u=$this->admin();$this->actingAs($u);$r=$this->postJson('/api/websites',['name'=>'Test Website','domain'=>'test.example.com','type'=>'Business','theme'=>'Evergreen','default_language'=>'en','default_currency'=>'USD']);$r->assertCreated()->assertJsonPath('name','Test Website');$id=$r->json('id');
  foreach(['theme_settings','website_settings','pages','templates','media_folders','menus','global_components'] as $table)$this->assertDatabaseHas($table,['website_id'=>$id]);
  $this->assertSame(4,DB::table('website_languages')->where('website_id',$id)->count());$this->assertSame(7,DB::table('website_currencies')->where('website_id',$id)->count());
  $this->postJson('/api/websites',['name'=>'Duplicate','domain'=>'test.example.com','type'=>'Business','theme'=>'Evergreen','default_language'=>'en','default_currency'=>'USD'])->assertUnprocessable();
 }
 public function test_drafts_are_not_public_and_saved_blocks_are_renderable():void{
  $u=$this->admin();$site=app(ProvisionWebsite::class)->create(['name'=>'Test','domain'=>'test.example.com'],$u->id);$page=$site->pages()->first();
  $this->getJson("/api/public/websites/$site->id/home")->assertNotFound();
  $this->actingAs($u)->putJson("/api/websites/$site->id/pages/$page->id",['version'=>$page->version,'title'=>'Hello','slug'=>'home','language'=>'en','status'=>'published','blocks'=>[['id'=>'one','type'=>'Text','title'=>'Persisted heading','text'=>'Persisted content']],'seo'=>['title'=>'Hello world']])->assertOk();
  $site->update(['status'=>'published']);$this->getJson("/api/public/websites/$site->id/home")->assertOk()->assertJsonPath('page.blocks.0.title','Persisted heading');$this->assertDatabaseHas('page_revisions',['page_id'=>$page->id]);
 }
 public function test_other_tenants_cannot_read_or_modify_pages():void{
  $admin=$this->admin();$site=app(ProvisionWebsite::class)->create(['name'=>'Private','domain'=>'private.example.com'],$admin->id);$outsider=User::factory()->create();$outsider->role_id=DB::table('roles')->where('name','Editor')->value('id');$outsider->save();
  $this->actingAs($outsider)->getJson("/api/websites/$site->id/pages")->assertNotFound();$this->actingAs($outsider)->getJson('/api/dashboard')->assertOk()->assertJsonCount(0,'websites');
 }
 public function test_editor_cannot_change_appearance():void{
  $admin=$this->admin();$site=app(ProvisionWebsite::class)->create(['name'=>'Private','domain'=>'private.example.com'],$admin->id);$editor=User::factory()->create();$role=DB::table('roles')->where('name','Editor')->value('id');$editor->role_id=$role;$editor->save();DB::table('website_user')->insert(['website_id'=>$site->id,'user_id'=>$editor->id,'role_id'=>$role]);
  $this->actingAs($editor)->getJson("/api/websites/$site->id/pages")->assertOk();$this->actingAs($editor)->putJson("/api/websites/$site->id/appearance",[])->assertForbidden();
 }
 public function test_page_id_cannot_be_used_across_websites():void{
  $u=$this->admin();$first=app(ProvisionWebsite::class)->create(['name'=>'One','domain'=>'one.example.com'],$u->id);$second=app(ProvisionWebsite::class)->create(['name'=>'Two','domain'=>'two.example.com'],$u->id);$page=$first->pages()->first();$this->actingAs($u)->deleteJson("/api/websites/$second->id/pages/$page->id")->assertNotFound();$this->assertDatabaseHas('pages',['id'=>$page->id]);
 }
 public function test_minimal_provisioning_hydrates_database_defaults_before_creating_relations():void{
  $user=$this->admin();$site=app(ProvisionWebsite::class)->create(['name'=>'Defaults','domain'=>'defaults.example.com'],$user->id);
  $this->assertSame('en',$site->default_language);$this->assertSame('USD',$site->default_currency);$this->assertSame('draft',$site->status);
  $this->assertDatabaseHas('website_languages',['website_id'=>$site->id,'language_code'=>'en']);
  $this->assertDatabaseHas('website_currencies',['website_id'=>$site->id,'currency_code'=>'USD']);
  $this->assertSame('en',$site->pages()->first()->language);
 }
}
