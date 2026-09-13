<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Hash};
use App\Models\User;
use App\Domains\Websites\ProvisionWebsite;
class DatabaseSeeder extends Seeder {
 public function run():void {
  foreach(['Super Admin'=>['*'],'Administrator'=>['*'],'Editor'=>['read','pages','media','templates'],'Content Manager'=>['read','pages','media','menus','templates','translations'],'Marketing Manager'=>['read','pages','seo','translations']] as $name=>$permissions) { DB::table('roles')->updateOrInsert(['name'=>$name],['is_system'=>true,'created_at'=>now(),'updated_at'=>now()]); \App\Domains\Access\Access::syncPermissions(DB::table('roles')->where('name',$name)->value('id'),$permissions); }
  foreach(\App\Domains\Access\Access::PERMISSIONS as $permission) DB::table('permissions')->insertOrIgnore(['name'=>$permission]);
  foreach([['en','English','English','ltr'],['fa','Dari','دری','rtl'],['ps','Pashto','پښتو','rtl'],['ar','Arabic','العربية','rtl']] as [$code,$name,$native,$dir])DB::table('languages')->updateOrInsert(['code'=>$code],['name'=>$name,'native_name'=>$native,'direction'=>$dir]);
  foreach([['USD','US Dollar','$'],['AFN','Afghan Afghani','؋'],['EUR','Euro','€'],['GBP','British Pound','£'],['AED','UAE Dirham','د.إ'],['SAR','Saudi Riyal','﷼'],['PKR','Pakistani Rupee','Rs']] as [$code,$name,$symbol])DB::table('currencies')->updateOrInsert(['code'=>$code],['name'=>$name,'symbol'=>$symbol,'decimals'=>2]);
  foreach(['Evergreen'=>'#286b54','Editorial'=>'#855039','Studio'=>'#62588b','Scholar'=>'#274e75'] as $name=>$color)DB::table('themes')->updateOrInsert(['name'=>$name],['category'=>$name==='Scholar'?'University':'Business','tokens'=>json_encode(['primary'=>$color,'background'=>'#faf9f6','surface'=>'#ffffff','text'=>'#263d35','heading'=>'#193c30','font'=>'Inter','radius'=>12,'container'=>1200,'mode'=>'light','header'=>'centered','footer'=>'simple']),'created_at'=>now(),'updated_at'=>now()]);
  if(!env('CMS_ADMIN_PASSWORD')){ $this->command->warn('Set CMS_ADMIN_PASSWORD and CMS_ADMIN_EMAIL to seed the initial administrator.');return; }
  $user=User::firstOrCreate(['email'=>env('CMS_ADMIN_EMAIL','admin@example.com')],['name'=>'Alex Morgan','password'=>Hash::make(env('CMS_ADMIN_PASSWORD'))]);$user->role_id=DB::table('roles')->where('name','Super Admin')->value('id');$user->save();
  if(!DB::table('websites')->exists())app(ProvisionWebsite::class)->create(['name'=>'Evergreen Studio','domain'=>'evergreen.example.com','type'=>'Agency','description'=>'Designing a more thoughtful tomorrow.','theme'=>'Evergreen'],$user->id);
 }
}
