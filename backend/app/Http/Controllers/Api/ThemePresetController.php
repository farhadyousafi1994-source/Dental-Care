<?php
namespace App\Http\Controllers\Api;
use App\Domains\Access\{WebsiteAccess,Access};
use App\Domains\Appearance\ThemeTokens;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class ThemePresetController extends Controller {
    public function index(int $site){WebsiteAccess::check($site);return DB::table('theme_presets')->where('website_id',$site)->orderBy('name')->get()->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'theme'=>$p->theme,'appearance'=>json_decode($p->tokens,true),'updated_at'=>$p->updated_at]);}
    public function save(Request $r,int $site,?int $preset=null){WebsiteAccess::check($site,'appearance');if($preset)abort_unless(DB::table('theme_presets')->where('website_id',$site)->where('id',$preset)->exists(),404);$data=$r->validate(['name'=>['required','string','max:120',Rule::unique('theme_presets')->where('website_id',$site)->ignore($preset)],'theme'=>'required|exists:themes,name',...ThemeTokens::rules()]);return DB::transaction(function()use($site,$preset,$data){$values=['name'=>$data['name'],'theme'=>$data['theme'],'tokens'=>json_encode($data['appearance']),'updated_at'=>now()];if($preset){DB::table('theme_presets')->where('id',$preset)->update($values);$id=$preset;}else{$id=DB::table('theme_presets')->insertGetId([...$values,'website_id'=>$site,'created_at'=>now()]);}Access::audit('saved theme preset',$data['name'],$site);return ['id'=>$id,...$data];});}
    public function delete(int $site,int $preset){WebsiteAccess::check($site,'appearance');abort_unless(DB::table('theme_presets')->where('website_id',$site)->where('id',$preset)->delete(),404);Access::audit('deleted theme preset','Preset #'.$preset,$site);return ['ok'=>true];}
}
