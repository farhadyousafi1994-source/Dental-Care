<?php
namespace App\Domains\Websites;
use Illuminate\Database\Eloquent\Model;
class Website extends Model {
 protected $guarded=['id'];
 public function pages(){return $this->hasMany(\App\Domains\Content\Page::class);}
 public function scopeAccessible($query,$user){if(\Illuminate\Support\Facades\DB::table('roles')->where('id',$user->role_id)->value('name')==='Super Admin')return $query;return $query->whereIn('id',\Illuminate\Support\Facades\DB::table('website_user')->where('user_id',$user->id)->select('website_id'));}
}
