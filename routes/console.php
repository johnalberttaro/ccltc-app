<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Create an admin account:  php artisan admin:create
Artisan::command('admin:create', function () {
    $name = $this->ask('Admin name');
    $email = $this->ask('Admin email');
    $password = $this->secret('Password (at least 8 characters)');

    if (! $name || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Please enter a name and a valid email.');
        return 1;
    }
    if (strlen((string) $password) < 8) {
        $this->error('Password must be at least 8 characters.');
        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('That email already has an account.');
        return 1;
    }

    // The User model hashes the password automatically
    User::create(['name' => $name, 'email' => $email, 'password' => $password]);

    $this->info("Admin account created for {$email}. Log in at /admin/login");
})->purpose('Create an admin account');