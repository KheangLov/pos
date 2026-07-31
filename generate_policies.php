<?php
require __DIR__ . '/vendor/autoload.php';
$modelsPath = __DIR__ . '/app/Models';
$policiesPath = __DIR__ . '/app/Policies';

if (!is_dir($policiesPath)) {
    mkdir($policiesPath, 0755, true);
}

$files = scandir($modelsPath);
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $modelName = pathinfo($file, PATHINFO_FILENAME);
        $policyName = $modelName . 'Policy';
        $varName = lcfirst($modelName);
        $permPrefix = \Illuminate\Support\Str::snake($modelName);
        
        $policyContent = <<<EOT
<?php

namespace App\Policies;

use App\Models\\$modelName;
use App\Models\User;

class $policyName
{
    public function viewAny(User \$user): bool
    {
        return \$user->can('view_$permPrefix');
    }

    public function view(User \$user, $modelName \$$varName): bool
    {
        return \$user->can('view_$permPrefix');
    }

    public function create(User \$user): bool
    {
        return \$user->can('create_$permPrefix');
    }

    public function update(User \$user, $modelName \$$varName): bool
    {
        return \$user->can('update_$permPrefix');
    }

    public function delete(User \$user, $modelName \$$varName): bool
    {
        return \$user->can('delete_$permPrefix');
    }

    public function restore(User \$user, $modelName \$$varName): bool
    {
        return \$user->can('restore_$permPrefix');
    }

    public function forceDelete(User \$user, $modelName \$$varName): bool
    {
        return \$user->can('force_delete_$permPrefix');
    }
}
EOT;
        file_put_contents("$policiesPath/$policyName.php", $policyContent);
        echo "Created $policyName\n";
    }
}
