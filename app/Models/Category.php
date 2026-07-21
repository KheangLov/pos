<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Laravel\Scout\Searchable;
use JeroenG\Explorer\Application\Explored;

#[Fillable(['company_id', 'name', 'slug', 'description', 'image_url', 'is_active', 'sort_order'])]
class Category extends Model implements Explored
{
    use Searchable;

    public function mappableAs(): array
    {
        return [
            'id' => 'keyword',
            'company_id' => 'keyword',
            'name' => 'text',
            'description' => 'text',
        ];
    }
}
