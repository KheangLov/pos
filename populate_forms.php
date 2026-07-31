<?php

$schema = json_decode(file_get_contents('schemas.json'), true);

$map = [
    'companies' => 'Companies/Schemas/CompanyForm.php',
    'branches' => 'Branches/Schemas/BranchForm.php',
    'categories' => 'Categories/Schemas/CategoryForm.php',
    'products' => 'Products/Schemas/ProductForm.php',
    'product_variants' => 'ProductVariants/Schemas/ProductVariantForm.php',
    'modifiers' => 'Modifiers/Schemas/ModifierForm.php',
    'modifier_groups' => 'ModifierGroups/Schemas/ModifierGroupForm.php',
    'modifier_factors' => 'ModifierFactors/Schemas/ModifierFactorForm.php',
    'serial_numbers' => 'SerialNumbers/Schemas/SerialNumberForm.php',
    'stock_transactions' => 'StockTransactions/Schemas/StockTransactionForm.php',
    'tables' => 'Tables/Schemas/TableForm.php',
    'floor_plans' => 'FloorPlans/Schemas/FloorPlanForm.php',
    'users' => 'Users/Schemas/UserForm.php',
    'roles' => 'Roles/Schemas/RoleForm.php',
    'permissions' => 'Permissions/Schemas/PermissionForm.php',
];

$baseDir = __DIR__ . '/app/Filament/Resources/';

function generateFields($columns) {
    $fields = [];
    foreach ($columns as $col) {
        if (in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'remember_token'])) continue;

        if (str_ends_with($col, '_id')) {
            $relation = str_replace('_id', '', $col);
            $fields[] = "\\Filament\\Forms\\Components\\Select::make('$col')->relationship('$relation', 'name')->searchable()->preload()->required()";
        } elseif (str_starts_with($col, 'is_')) {
            $fields[] = "\\Filament\\Forms\\Components\\Toggle::make('$col')->required()";
        } elseif (in_array($col, ['description', 'notes'])) {
            $fields[] = "\\Filament\\Forms\\Components\\Textarea::make('$col')->columnSpanFull()";
        } elseif (in_array($col, ['logo_url', 'image_url'])) {
            $fields[] = "\\Filament\\Forms\\Components\\FileUpload::make('$col')->image()";
        } elseif (in_array($col, ['price', 'base_price', 'cost_price', 'additional_price'])) {
            $fields[] = "\\Filament\\Forms\\Components\\TextInput::make('$col')->numeric()->prefix('$')";
        } elseif (in_array($col, ['quantity', 'capacity', 'min_selections', 'max_selections', 'sort_order'])) {
            $fields[] = "\\Filament\\Forms\\Components\\TextInput::make('$col')->numeric()";
        } elseif (in_array($col, ['email'])) {
            $fields[] = "\\Filament\\Forms\\Components\\TextInput::make('$col')->email()->required()";
        } elseif ($col === 'password') {
            $fields[] = "\\Filament\\Forms\\Components\\TextInput::make('$col')->password()->required(fn (string \$operation): bool => \$operation === 'create')->dehydrated(fn (?string \$state) => filled(\$state))";
        } else {
            $fields[] = "\\Filament\\Forms\\Components\\TextInput::make('$col')->required()";
        }
    }
    return implode(",\n                ", $fields);
}

foreach ($map as $table => $file) {
    $path = $baseDir . $file;
    if (file_exists($path) && isset($schema[$table])) {
        $content = file_get_contents($path);
        
        $fieldsStr = generateFields($schema[$table]);
        $fieldsStr = str_replace('”', '")', $fieldsStr);
        
        // Find components([ ... ])
        $content = preg_replace(
            '/->components\(\[\s*\/\/.*?\s*\]\)/s',
            "->components([\n                $fieldsStr\n            ])",
            $content
        );
        // Sometimes it's just ->components([])
        $content = preg_replace(
            '/->components\(\[\s*\]\)/s',
            "->components([\n                $fieldsStr\n            ])",
            $content
        );

        file_put_contents($path, $content);
        echo "Updated $file\n";
    }
}
