<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('laundry:install {--demo : Add fictional shop activity; local only} {--email= : Owner email} {--name= : Owner name}', function () {
    if (User::exists()) {
        $this->error('Accounts already exist. Installation will not overwrite them.');

        return 1;
    }
    if ($this->option('demo') && app()->environment('production')) {
        $this->error('Demo data is disabled in production.');

        return 1;
    }
    $email = $this->option('email') ?: $this->ask('Owner email', 'admin@freshfold.test');
    $name = $this->option('name') ?: $this->ask('Owner name', 'Shop Administrator');
    $password = Str::password(20);
    $validator = validator(['email' => $email, 'name' => $name], ['email' => 'required|email|max:255', 'name' => 'required|string|max:150']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    DB::transaction(function () use ($name, $email, $password) {
        User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin', 'active' => true, 'must_change_password' => true]);
        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        if ($this->option('demo')) {
            $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        }
    });
    $this->info('Installation complete. Save this temporary password privately; it is shown only now.');
    $this->line('Email: '.$email);
    $this->line('Temporary password: '.$password);
    $this->line('Sign in and change your password before using the workspace.');
})->purpose('Create the first owner account and starter service catalog without overwriting existing accounts');

Artisan::command('laundry:reset-password {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Account not found.');

        return 1;
    }
    if (! $this->confirm('Reset this account password and revoke its browser sessions?')) {
        return 1;
    }
    $password = Str::password(20);
    $user->update(['password' => $password, 'must_change_password' => true, 'remember_token' => null]);
    DB::table('sessions')->where('user_id', $user->id)->delete();
    AuditLog::record('auth.password_reset_console', (string) $user->id);
    $this->line('Temporary password: '.$password);
})->purpose('Recover a staff account with trusted server-console access');
