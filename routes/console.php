<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('vx:make-admin {email} {password} {--name=Admin}', function (string $email, string $password) {
    $u = User::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'company' => config('verifyxpeed.brand'), 'password' => $password, 'role' => 'admin', 'kyb_status' => 'approved', 'phone' => '9999999999']);
    $u->walletModel();
    $this->info("Admin ready: {$u->email}");
})->purpose('Create or reset a VerifyXpeed admin login');
