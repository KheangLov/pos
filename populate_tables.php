<?php

$schema = json_decode(file_get_contents(__DIR__ . '/schemas.json'), true);

$map = [
    'companies' => 'Companies/Tables/CompaniesTable.php',
    'branches' => 'Branches/Tables/BranchesTable.php',
    'categories' => 'Categories/Tables/CategoriesTable.php',
    'products' => 'Products/Tables/ProductsTable.php',
    'product_variants' => 'ProductVariants/Tables/ProductVariantsTable.php',
    'modifiers' => 'Modifiers/Tables/ModifiersTable.php',
    'modifier_groups' => 'ModifierGroups/Tables/ModifierGroupsTable.php',
    'modifier_factors' => 'ModifierFactors/Tables/ModifierFactorsTable.php',
    'serial_numbers' => 'SerialNumbers/Tables/SerialNumbersTable.php',
    'stock_transactions' => 'StockTransactions/Tables/StockTransactionsTable.php',
    'tables' => 'Tables/Tables/TablesTable.php',
    'floor_plans' => 'FloorPlans/Tables/FloorPlansTable.php',
    'users' => 'Users/Tables/UsersTable.php',
    'roles' => 'Roles/Tables/RolesTable.php',
    'permissions' => 'Permissions/Tables/PermissionsTable.php',
];

$baseDir = __DIR__ . '/app/Filament/Resources/';

function generateColumns($columns) {
    $fields = [];
    foreach ($columns as $col) {
        if (in_array($col, ['password', 'remember_token', 'deleted_at'])) continue;

        if (str_ends_with($col, '_id')) {
            $relation = str_replace('_id', '', $col);
            $fields[] = "\\Filament\\Tables\\Columns\\TextColumn::make('$relation.name')->searchable()->sortable()";
        } elseif (str_starts_with($col, 'is_')) {
            $fields[] = "\\Filament\\Tables\\Columns\\IconColumn::make('$col')->boolean()->sortable()";
        } elseif (in_array($col, ['logo_url', 'image_url'])) {
            $fields[] = "\\Filament\\Tables\\Columns\\ImageColumn::make('$col')";
        } elseif (in_array($col, ['price', 'base_price', 'cost_price', 'additional_price'])) {
            $fields[] = "\\Filament\\Tables\\Columns\\TextColumn::make('$col')->money()->sortable()";
        } elseif (in_array($col, ['created_at', 'updated_at', 'email_verified_at'])) {
            $fields[] = "\\Filament\\Tables\\Columns\\TextColumn::make('$col')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true)";
        } else {
            $fields[] = "\\Filament\\Tables\\Columns\\TextColumn::make('$col')->searchable()->sortable()";
        }
    }
    return implode(",\n                ", $fields);
}

foreach ($map as $table => $file) {
    $path = $baseDir . $file;
    if (file_exists($path) && isset($schema[$table])) {
        $content = file_get_contents($path);
        
        $fieldsStr = generateColumns($schema[$table]);
        
        // Find columns([ ... ])
        $content = preg_replace(
            '/->columns\(\[\s*\/\/.*?\s*\]\)/s',
            "->columns([\n                $fieldsStr\n            ])",
            $content
        );
        // Sometimes it's just ->columns([])
        $content = preg_replace(
            '/->columns\(\[\s*\]\)/s',
            "->columns([\n                $fieldsStr\n            ])",
            $content
        );

        file_put_contents($path, $content);
        echo "Updated $file\n";
    } else {
        echo "Skipped $file (not found or no schema)\n";
    }
}
