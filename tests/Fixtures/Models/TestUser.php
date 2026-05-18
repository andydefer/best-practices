<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Models;

use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'grade',
        'email_verified_at',
        'tags',
    ];

    protected $casts = [
        'status' => TestUserStatus::class,
        'role' => TestUserRole::class,
        'grade' => TestUserGrade::class,
        'email_verified_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Get all products for this user (polymorphic relation).
     */
    public function products(): MorphMany
    {
        return $this->morphMany(TestProduct::class, 'productable');
    }
}
