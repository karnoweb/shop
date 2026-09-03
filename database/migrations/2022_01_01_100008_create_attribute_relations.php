<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // This table links attributes to attribute groups. For example, an attribute 'Screen Size' could be linked to a group 'Mobile Phone'.
        Schema::create('attribute_attribute_group', function (Blueprint $table) {
            $table->foreignId('attribute_group_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->index()->constrained()->cascadeOnDelete();

            $table->unique(['attribute_group_id', 'attribute_id'], 'attribute_attribute_group_unique');
        });

        // This table links product interfaces to attributes. For example, a product interface 'Mobile Phone' could have attributes like 'Screen Size' With some condition like codding.
        Schema::create('product_interface_attributes', function (Blueprint $table) {
            $table->foreignId('product_interface_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->index()->constrained()->cascadeOnDelete();
            $table->boolean('codding')->default(false);

            // Ensuring uniqueness to prevent duplicate entries for the same product, attribute combination
            $table->unique(['product_interface_id', 'attribute_id'], 'product_interface_attribute_unique');
        });

        // This table links product interfaces to attribute values. For example, a product interface 'Mobile Phone' could have attribute values like '5.5 inch'.
        Schema::create('product_interface_attribute_values', function (Blueprint $table) {
            $table->foreignId('product_interface_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->index()->constrained()->cascadeOnDelete();

            // Ensuring uniqueness to prevent duplicate entries for the same product, attribute_value combination
            $table->unique(['product_interface_id', 'attribute_value_id', 'attribute_id'], 'product_interface_attribute_value_unique');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->foreignId('product_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->index()->constrained()->cascadeOnDelete();

            // Ensuring uniqueness to prevent duplicate entries for the same product, attribute_value combination
            $table->unique(['product_id', 'attribute_value_id', 'attribute_id'], 'product_attribute_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_interface_attribute_values');
        Schema::dropIfExists('product_interface_attributes');
        Schema::dropIfExists('attribute_attribute_group');
        Schema::dropIfExists('attribute_groups');
    }
};
