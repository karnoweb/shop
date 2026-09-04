<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_interface_complementary', function (Blueprint $table) {
            $table->unsignedBigInteger('product_interface_id');
            $table->unsignedBigInteger('complementary_id');

            $table->primary(['product_interface_id', 'complementary_id']);

            $table->foreign('product_interface_id')
                ->references('id')
                ->on('product_interfaces')
                ->cascadeOnDelete();

            $table->foreign('complementary_id')
                ->references('id')
                ->on('product_interfaces')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_interface_complementary');
    }
};
