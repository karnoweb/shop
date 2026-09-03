<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->string('tier')->nullable()->after('user_group_id');
            $table->index(['product_id', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'tier']);
            $table->dropColumn('tier');
        });
    }
};
