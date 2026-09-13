<?php
namespace Tests\Feature;

use App\Domains\Websites\ProvisionWebsite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role_id' => DB::table('roles')->where('name', 'Super Admin')->value('id')])->save();
        $this->actingAs($this->admin);
    }
    private function accountPayload(array $overrides = []): array
    {
        return ['name' => 'Team Member', 'email' => 'member@example.com', 'role_id' => DB::table('roles')->where('name', 'Editor')->value('id'), 'is_active' => true, 'password' => 'TestingOnly123!', 'password_confirmation' => 'TestingOnly123!', ...$overrides];
    }
    public function test_creates_account_with_hash_and_never_returns_password(): void
    {
        $response = $this->postJson('/api/users', $this->accountPayload())->assertCreated()->assertJsonMissingPath('password')->assertJsonMissingPath('remember_token');
        $user = User::findOrFail($response->json('id'));
        $this->assertTrue(Hash::check('TestingOnly123!', $user->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'created account', 'subject' => 'member@example.com']);
    }
    public function test_cannot_suspend_last_active_super_admin(): void
    {
        $this->putJson('/api/users/'.$this->admin->id, $this->accountPayload(['name' => $this->admin->name, 'email' => $this->admin->email, 'role_id' => $this->admin->role_id, 'is_active' => false]))->assertUnprocessable();
        $this->assertTrue($this->admin->fresh()->is_active);
    }
    public function test_regular_editor_cannot_manage_global_users_or_roles(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role_id' => DB::table('roles')->where('name', 'Editor')->value('id')])->save();
        $this->actingAs($user)->getJson('/api/access')->assertForbidden();
        $this->actingAs($user)->postJson('/api/users', $this->accountPayload())->assertForbidden();
        $this->actingAs($user)->postJson('/api/roles', ['name' => 'Escalation', 'permissions' => ['users']])->assertForbidden();
    }
    public function test_suspension_blocks_existing_authenticated_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->getJson('/api/dashboard')->assertUnauthorized();
    }
    public function test_custom_roles_use_normalized_permissions_and_protect_system_roles(): void
    {
        $r = $this->postJson('/api/roles', ['name' => 'Media reviewer', 'permissions' => ['media']])->assertOk();
        $this->assertDatabaseHas('permission_role', ['role_id' => $r->json('id'), 'permission_id' => DB::table('permissions')->where('name', 'media')->value('id')]);
        $this->putJson('/api/roles/'.$this->admin->role_id, ['name' => 'Super Admin', 'permissions' => ['read']])->assertUnprocessable();
        $this->postJson('/api/roles', ['name' => 'Wildcard escalation', 'permissions' => ['*']])->assertUnprocessable();
        $this->deleteJson('/api/roles/'.$this->admin->role_id)->assertUnprocessable();
    }
    public function test_membership_is_scoped_and_cannot_assign_global_super_role(): void
    {
        $site = app(ProvisionWebsite::class)->create(['name' => 'Team', 'domain' => 'team.example.com'], $this->admin->id);
        $user = User::factory()->create();
        $editor = DB::table('roles')->where('name', 'Editor')->value('id');
        $this->postJson("/api/websites/$site->id/members", ['email' => $user->email, 'role_id' => $this->admin->role_id])->assertUnprocessable();
        $this->postJson("/api/websites/$site->id/members", ['email' => $user->email, 'role_id' => $editor])->assertOk();
        $this->actingAs($user)->getJson("/api/websites/$site->id/pages")->assertOk();
        $this->actingAs($user)->getJson("/api/websites/$site->id/members")->assertForbidden();
    }
}
