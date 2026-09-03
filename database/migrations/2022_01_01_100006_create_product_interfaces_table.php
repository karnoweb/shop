<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_interfaces', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->index()->unique();
            // Soft host category key (categories table owned by host CMS)
            $table->unsignedBigInteger('category_id')->index();
            // translation: title like product name
            $table->string('type')->default('simple');
            $table->unsignedBigInteger('brand_id')->index()->nullable();
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            $table->unsignedInteger('warning_quantity')->nullable();
            $table->unsignedInteger('max_discount_percent')->nullable();
            $table->timestamp('ladder_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('wish_count')->default(0);
            $table->boolean('need_stock_confirm')->index()->default(false);
            $table->boolean('published')->index()->default(false);
            $table->timestamp('published_at')->index()->nullable();
            $table->text('languages')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_interfaces');
    }
};
