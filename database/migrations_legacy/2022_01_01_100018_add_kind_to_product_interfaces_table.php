<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Shop\Enums\ProductKindEnum;

return new class extends Migration
{
    /**
     * Generic, inventory-agnostic business classification
     * (physical|service|digital|bundle) — see {@see ProductKindEnum}.
     * Independent from the existing `type` column (variant/configuration shape).
     */
    public function up(): void
    {
        if (Schema::hasColumn('product_interfaces', 'kind')) {
            return;
        }

        Schema::table('product_interfaces', function (Blueprint $table): void {
            $table->string('kind')->default('physical')->index()->after('type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_interfaces', 'kind')) {
            return;
        }

        Schema::table('product_interfaces', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
