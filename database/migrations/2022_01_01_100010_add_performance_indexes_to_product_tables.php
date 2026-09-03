<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for Product module.
 *
 * این migration شامل index های بهینه‌سازی برای query های پرکاربرد است.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Product Interfaces indexes
        Schema::table('product_interfaces', function (Blueprint $table) {
            // برای query: WHERE category_id = ? AND published = 1
            $table->index(['category_id', 'published'], 'idx_pi_category_published');

            // برای query: WHERE type = ?
            $table->index('type', 'idx_pi_type');
        });

        // Products indexes
        Schema::table('products', function (Blueprint $table) {
            // برای query: WHERE product_interface_id = ? AND published = 1
            $table->index(['product_interface_id', 'published'], 'idx_p_interface_published');

            // برای query: WHERE published = 1 AND stock > 0
            $table->index(['published', 'stock'], 'idx_p_published_stock');

            // برای price range queries
            $table->index('retail_price', 'idx_p_retail_price');
        });

        // Pivot table covering index for faster attribute lookups
        Schema::table('product_interface_attribute_values', function (Blueprint $table) {
            // Covering index برای: WHERE product_interface_id = ?
            // با SELECT attribute_id, attribute_value_id
            $table->index(
                ['product_interface_id', 'attribute_id', 'attribute_value_id'],
                'idx_piav_covering'
            );
        });

        // Product attribute values covering index
        Schema::table('product_attribute_values', function (Blueprint $table) {
            // Covering index برای: WHERE product_id = ?
            $table->index(
                ['product_id', 'attribute_id', 'attribute_value_id'],
                'idx_pav_covering'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_interfaces', function (Blueprint $table) {
            $table->dropIndex('idx_pi_category_published');
            $table->dropIndex('idx_pi_type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_p_interface_published');
            $table->dropIndex('idx_p_published_stock');
        });

        // idx_p_retail_price targets the legacy retail_price column, which a later
        // migration (modify_products_table_pricing) may have already dropped/replaced
        // with base_price without recreating this index on rollback.
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_p_retail_price');
            });
        } catch (Throwable) {
            // Index may already be gone if the pricing migration rolled back first.
        }

        Schema::table('product_interface_attribute_values', function (Blueprint $table) {
            $table->dropIndex('idx_piav_covering');
        });

        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropIndex('idx_pav_covering');
        });
    }
};
