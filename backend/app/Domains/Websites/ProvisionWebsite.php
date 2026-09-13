<?php
namespace App\Domains\Websites;
use Illuminate\Support\Facades\DB;
use App\Domains\Content\Page;
class ProvisionWebsite {
 public function create(array $data, int $userId): Website {
 return DB::transaction(function() use($data,$userId){
  $languages=$data['languages']??['en','fa','ps','ar'];$currencies=$data['currencies']??['USD','AFN','EUR','GBP','AED','SAR','PKR'];unset($data['languages'],$data['currencies']);
  $theme=DB::table('themes')->where('name',$data['theme']??'Evergreen')->first()??DB::table('themes')->first();
  $tokens=json_decode($theme->tokens,true);$site=Website::create([...$data,'theme'=>$theme->name,'theme_id'=>$theme->id,'color'=>$tokens['primary'],'image'=>$data['image']??'/images/studio.jpg','created_by'=>$userId])->refresh();$now=now();
  DB::table('website_user')->insert(['website_id'=>$site->id,'user_id'=>$userId,'role_id'=>DB::table('roles')->where('name','Administrator')->value('id')]);
  foreach(array_unique([...$languages,'en','fa','ps','ar',$site->default_language]) as $language)DB::table('website_languages')->insert(['website_id'=>$site->id,'language_code'=>$language]);
  foreach(array_unique([...$currencies,$site->default_currency]) as $currency)DB::table('website_currencies')->insert(['website_id'=>$site->id,'currency_code'=>$currency,'exchange_rate'=>1,'created_at'=>$now,'updated_at'=>$now]);
  DB::table('theme_settings')->insert(['website_id'=>$site->id,'values'=>$theme->tokens,'created_at'=>$now,'updated_at'=>$now]);
  DB::table('website_settings')->insert(['website_id'=>$site->id,'values'=>json_encode(['tagline'=>$site->description,'contact_email'=>'','seo'=>['index'=>true],'modules'=>['appearance','builder','media','menus','templates','localization','currencies']]),'created_at'=>$now,'updated_at'=>$now]);
  foreach(['Announcement Bar'=>['enabled'=>false,'text'=>'Something new is on its way.'],'Global CTA'=>['enabled'=>false,'title'=>'Let’s create something meaningful.','text'=>'We would love to hear from you.','button'=>'Get in touch','link'=>'mailto:hello@example.com'],'Header'=>['title'=>$site->name],'Footer'=>['text'=>'© '.date('Y').' '.$site->name.'. All rights reserved.'],'Navigation'=>['items'=>[['label'=>'Home','url'=>'/']]]] as $name=>$content)DB::table('global_components')->insert(['website_id'=>$site->id,'name'=>$name,'content'=>json_encode($content),'created_at'=>$now,'updated_at'=>$now]);
  $blocks=[['id'=>'hero-1','type'=>'Hero','title'=>'A new chapter starts here.','text'=>$site->description?:'Thoughtfully made. Built for what comes next.','button'=>'Discover more','image'=>$site->image],['id'=>'text-1','type'=>'Text','title'=>'Welcome to '.$site->name,'text'=>'This is your space to tell your story. Open the page builder to make it your own.']];
  Page::create(['website_id'=>$site->id,'author_id'=>$userId,'title'=>'Home','slug'=>'home','status'=>'draft','language'=>$site->default_language,'blocks'=>$blocks,'seo'=>['title'=>$site->name,'description'=>$site->description]]);
  DB::table('templates')->insert(['website_id'=>$site->id,'name'=>'Getting started','type'=>'Page','blocks'=>json_encode($blocks),'created_at'=>$now,'updated_at'=>$now]);
  $menu=DB::table('menus')->insertGetId(['website_id'=>$site->id,'name'=>'Main navigation','location'=>'header','language'=>'en','created_at'=>$now,'updated_at'=>$now]);
  DB::table('menu_items')->insert(['menu_id'=>$menu,'label'=>'Home','url'=>'home','position'=>0,'created_at'=>$now,'updated_at'=>$now]);
  DB::table('media_folders')->insert(['website_id'=>$site->id,'name'=>'Brand assets']);
  DB::table('activity_logs')->insert(['website_id'=>$site->id,'user_id'=>$userId,'action'=>'created a website','subject'=>$site->name,'created_at'=>$now,'updated_at'=>$now]);
  \App\Events\WebsiteProvisioned::dispatch($site);
  return $site;
 });}
}
