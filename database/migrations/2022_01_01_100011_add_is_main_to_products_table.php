<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'is_main')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_main')->default(false)->after('product_interface_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'is_main')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_main']);
            $table->dropColumn('is_main');
        });
    }
};
