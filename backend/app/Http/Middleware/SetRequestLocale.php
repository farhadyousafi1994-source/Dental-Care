<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SetRequestLocale {
 public function handle(Request $request,Closure $next){$locale=strtolower(substr($request->header('Accept-Language','en'),0,2));app()->setLocale(in_array($locale,['en','fa','ps','ar'])?$locale:'en');return $next($request);}
}
