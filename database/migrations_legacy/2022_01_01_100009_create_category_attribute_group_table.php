<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_group', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->index();
            $table->foreignId('attribute_group_id')->index()->constrained()->cascadeOnDelete();

            $table->unique(['category_id', 'attribute_group_id'], 'category_attribute_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_group');
    }
};
