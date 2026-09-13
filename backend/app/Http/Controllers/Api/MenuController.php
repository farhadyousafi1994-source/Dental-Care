<?php
namespace App\Http\Controllers\Api;
use App\Domains\Access\{WebsiteAccess, Access};
use App\Domains\Menus\MenuTree;
use App\Domains\Websites\Website;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MenuController extends Controller {
    public function index(int $site) {
        WebsiteAccess::check($site);
        return DB::table('menus')->where('website_id', $site)->get()->map(fn ($m) => [...(array) $m, 'items' => MenuTree::items($m->id)]);
    }
    public function save(Request $request, int $site) {
        WebsiteAccess::check($site, 'menus');
        $data = $request->validate(['name' => 'required|string|max:120', 'location' => 'required|in:header,mobile,footer,secondary', 'language' => 'required|exists:languages,code', 'version' => 'required|integer|min:0', 'items' => 'required|array|max:100']);
        abort_unless(DB::table('website_languages')->where('website_id', $site)->where('language_code', $data['language'])->exists(), 422, 'Enable this language on the website first.');
        MenuTree::validate($data['items']);
        return DB::transaction(function () use ($site, $data) {
            Website::whereKey($site)->lockForUpdate()->firstOrFail();
            $old = DB::table('menus')->where('website_id', $site)->where('location', $data['location'])->where('language', $data['language'])->first();
            abort_if((int) ($old?->version ?? 0) !== $data['version'], 409, 'This menu changed in another session. Reload before saving.');
            $version = ($old?->version ?? 0) + 1;
            if ($old) { $id = $old->id; DB::table('menus')->where('id', $id)->update(['name' => $data['name'], 'version' => $version, 'updated_at' => now()]); }
            else $id = DB::table('menus')->insertGetId(['website_id' => $site, 'name' => $data['name'], 'location' => $data['location'], 'language' => $data['language'], 'version' => $version, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('menu_items')->where('menu_id', $id)->delete();
            MenuTree::insert($id, $data['items']);
            Access::audit('saved navigation', $data['name'].' ('.$data['language'].')', $site);
            return ['id' => $id, ...$data, 'version' => $version];
        });
    }
}
