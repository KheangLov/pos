<?php

$groups = [
    'Operations' => [
        'app/Filament/Pages/Pos.php',
        'app/Filament/Pages/Kds.php',
    ],
    'Catalog' => [
        'app/Filament/Resources/Categories/CategoryResource.php',
        'app/Filament/Resources/Products/ProductResource.php',
        'app/Filament/Resources/ProductVariants/ProductVariantResource.php',
        'app/Filament/Resources/Modifiers/ModifierResource.php',
        'app/Filament/Resources/ModifierGroups/ModifierGroupResource.php',
        'app/Filament/Resources/ModifierFactors/ModifierFactorResource.php',
        'app/Filament/Resources/SerialNumbers/SerialNumberResource.php',
        'app/Filament/Resources/StockTransactions/StockTransactionResource.php',
    ],
    'Establishment' => [
        'app/Filament/Resources/Companies/CompanyResource.php',
        'app/Filament/Resources/Branches/BranchResource.php',
        'app/Filament/Resources/FloorPlans/FloorPlanResource.php',
        'app/Filament/Resources/Tables/TableResource.php',
    ],
    'Access Control' => [
        'app/Filament/Resources/Users/UserResource.php',
        'app/Filament/Resources/Roles/RoleResource.php',
        'app/Filament/Resources/Permissions/PermissionResource.php',
    ]
];

foreach ($groups as $group => $files) {
    foreach ($files as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if (strpos($content, '$navigationGroup') === false) {
                // Find navigationIcon
                $content = preg_replace(
                    '/(protected static \?string \$navigationIcon = .*?;)/',
                    "$1\n\n    protected static ?string \$navigationGroup = '$group';",
                    $content
                );
                
                // For Pages that might use string|BackedEnum|null
                $content = preg_replace(
                    '/(protected static string\|BackedEnum\|null \$navigationIcon = .*?;)/',
                    "$1\n\n    protected static ?string \$navigationGroup = '$group';",
                    $content
                );

                file_put_contents($path, $content);
                echo "Updated $file\n";
            } else {
                echo "Skipped $file (already has group)\n";
            }
        } else {
            echo "File not found: $file\n";
        }
    }
}
