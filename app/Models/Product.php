<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Laravel\Scout\Searchable;
use JeroenG\Explorer\Application\Explored;

#[Fillable([
    'company_id', 'category_id', 'name', 'slug', 'description', 
    'image_url', 'base_price', 'cost_price', 'sku', 'barcode', 
    'is_serialized', 'warranty_period', 'is_active'
])]
class Product extends Model implements Explored
{
    use Searchable;

    public function mappableAs(): array
    {
        return [
            'id' => 'keyword',
            'company_id' => 'keyword',
            'category_id' => 'keyword',
            'name' => 'text',
            'description' => 'text',
            'sku' => 'keyword',
            'barcode' => 'keyword',
        ];
    }
}
