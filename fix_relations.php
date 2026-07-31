<?php
$files = glob(__DIR__ . '/app/Filament/Resources/*/*/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    
    $replacements = [
        "'floor_plan'" => "'floorPlan'",
        "'floor_plan." => "'floorPlan.",
        "'product_variant'" => "'productVariant'",
        "'product_variant." => "'productVariant.",
        "'modifier_group'" => "'modifierGroup'",
        "'modifier_group." => "'modifierGroup."
    ];
    
    $newContent = str_replace(array_keys($replacements), array_values($replacements), $content);
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Fixed $file\n";
    }
}
