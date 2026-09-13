<?php
namespace Tests\Feature;
use App\Domains\Content\BlockSchema;
use App\Domains\Websites\ProvisionWebsite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class BuilderModulesTest extends TestCase {
 use RefreshDatabase;
 private $site; private $other;
 protected function setUp():void{parent::setUp();$this->seed();$user=User::factory()->create();$user->forceFill(['role_id'=>DB::table('roles')->where('name','Super Admin')->value('id')])->save();$this->actingAs($user);$this->site=app(ProvisionWebsite::class)->create(['name'=>'Builder','domain'=>'builder.example.com'],$user->id);$this->other=app(ProvisionWebsite::class)->create(['name'=>'Other','domain'=>'other.example.com'],$user->id);}
 public function test_nested_menu_is_scoped_to_language_and_location():void{
  $path="/api/websites/{$this->site->id}/menus";
  $payload=['name'=>'Dari footer','location'=>'footer','language'=>'fa','version'=>0,'items'=>[['key'=>'parent','label'=>'Parent','url'=>'home','children'=>[['key'=>'child','label'=>'Child','url'=>'https://example.com','new_tab'=>true,'children'=>[]]]]]];
  $this->postJson($path,$payload)->assertOk()->assertJsonPath('version',1);
  $menus=$this->getJson($path)->assertOk()->json();$footer=collect($menus)->firstWhere('location','footer');$this->assertTrue($footer['items'][0]['children'][0]['new_tab']);
  $this->postJson($path,$payload)->assertConflict();$payload['version']=1;$payload['items'][0]['url']='javascript:alert(1)';$this->postJson($path,$payload)->assertUnprocessable();
 }
 public function test_folder_parent_cannot_cross_tenants_or_form_cycle():void{
  $base="/api/websites/{$this->site->id}/media-folders";
  $parent=$this->postJson($base,['name'=>'Parent'])->assertOk()->json('id');$child=$this->postJson($base,['name'=>'Child','parent_id'=>$parent])->assertOk()->json('id');
  $this->putJson($base.'/'.$parent,['name'=>'Parent','parent_id'=>$child])->assertUnprocessable();
  $this->postJson("/api/websites/{$this->other->id}/media-folders",['name'=>'Wrong','parent_id'=>$parent])->assertUnprocessable();$this->deleteJson($base.'/'.$parent)->assertUnprocessable();
 }
 public function test_image_edit_creates_derivative_without_modifying_original():void{
  if(!extension_loaded('gd'))$this->markTestSkipped('GD required for raster editing.');Storage::fake('public');
  $upload=$this->postJson("/api/websites/{$this->site->id}/media",['files'=>[UploadedFile::fake()->image('photo.png',200,100)]])->assertCreated()->json('0');
  $before=Storage::disk('public')->get($upload['path']);
  $payload=['x'=>25,'y'=>0,'crop_width'=>50,'crop_height'=>100,'width'=>50,'quality'=>80,'format'=>'png','name'=>'Cropped'];
  $out=$this->postJson("/api/websites/{$this->site->id}/media/{$upload['id']}/transform",$payload)->assertCreated()->assertJsonPath('width',50)->assertJsonPath('height',50)->json();
  $this->assertNotEquals($out['id'],$upload['id']);$this->assertSame($before,Storage::disk('public')->get($upload['path']));
  $this->postJson("/api/websites/{$this->other->id}/media/{$upload['id']}/transform",$payload)->assertNotFound();
 }
 public function test_presets_do_not_activate_until_appearance_is_saved():void{
  $before=DB::table('theme_settings')->where('website_id',$this->site->id)->value('values');$tokens=json_decode($before,true);$tokens['primary']='#123456';
  $id=$this->postJson("/api/websites/{$this->site->id}/theme-presets",['name'=>'Saved look','theme'=>'Evergreen','appearance'=>$tokens])->assertOk()->json('id');
  $this->assertSame($before,DB::table('theme_settings')->where('website_id',$this->site->id)->value('values'));
  $this->deleteJson("/api/websites/{$this->other->id}/theme-presets/$id")->assertNotFound();
 }
 public function test_recursive_schema_rejects_duplicate_ids():void{
  $block=['id'=>'same','type'=>'Text','title'=>'Example','text'=>'Saved'];
  $this->expectException(ValidationException::class);BlockSchema::validate([['id'=>'root','type'=>'Columns','children'=>[[$block],[$block]]]]);
 }
}
