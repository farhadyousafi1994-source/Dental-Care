<?php
namespace App\Domains\Access;
use App\Domains\Websites\Website;
use Illuminate\Support\Facades\DB;
class WebsiteAccess {
 public static function check(int $id,string $permission='read'):Website {
  $user=request()->user();$site=Website::accessible($user)->findOrFail($id);
  $roleId=DB::table('website_user')->where('website_id',$id)->where('user_id',$user->id)->value('role_id')??$user->role_id;
  $global=DB::table('roles')->where('id',$user->role_id)->first();$role=DB::table('roles')->where('id',$roleId)->first();
  $permissions=Access::permissions($roleId);
  abort_unless($user->is_active && ($global?->name==='Super Admin'||in_array('*',$permissions)||in_array($permission,$permissions)),403,'You do not have permission to perform this action.');return $site;
 }
}
