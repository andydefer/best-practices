<?php

// tests/database/migrations/2024_01_01_000002_create_test_products_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('price')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->morphs('productable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_products');
    }
};
