<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('clinic:create-user {email} {--name=}')]
#[Description('Create a staff account using a hidden password prompt')]
class CreateStaffUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->secret('Password (at least 12 characters)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make([
            'email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:1024', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->info('Staff account created. Assign roles in the staff panel, or use clinic:grant-admin for initial setup.');

        return self::SUCCESS;
    }
}
