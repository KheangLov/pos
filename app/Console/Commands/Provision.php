<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * First-run setup for a production install.
 *
 * Release builds ship without the demo seeders (they'd put fictional coffee
 * shops in a real customer's database), so this is how a fresh deployment gets
 * its roles, first company/branch and first administrator.
 */
class Provision extends Command
{
    protected $signature = 'app:provision
        {--company= : Company name}
        {--branch=Main : First branch name}
        {--name= : Administrator full name}
        {--email= : Administrator email}
        {--password= : Administrator password (prompted when omitted)}';

    protected $description = 'Set up roles, the first company/branch and an administrator account';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->warn('This system already has users — provisioning is only for a fresh install.');
            $this->line('Add further users from the admin panel instead.');

            return self::FAILURE;
        }

        $company = (string) ($this->option('company') ?: $this->ask('Company name'));
        $branchName = (string) ($this->option('branch') ?: 'Main');
        $name = (string) ($this->option('name') ?: $this->ask('Administrator full name'));
        $email = (string) ($this->option('email') ?: $this->ask('Administrator email'));
        $password = (string) ($this->option('password') ?: $this->secret('Administrator password'));

        foreach (['Company name' => $company, 'Administrator name' => $name, 'Email' => $email, 'Password' => $password] as $label => $value) {
            if (trim($value) === '') {
                $this->components->error("{$label} is required.");

                return self::FAILURE;
            }
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('That email address is not valid.');

            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->components->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $this->components->task('Creating roles and permissions', function (): void {
            $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        });

        $user = DB::transaction(function () use ($company, $branchName, $name, $email, $password): User {
            $companyModel = Company::create([
                'name' => trim($company),
                'email' => $email,
                'is_active' => true,
            ]);

            $branch = Branch::create([
                'company_id' => $companyModel->id,
                'name' => trim($branchName),
                'is_active' => true,
            ]);

            $admin = User::create([
                'name' => trim($name),
                'email' => trim($email),
                'password' => $password,
                'company_id' => $companyModel->id,
                'branch_id' => $branch->id,
            ]);

            $admin->assignRole(Role::findByName('Admin'));

            return $admin;
        });

        $this->newLine();
        $this->components->info('Provisioning complete.');
        $this->components->twoColumnDetail('Company', $company);
        $this->components->twoColumnDetail('Branch', $branchName);
        $this->components->twoColumnDetail('Administrator', $user->email);
        $this->newLine();
        $this->line('Sign in at <options=bold>/admin</> and add your products, tables and staff from there.');

        return self::SUCCESS;
    }
}
