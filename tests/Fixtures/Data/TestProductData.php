<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Data;

use AndyDefer\BestPractices\Data\AbstractData;

final class TestProductData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?int $price = null,
        /**
         * @param  array<string, mixed>|null  $metadata  JSON metadata (key-value pairs)
         */
        public readonly ?array $metadata = null,
        public readonly ?bool $isFeatured = null,
        public readonly ?int $productableId = null,
        public readonly ?string $productableType = null,
        public readonly ?string $createdAt = null,
    ) {}
}
