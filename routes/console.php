<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tapcloudi:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No existe un usuario con el email {$email}.");

        return 1;
    }

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('superadmin', 'web');
    Role::findOrCreate('client', 'web');

    $user->assignRole('admin');

    $this->info("El usuario {$email} ahora tiene rol admin.");

    return 0;
})->purpose('Assign the admin role to an existing TapCloudi user');

Artisan::command('tapcloudi:make-superadmin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No existe un usuario con el email {$email}.");

        return 1;
    }

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('superadmin', 'web');
    Role::findOrCreate('client', 'web');

    $user->assignRole('superadmin');
    $user->assignRole('admin');

    $this->info("El usuario {$email} ahora tiene rol superadmin.");

    return 0;
})->purpose('Assign the superadmin role to an existing TapCloudi user');
