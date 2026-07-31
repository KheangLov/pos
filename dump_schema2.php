<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = ['companies', 'branches', 'categories', 'products', 'product_variants', 'modifiers', 'modifier_groups', 'modifier_factors', 'serial_numbers', 'stock_transactions', 'tables', 'floor_plans', 'users', 'roles', 'permissions'];
$schemas = [];

foreach ($tables as $t) {
    if (Schema::hasTable($t)) {
        $schemas[$t] = Schema::getColumnListing($t);
    }
}

file_put_contents(__DIR__.'/schemas.json', json_encode($schemas, JSON_PRETTY_PRINT));
