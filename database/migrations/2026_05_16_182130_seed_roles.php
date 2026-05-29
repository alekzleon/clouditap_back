<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('client', 'web');

        User::doesntHave('roles')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $user->assignRole('client');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
