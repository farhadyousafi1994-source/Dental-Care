<?php
namespace App\Http\Controllers\Api;
use App\Domains\Access\{WebsiteAccess,Access};
use App\Domains\Websites\Website;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Mail};
use Illuminate\Support\Str;
class EngagementController extends Controller {
 private function published(int $site):Website{return Website::where('status','published')->findOrFail($site);}
 public function contact(Request $r,int $site){$this->published($site);$d=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|max:254','message'=>'required|string|min:10|max:10000','language'=>'required|in:en,fa,ps,ar','consent'=>'required|accepted','company'=>'nullable|max:0']);DB::table('submissions')->insert([...collect($d)->only(['name','email','message','language'])->all(),'website_id'=>$site,'created_at'=>now(),'updated_at'=>now()]);return response()->json(['message'=>'Message received.'],201);}
 public function subscribe(Request $r,int $site){$website=$this->published($site);abort_if(app()->environment('production')&&in_array(config('mail.default'),['log','array']),503,'Email delivery is not configured.');$d=$r->validate(['email'=>'required|email|max:254','language'=>'required|in:en,fa,ps,ar','consent'=>'required|accepted','company'=>'nullable|max:0']);$email=Str::lower($d['email']);$token=Str::random(64);
  DB::transaction(function()use($site,$email,$d,$token){Website::whereKey($site)->lockForUpdate()->firstOrFail();DB::table('subscribers')->updateOrInsert(['website_id'=>$site,'email'=>$email],['language'=>$d['language'],'token_hash'=>hash('sha256',$token),'token_expires_at'=>now()->addHours(24),'consented_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);});
  // Never expose verification tokens in the public response. Configure SMTP in production.
  $url=rtrim(config('services.frontend_url',config('app.url')),'/').'/site/'.$site.'/home?newsletter='.urlencode($token).'&email='.urlencode($email);
  try{Mail::raw("Confirm your newsletter request for {$website->name}:\n$url\n\nThis link expires in 24 hours. Open it to confirm or unsubscribe. If you did not request this, ignore it.",fn($m)=>$m->to($email)->subject('Confirm newsletter request'));}catch(\Throwable $e){report($e);abort(503,'Email could not be sent. Please try again later.');}
  return response()->json(['message'=>'Check your email to confirm your subscription.'],202);
 }
 public function subscription(Request $r,int $site){$this->published($site);$d=$r->validate(['email'=>'required|email','token'=>'required|string|size:64','action'=>'required|in:confirm,unsubscribe']);$changed=DB::table('subscribers')->where('website_id',$site)->where('email',Str::lower($d['email']))->where('token_hash',hash('sha256',$d['token']))->where('token_expires_at','>',now())->update(['status'=>$d['action']==='confirm'?'subscribed':'unsubscribed','confirmed_at'=>$d['action']==='confirm'?now():null,'token_hash'=>null,'token_expires_at'=>null,'updated_at'=>now()]);abort_unless($changed,422,'This link is invalid or expired. Request a new email.');return ['ok'=>true];}
 public function index(int $site){WebsiteAccess::check($site,'submissions');return ['messages'=>DB::table('submissions')->where('website_id',$site)->orderByDesc('id')->paginate(30,['*'],'messages_page'),'subscribers'=>DB::table('subscribers')->where('website_id',$site)->orderByDesc('id')->paginate(30,['id','email','language','status','consented_at','confirmed_at'],'subscribers_page')];}
 public function update(Request $r,int $site,int $submission){WebsiteAccess::check($site,'submissions');$d=$r->validate(['status'=>'required|in:new,read,archived']);abort_unless(DB::table('submissions')->where('website_id',$site)->where('id',$submission)->exists(),404);DB::table('submissions')->where('website_id',$site)->where('id',$submission)->update([...$d,'updated_at'=>now()]);Access::audit('updated submission','Message #'.$submission,$site);return ['ok'=>true];}
 public function delete(int $site,string $kind,int $entry){WebsiteAccess::check($site,'submissions');abort_unless(in_array($kind,['submissions','subscribers']),404);abort_unless(DB::table($kind)->where('website_id',$site)->where('id',$entry)->delete(),404);Access::audit('deleted '.$kind,'Record #'.$entry,$site);return ['ok'=>true];}
}
