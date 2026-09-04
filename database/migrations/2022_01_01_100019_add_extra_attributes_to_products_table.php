<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured, query-friendly extra attribute storage for Product, mirroring
     * the existing `extra_attributes` json column already present on
     * `brands` and `product_interfaces`.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'extra_attributes')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->json('extra_attributes')->nullable()->after('searchable_title');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'extra_attributes')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('extra_attributes');
        });
    }
};
