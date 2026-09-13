<?php
namespace App\Http\Controllers\Api;

use App\Domains\Access\{Access, WebsiteAccess};
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\{Rule, Rules\Password};

class AccessController extends Controller
{
    private function roleRows()
    {
        return DB::table('roles')->orderBy('id')->get()->map(fn ($role) => [...(array) $role, 'permissions' => Access::permissions($role->id)]);
    }

    public function index(Request $request)
    {
        Access::requireSuper();
        $users = User::query()->select('id', 'name', 'email', 'role_id', 'is_active', 'created_at')->orderBy('name')->get();
        return ['users' => $users, 'roles' => $this->roleRows(), 'permissions' => Access::PERMISSIONS];
    }

    public function saveUser(Request $request, ?int $user = null)
    {
        Access::requireSuper();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($user)],
            'role_id' => 'required|integer|exists:roles,id',
            'is_active' => 'required|boolean',
            'password' => [$user ? 'nullable' : 'required', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()],
        ]);
        return DB::transaction(function () use ($user, $data) {
            // Serialize mutations to the Super Admin roster; two simultaneous suspensions cannot remove all owners.
            $superRole = DB::table('roles')->where('name', 'Super Admin')->lockForUpdate()->first();
            $target = $user ? User::lockForUpdate()->findOrFail($user) : new User;
            if ($user && $target->is_active && $target->role_id === $superRole->id && (! $data['is_active'] || (int) $data['role_id'] !== $superRole->id)) {
                abort_if(User::where('role_id', $superRole->id)->where('is_active', true)->count() <= 1, 422, 'Keep at least one active Super Admin.');
            }
            $target->forceFill(['name' => $data['name'], 'email' => strtolower($data['email']), 'role_id' => $data['role_id'], 'is_active' => $data['is_active']]);
            if (! empty($data['password'])) $target->password = Hash::make($data['password']);
            $target->save();
            if (! $target->is_active || ! empty($data['password'])) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
                $target->forceFill(['remember_token' => null])->save();
            }
            Access::audit($user ? 'updated account' : 'created account', $target->email);
            return $target;
        });
    }

    public function saveRole(Request $request, ?int $role = null)
    {
        Access::requireSuper();
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role)], 'permissions' => 'required|array|min:1', 'permissions.*' => ['required', Rule::in(Access::PERMISSIONS)]]);
        return DB::transaction(function () use ($role, $data) {
            if ($role) {
                $record = DB::table('roles')->where('id', $role)->lockForUpdate()->first();
                abort_unless($record, 404);
                abort_if($record->is_system, 422, 'Built-in roles are protected. Create a custom role instead.');
                DB::table('roles')->where('id', $role)->update(['name' => $data['name'], 'updated_at' => now()]);
            } else {
                $role = DB::table('roles')->insertGetId(['name' => $data['name'], 'is_system' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
            Access::syncPermissions($role, array_unique(['read', ...$data['permissions']]));
            Access::audit('saved role', $data['name']);
            return ['id' => $role, ...$data];
        });
    }

    public function deleteRole(int $role)
    {
        Access::requireSuper();
        return DB::transaction(function () use ($role) {
            $record = DB::table('roles')->where('id', $role)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($record->is_system, 422, 'Built-in roles cannot be deleted.');
            abort_if(User::where('role_id', $role)->exists() || DB::table('website_user')->where('role_id', $role)->exists(), 422, 'Reassign all accounts and memberships before deleting this role.');
            DB::table('roles')->where('id', $role)->delete();
            Access::audit('deleted role', $record->name);
            return ['ok' => true];
        });
    }

    public function members(int $site)
    {
        WebsiteAccess::check($site, 'users');
        $members = DB::table('website_user')->join('users', 'users.id', '=', 'website_user.user_id')->where('website_id', $site)->get(['users.id', 'users.name', 'users.email', 'users.is_active', 'website_user.role_id']);
        return ['members' => $members, 'roles' => $this->roleRows()->filter(fn ($r) => $r['name'] !== 'Super Admin')->values()];
    }

    public function saveMember(Request $request, int $site)
    {
        WebsiteAccess::check($site, 'users');
        $data = $request->validate(['email' => 'required|email', 'role_id' => 'required|integer|exists:roles,id']);
        $target = User::where('email', $data['email'])->where('is_active', true)->firstOrFail();
        abort_if(DB::table('roles')->where('id', $data['role_id'])->value('name') === 'Super Admin', 422, 'Super Admin is a workspace role, not a website membership.');
        // Non-super managers cannot grant capabilities they do not possess themselves.
        $actorRole = DB::table('website_user')->where('website_id', $site)->where('user_id', $request->user()->id)->value('role_id');
        $actorPermissions = Access::permissions($actorRole);
        abort_unless(Access::isSuper($request->user()) || in_array('*', $actorPermissions) || ! array_diff(Access::permissions((int) $data['role_id']), $actorPermissions), 403, 'Cannot assign permissions beyond your own.');
        DB::table('website_user')->updateOrInsert(['website_id' => $site, 'user_id' => $target->id], ['role_id' => $data['role_id']]);
        Access::audit('updated website membership', $target->email, $site);
        return ['ok' => true];
    }

    public function removeMember(Request $request, int $site, int $user)
    {
        WebsiteAccess::check($site, 'users');
        abort_if($user === $request->user()->id, 422, 'Ask another administrator to remove your own membership.');
        abort_unless(DB::table('website_user')->where('website_id', $site)->where('user_id', $user)->delete(), 404);
        Access::audit('removed website membership', 'User #'.$user, $site);
        return ['ok' => true];
    }
}
