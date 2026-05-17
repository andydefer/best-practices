<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Models;

use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use Illuminate\Database\Eloquent\Model;

final class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'email_verified_at',
    ];

    protected $casts = [
        'status' => TestBackedStringEnum::class,
        'email_verified_at' => 'datetime',
    ];
}
