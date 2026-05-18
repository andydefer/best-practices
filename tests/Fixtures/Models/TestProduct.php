<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Models;

use AndyDefer\BestPractices\Casts\JsonCast;
use AndyDefer\BestPractices\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

final class TestProduct extends Model
{
    protected $table = 'test_products';

    protected $fillable = [
        'name',
        'price',
        'metadata',
        'is_featured',
        'productable_id',
        'productable_type',
    ];

    protected $casts = [
        'metadata' => JsonCast::class,
        'price' => MoneyCast::class,
        'is_featured' => 'boolean',
    ];

    /**
     * Get the parent productable model (polymorphic relation).
     */
    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get featured products (limited by best_practices_limit).
     */
    protected function featuredProducts(): Attribute
    {
        return Attribute::make(
            get: function (): Collection {
                return $this->productable
                    ->products()
                    ->where('is_featured', true)
                    ->limit(best_practices_limit())
                    ->get();
            },
        );
    }
}
