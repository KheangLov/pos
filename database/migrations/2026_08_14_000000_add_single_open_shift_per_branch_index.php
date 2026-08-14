<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P1-1: DB-level guarantee of at most one open shift per branch.
 *
 * The app's check-then-create in Pos::openShift() is racy (two terminals,
 * double-click) — this partial unique index is the backstop: a second
 * concurrent open on the same branch violates the constraint instead of
 * forking the cash-drawer history. Supported by Postgres and SQLite
 * (the test DB); MySQL has no partial indexes, but the app targets pgsql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement('CREATE UNIQUE INDEX shifts_single_open_per_branch ON shifts (branch_id) WHERE status = \'open\'');
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS shifts_single_open_per_branch');
    }
};
