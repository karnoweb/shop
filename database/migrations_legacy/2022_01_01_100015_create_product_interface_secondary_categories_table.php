<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_interface_secondary_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('product_interface_id');
            $table->unsignedBigInteger('category_id');

            $table->foreign('product_interface_id', 'pi_sec_cat_pi_fk')
                ->references('id')
                ->on('product_interfaces')
                ->cascadeOnDelete();

            $table->unique(['product_interface_id', 'category_id'], 'pi_secondary_categories_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_interface_secondary_categories');
    }
};
