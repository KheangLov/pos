<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JeroenG\Explorer\Domain\Syntax\MultiMatch;
use JeroenG\Explorer\Domain\Syntax\Term;

/**
 * Product search backed by Elasticsearch (Scout + Explorer) with a transparent
 * database fallback when the search cluster is unreachable.
 */
class ProductSearch
{
    /**
     * @return Collection<int, Product>
     */
    public function search(int $companyId, string $term, ?int $categoryId = null, int $limit = 60): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return $this->browse($companyId, $categoryId, $limit);
        }

        try {
            $builder = Product::search($term)
                ->filter(new Term('company_id', (string) $companyId))
                ->filter(new Term('is_active', true));

            if ($categoryId !== null) {
                $builder->filter(new Term('category_id', (string) $categoryId));
            }

            // Typo-tolerant match on names + exact hits on SKUs/barcodes (incl. variants)
            $builder->must(new MultiMatch($term, [
                'name^3',
                'variant_names^2',
                'category_name',
                'description',
                'sku^5',
                'barcode^5',
                'variant_skus^4',
                'variant_barcodes^4',
            ], fuzziness: 'AUTO'));

            return $builder->take($limit)->get()->load(['category', 'variants', 'modifierGroups.modifiers.modifierFactor']);
        } catch (\Throwable $e) {
            Log::warning('Elasticsearch unavailable, falling back to database search', [
                'error' => $e->getMessage(),
            ]);

            return $this->databaseSearch($companyId, $term, $categoryId, $limit);
        }
    }

    /**
     * @return Collection<int, Product>
     */
    public function browse(int $companyId, ?int $categoryId = null, int $limit = 200): Collection
    {
        // The full grid is the hottest POS query; cache per company/category and
        // flush via the company tag whenever a product is saved (see Product model).
        return Cache::tags(["products:{$companyId}"])->remember(
            "products.browse.{$companyId}.".($categoryId ?? 'all').".{$limit}",
            now()->addMinutes(10),
            fn () => Product::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
                ->with(['category', 'variants', 'modifierGroups.modifiers.modifierFactor'])
                ->orderBy('name')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @return Collection<int, Product>
     */
    private function databaseSearch(int $companyId, string $term, ?int $categoryId, int $limit): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->where(function ($q) use ($like) {
                $q->where('name', 'ilike', $like)
                    ->orWhere('sku', 'ilike', $like)
                    ->orWhere('barcode', 'ilike', $like)
                    ->orWhereHas('variants', function ($v) use ($like) {
                        $v->where('sku', 'ilike', $like)->orWhere('barcode', 'ilike', $like);
                    });
            })
            ->with(['category', 'variants', 'modifierGroups.modifiers.modifierFactor'])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
