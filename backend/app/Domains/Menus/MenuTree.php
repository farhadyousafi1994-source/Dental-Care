<?php
namespace App\Domains\Menus;
use Illuminate\Support\Facades\DB;
final class MenuTree {
    public static function items(int $menu): array {
        $rows = DB::table('menu_items')->where('menu_id', $menu)->orderBy('position')->get();
        $build = function ($parent, int $depth = 0) use (&$build, $rows): array {
            if ($depth > 3) return [];
            return $rows->filter(fn ($r) => $r->parent_id === $parent)->map(fn ($r) => [
                'key' => $r->key ?: 'item-'.$r->id, 'label' => $r->label, 'url' => $r->url,
                'new_tab' => (bool) $r->new_tab, 'children' => $build($r->id, $depth + 1),
            ])->values()->all();
        };
        return $build(null);
    }
    public static function validate(array $items): void {
        $keys = []; $count = 0;
        $walk = function ($list, int $depth) use (&$walk, &$keys, &$count) {
            abort_if($depth > 3, 422, 'Menus support up to three levels.');
            foreach ($list as $item) {
                abort_if(++$count > 100, 422, 'A menu supports at most 100 links.');
                validator($item, [
                    'key' => 'required|string|max:80', 'label' => 'required|string|max:100',
                    'url' => ['required', 'string', 'max:2048', 'regex:~^(https?://[^\s]+|mailto:[^\s]+|tel:[+0-9 ()-]+|#[a-zA-Z0-9_-]+|/?[a-zA-Z0-9_/-]+)$~'],
                    'new_tab' => 'sometimes|boolean', 'children' => 'sometimes|array|max:100',
                ])->validate();
                abort_if(in_array($item['key'], $keys, true), 422, 'Every menu item must have a unique key.');
                $keys[] = $item['key'];
                if (!empty($item['children'])) $walk($item['children'], $depth + 1);
            }
        };
        $walk($items, 1);
    }
    public static function insert(int $menu, array $items, ?int $parent = null): void {
        foreach ($items as $position => $item) {
            $id = DB::table('menu_items')->insertGetId(['menu_id' => $menu, 'parent_id' => $parent, 'key' => $item['key'], 'label' => $item['label'], 'url' => $item['url'], 'new_tab' => $item['new_tab'] ?? false, 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
            self::insert($menu, $item['children'] ?? [], $id);
        }
    }
}
