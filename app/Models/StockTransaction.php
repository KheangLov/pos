<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['branch_id', 'product_id', 'product_variant_id', 'quantity', 'type', 'reference_type', 'reference_id', 'notes'])]
class StockTransaction extends Model
{
    public const LOW_STOCK_THRESHOLD = 10;

    /**
     * Reorder threshold — now config-driven (config/pos.php, env
     * LOW_STOCK_THRESHOLD). Kept as a method so callers don't touch the
     * legacy const directly.
     */
    public static function lowStockThreshold(): int
    {
        return (int) config('pos.low_stock_threshold', self::LOW_STOCK_THRESHOLD);
    }

    public static function onHand(int $branchId, int $productId): int
    {
        return (int) static::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
