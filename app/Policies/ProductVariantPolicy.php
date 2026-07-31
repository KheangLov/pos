<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_product_variant');
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return $user->can('view_product_variant');
    }

    public function create(User $user): bool
    {
        return $user->can('create_product_variant');
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        return $user->can('update_product_variant');
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        return $user->can('delete_product_variant');
    }

    public function restore(User $user, ProductVariant $productVariant): bool
    {
        return $user->can('restore_product_variant');
    }

    public function forceDelete(User $user, ProductVariant $productVariant): bool
    {
        return $user->can('force_delete_product_variant');
    }
}