<?php
namespace App\Http\Controllers\Api;

use App\Domains\Access\Access;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function me(Request $request): array
    {
        $user = $request->user();
        return [...$user->toArray(), 'role' => DB::table('roles')->where('id', $user->role_id)->value('name'), 'is_super_admin' => Access::isSuper($user)];
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:255']);
        if (! Auth::attempt([...$credentials, 'is_active' => true])) {
            return response()->json(['message' => 'The email or password is incorrect, or the account is inactive.'], 422);
        }
        $request->session()->regenerate();
        Access::audit('logged in', 'Account');
        return $this->me($request);
    }

    public function logout(Request $request): array
    {
        Access::audit('logged out', 'Account');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return ['ok' => true];
    }

    public function password(Request $request): array
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()]]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();
        Access::audit('changed password', 'Account');
        return ['ok' => true];
    }
}
