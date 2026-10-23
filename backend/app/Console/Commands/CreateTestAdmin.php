<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class CreateTestAdmin extends Command
{
    protected $signature = 'auth:create-test-admin {email} {password}';

    protected $description = 'Create or update a test admin user for login testing';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?? 'Test Admin';
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->save();

        $this->info("Test admin created/updated: {$email}");
        return 0;
    }
}
