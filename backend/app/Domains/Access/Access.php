<?php

namespace App\Domains\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Access
{
    public const PERMISSIONS = ['read', 'pages', 'media', 'appearance', 'menus', 'templates', 'translations', 'seo', 'settings', 'users', 'commerce', 'submissions'];

    public static function isSuper(User $user): bool
    {
        return $user->is_active && DB::table('roles')->where('id', $user->role_id)->where('name', 'Super Admin')->exists();
    }

    public static function permissions(?int $roleId): array
    {
        return DB::table('permission_role')->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_id', $roleId)->pluck('permissions.name')->all();
    }

    public static function requireSuper(): void
    {
        abort_unless(self::isSuper(request()->user()), 403, 'Only a workspace Super Admin can manage accounts and roles.');
    }

    public static function syncPermissions(int $roleId, array $permissions): void
    {
        DB::table('permission_role')->where('role_id', $roleId)->delete();
        foreach (array_unique($permissions) as $permission) {
            DB::table('permissions')->insertOrIgnore(['name' => $permission]);
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('name', $permission)->value('id')]);
        }
    }

    public static function audit(string $action, string $subject, ?int $site = null): void
    {
        DB::table('activity_logs')->insert(['website_id' => $site, 'user_id' => request()->user()?->id, 'action' => $action, 'subject' => $subject, 'created_at' => now(), 'updated_at' => now()]);
    }
}
