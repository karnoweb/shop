<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_interface_id')->index()->constrained()->cascadeOnDelete();
            $table->boolean('is_main')->default(false)->index();
            $table->string('sku')->index()->nullable();
            $table->decimal('base_price', 15)->nullable();
            $table->unsignedBigInteger('stock')->default(0);
            $table->unsignedInteger('minimum_sale')->nullable();
            $table->unsignedInteger('maximum_sale')->nullable();
            $table->decimal('weight')->nullable();
            $table->decimal('height')->nullable();
            $table->decimal('length')->nullable();
            $table->decimal('width')->nullable();
            $table->json('searchable_title')->nullable();
            $table->boolean('published')->index()->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
