<?php

// tests/Fixtures/Models/TestProduct.php
declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Models;

use AndyDefer\BestPractices\Casts\JsonCast;
use AndyDefer\BestPractices\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;

final class TestProduct extends Model
{
    protected $table = 'test_products';

    protected $fillable = [
        'name',
        'price',
        'metadata',
    ];

    protected $casts = [
        'metadata' => JsonCast::class,
        'price' => MoneyCast::class,
    ];
}
