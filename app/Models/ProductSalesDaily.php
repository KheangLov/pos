<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view over the product_sales_daily materialized view (see
 * database/migrations/*_create_reporting_materialized_views.php). Exists
 * only so Reports.php can hand Filament's Table::query() an Eloquent
 * Builder — Filament's table component requires one specifically and
 * won't accept a raw query builder, even for a view that's never written
 * to through the model.
 */
class ProductSalesDaily extends Model
{
    protected $table = 'product_sales_daily';

    public $timestamps = false;

    public $incrementing = false;
}
