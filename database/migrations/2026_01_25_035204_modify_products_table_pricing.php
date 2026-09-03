<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('products', 'base_price')) {
            $this->dropOrphanRetailPriceIndex();
            $this->dropLegacyPriceColumnsIfPresent();

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_price', 15, 0)->unsigned()->default(0)->after('sku');
        });

        if (Schema::hasColumn('products', 'retail_price') || Schema::hasColumn('products', 'wholesale_price')) {
            DB::statement('
                INSERT INTO product_prices (product_id, user_group_id, price, created_at, updated_at)
                SELECT
                    id,
                    NULL,
                    COALESCE(retail_price, wholesale_price, 0),
                    created_at,
                    updated_at
                FROM products
                WHERE COALESCE(retail_price, wholesale_price, 0) > 0
            ');

            DB::statement('
                UPDATE products
                SET base_price = COALESCE(retail_price, wholesale_price, 0)
                WHERE base_price = 0
            ');
        }

        $this->dropLegacyPriceColumnsIfPresent();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'base_price')) {
            return;
        }

        if (! Schema::hasColumn('products', 'retail_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('retail_price', 15)->nullable()->after('sku');
                $table->decimal('wholesale_price', 15)->nullable()->after('retail_price');
            });
        }

        DB::statement('
            UPDATE products
            SET retail_price = base_price
            WHERE base_price > 0
        ');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('base_price');
        });
    }

    private function dropOrphanRetailPriceIndex(): void
    {
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_p_retail_price');
            });
        } catch (Throwable) {
            // Index may not exist on greenfield or sqlite schemas.
        }
    }

    private function dropLegacyPriceColumnsIfPresent(): void
    {
        if (! Schema::hasColumn('products', 'retail_price') && ! Schema::hasColumn('products', 'wholesale_price')) {
            return;
        }

        if (Schema::hasColumn('products', 'retail_price')) {
            $this->dropOrphanRetailPriceIndex();
        }

        $columns = array_values(array_filter(
            ['retail_price', 'wholesale_price'],
            fn (string $column): bool => Schema::hasColumn('products', $column)
        ));

        if ($columns !== []) {
            Schema::table('products', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
