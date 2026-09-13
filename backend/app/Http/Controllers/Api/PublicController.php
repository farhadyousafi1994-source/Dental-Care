<?php
namespace App\Http\Controllers\Api;

use App\Domains\Content\Page;
use App\Domains\Menus\MenuTree;
use App\Domains\Websites\Website;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function page(Request $request, int $site, string $slug = 'home')
    {
        $website = Website::where('status', 'published')->findOrFail($site);
        $language = $request->query('lang', $website->default_language);
        $page = Page::where('website_id', $site)->where('slug', $slug)->where('language', $language)->where('status', 'published')->firstOrFail();
        $appearance = json_decode(DB::table('theme_settings')->where('website_id', $site)->value('values') ?? '{}', true);
        $menus = DB::table('menus')->where('website_id', $site)->where('language', $language)->get();
        $menuLocations = []; foreach ($menus as $menu) $menuLocations[$menu->location] = MenuTree::items($menu->id);
        // Only explicitly published configuration is exposed; no ownership, private settings or working copies.
        return [
            'website' => [...$website->only(['id', 'name', 'domain', 'description', 'theme', 'default_language', 'default_currency']), 'appearance' => $appearance, 'languages' => DB::table('website_languages')->where('website_id', $site)->pluck('language_code')],
            'page' => $page->only(['id', 'title', 'slug', 'language', 'status', 'blocks', 'seo', 'updated_at']),
            'menus' => $menuLocations['header'] ?? [], 'menu_locations' => $menuLocations,
            'components' => DB::table('global_components')->where('website_id', $site)->get()->map(fn ($c) => ['name' => $c->name, 'content' => json_decode($c->content, true)]),
            'translations' => DB::table('translations')->where('website_id', $site)->where('language', $language)->pluck('value', 'key'),
        ];
    }

    public function sitemap(int $site)
    {
        Website::where('status', 'published')->findOrFail($site);
        $xml = new \XMLWriter();
        $xml->openMemory(); $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset'); $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach (Page::where('website_id', $site)->where('status', 'published')->orderBy('id')->cursor() as $page) {
            if (str_contains($page->seo['robots'] ?? '', 'noindex')) continue;
            $xml->startElement('url');
            $xml->writeElement('loc', rtrim(config('app.url'), '/').'/site/'.$site.'/'.$page->slug.'?lang='.$page->language);
            $xml->writeElement('lastmod', $page->updated_at->toAtomString());
            $xml->endElement();
        }
        $xml->endElement(); $xml->endDocument();
        return response($xml->outputMemory(), 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
