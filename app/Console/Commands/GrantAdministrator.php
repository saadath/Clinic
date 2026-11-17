<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('clinic:grant-admin {email}')]
#[Description('Grant Super Admin to an existing active account from the trusted server console')]
class GrantAdministrator extends Command
{
    public function handle(): int
    {
        $account = User::where('email', mb_strtolower(trim($this->argument('email'))))->where('active', true)->first();
        if (! $account) {
            $this->error('Active account not found. Create it with clinic:create-user first.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true, '--no-interaction' => true]);
        DB::transaction(function () use ($account): void {
            $role = Role::where('name', 'super-admin')->lockForUpdate()->sole();
            $account->roles()->syncWithoutDetaching([$role->id]);
        });
        $this->info('Super Admin granted to '.$account->email.'.');

        return self::SUCCESS;
    }
}
