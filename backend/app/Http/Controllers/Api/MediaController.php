<?php
namespace App\Http\Controllers\Api;
use App\Domains\Access\{WebsiteAccess, Access};
use App\Domains\Media\RasterEditor;
use App\Domains\Websites\Website;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class MediaController extends Controller {
    private function media(int $site, int $id) { $m = DB::table('media')->where('website_id',$site)->where('id',$id)->first(); abort_unless($m,404); return $m; }
    public function folders(int $site) { WebsiteAccess::check($site); return DB::table('media_folders')->where('website_id',$site)->orderBy('name')->get(); }
    public function saveFolder(Request $request,int $site,?int $folder=null) {
        WebsiteAccess::check($site,'media');
        $data=$request->validate(['name'=>['required','string','max:100',Rule::unique('media_folders')->where('website_id',$site)->ignore($folder)],'parent_id'=>['nullable','integer',Rule::exists('media_folders','id')->where('website_id',$site)]]);
        return DB::transaction(function() use($site,$folder,$data) {
            Website::whereKey($site)->lockForUpdate()->firstOrFail();
            $old=$folder?DB::table('media_folders')->where('website_id',$site)->where('id',$folder)->first():null; if($folder)abort_unless($old,404);
            $parent=$data['parent_id']??null; $seen=[];
            while($parent){abort_if((int)$parent===$folder||in_array($parent,$seen),422,'A folder cannot be placed inside itself or its descendants.');$seen[]=$parent;abort_if(count($seen)>10,422,'Folder nesting is limited to ten levels.');$parent=DB::table('media_folders')->where('website_id',$site)->where('id',$parent)->value('parent_id');}
            if($old){DB::table('media_folders')->where('id',$folder)->update($data);DB::table('media')->where('website_id',$site)->where('folder_id',$folder)->update(['folder'=>$data['name']]);$id=$folder;}
            else $id=DB::table('media_folders')->insertGetId([...$data,'website_id'=>$site]);
            Access::audit('saved media folder',$data['name'],$site);return DB::table('media_folders')->find($id);
        });
    }
    public function deleteFolder(int $site,int $folder) {
        WebsiteAccess::check($site,'media');
        return DB::transaction(function()use($site,$folder){Website::whereKey($site)->lockForUpdate()->firstOrFail();abort_unless(DB::table('media_folders')->where('website_id',$site)->where('id',$folder)->exists(),404);abort_if(DB::table('media_folders')->where('parent_id',$folder)->exists()||DB::table('media')->where('folder_id',$folder)->exists(),422,'Move all files and subfolders before deleting this folder.');DB::table('media_folders')->where('id',$folder)->delete();Access::audit('deleted media folder','Folder #'.$folder,$site);return ['ok'=>true];});
    }
    public function update(Request $request,int $site,int $media) {
        WebsiteAccess::check($site,'media');$this->media($site,$media);
        $data=$request->validate(['name'=>'required|string|max:190','alt'=>'nullable|string|max:500','caption'=>'nullable|string|max:2000','description'=>'nullable|string|max:5000','folder_id'=>['nullable','integer',Rule::exists('media_folders','id')->where('website_id',$site)]]);
        $data['alt']=$data['alt']??'';$data['folder_id']=$data['folder_id']??null;$data['folder']=$data['folder_id']?DB::table('media_folders')->where('id',$data['folder_id'])->value('name'):'All files';
        DB::table('media')->where('id',$media)->update([...$data,'updated_at'=>now()]);Access::audit('updated media details',$data['name'],$site);return $this->media($site,$media);
    }
    public function transform(Request $request,int $site,int $media,RasterEditor $editor) {
        WebsiteAccess::check($site,'media');$original=$this->media($site,$media);
        $data=$request->validate(['x'=>'required|numeric|min:0|max:99','y'=>'required|numeric|min:0|max:99','crop_width'=>'required|numeric|min:1|max:100','crop_height'=>'required|numeric|min:1|max:100','width'=>'required|integer|min:16|max:4096','quality'=>'required|integer|min:20|max:100','format'=>'required|in:jpeg,png,webp','name'=>'required|string|max:180']);
        abort_if($data['x']+$data['crop_width']>100||$data['y']+$data['crop_height']>100,422,'The crop must stay inside the image.');
        abort_unless($original->path && Storage::disk('public')->exists($original->path),422,'Upload this image to the website before editing it. Remote URLs cannot be processed.');
        $result=$editor->transform(Storage::disk('public')->path($original->path),$data);
        $path='websites/'.$site.'/'.Str::uuid().'.'.$data['format'];
        abort_unless(Storage::disk('public')->put($path,$result['bytes']),500,'Unable to store the edited image.');
        try {$record=DB::transaction(function()use($site,$data,$result,$path,$original){$id=DB::table('media')->insertGetId(['website_id'=>$site,'folder_id'=>$original->folder_id,'folder'=>$original->folder,'name'=>$data['name'].'.'.$data['format'],'path'=>$path,'url'=>Storage::disk('public')->url($path),'mime'=>$result['mime'],'size'=>strlen($result['bytes']),'width'=>$result['width'],'height'=>$result['height'],'alt'=>$original->alt,'caption'=>$original->caption,'created_at'=>now(),'updated_at'=>now()]);Access::audit('created edited image',$data['name'],$site);return DB::table('media')->find($id);});return response()->json($record,201);}catch(\Throwable $e){Storage::disk('public')->delete($path);throw $e;}
    }
}
