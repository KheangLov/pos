<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class EnsureSeeded extends Command
{
    protected $signature = 'app:ensure-seeded';

    protected $description = 'Seed demo data and the admin account only if the database is empty (safe to run on every startup)';

    public function handle(): int
    {
        if (User::where('email', 'admin@pos.test')->exists()) {
            $this->info('Database already seeded, skipping.');

            return self::SUCCESS;
        }

        $this->info('Empty database detected, seeding demo data...');
        $this->call('db:seed', ['--force' => true]);

        $this->info('Indexing search data...');
        $this->call('scout:import', ['model' => \App\Models\Product::class]);
        $this->call('scout:import', ['model' => \App\Models\Category::class]);

        return self::SUCCESS;
    }
}
