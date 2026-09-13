<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::table('roles', fn (Blueprint $t) => $t->boolean('is_system')->default(false));
        foreach (DB::table('roles')->get() as $role) {
            DB::table('roles')->where('id', $role->id)->update(['is_system' => in_array($role->name, ['Super Admin', 'Administrator', 'Editor', 'Content Manager', 'Marketing Manager'])]);
            foreach (array_unique((array) json_decode($role->permissions, true)) as $permission) {
                DB::table('permissions')->insertOrIgnore(['name' => $permission]);
                DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => DB::table('permissions')->where('name', $permission)->value('id')]);
            }
        }
        Schema::table('roles', fn (Blueprint $t) => $t->dropColumn('permissions'));
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_active')->default(true)->index());
        Schema::table('pages', fn (Blueprint $t) => $t->unsignedInteger('version')->default(1));
        Schema::create('page_autosaves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('page_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('base_version');
            $t->unsignedInteger('revision')->default(1);
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['page_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_autosaves');
        Schema::table('pages', fn (Blueprint $t) => $t->dropColumn('version'));
        Schema::table('users', function (Blueprint $t) { $t->dropIndex(['is_active']); $t->dropColumn('is_active'); });
        Schema::table('roles', fn (Blueprint $t) => $t->json('permissions')->nullable());
        foreach (DB::table('roles')->get() as $role) {
            $permissions = DB::table('permission_role')->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')->where('role_id', $role->id)->pluck('permissions.name');
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::table('roles', fn (Blueprint $t) => $t->dropColumn('is_system'));
    }
};
