<?php

$files = glob(__DIR__ . '/app/Filament/Resources/*/*Resource.php');
$files = array_merge($files, glob(__DIR__ . '/app/Filament/Pages/*.php'));

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'protected static ?string $navigationGroup') !== false) {
        $content = str_replace(
            'protected static ?string $navigationGroup',
            'protected static \UnitEnum|string|null $navigationGroup',
            $content
        );
        file_put_contents($file, $content);
        echo "Fixed $file\n";
    }
}
