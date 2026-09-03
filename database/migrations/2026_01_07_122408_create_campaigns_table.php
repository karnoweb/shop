<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop marketing campaigns table.
 *
 * Note: also attaches campaign_id FKs on commerce orders/order_items for BC with the
 * original host migration filename. Commerce package should own those alters long-term.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('discount_id')->nullable()->index();
            $table->string('campaign_type')->default('order_based')->comment('Campaign type: product_based or order_based');
            $table->json('conditions')->nullable()->comment('Campaign filter conditions');
            $table->string('condition_logic')->default('and')->comment('Logic for conditions: and/or');
            $table->integer('priority')->default(0)->comment('Higher priority campaigns are evaluated first');
            $table->boolean('is_active')->default(true);
            $table->boolean('apply_automatically')->default(true)->comment('Auto-apply to matching orders');
            $table->boolean('exclude_manual_orders')->default(false)->comment('Exclude from manual invoices');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->text('languages')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'expires_at']);
            $table->index('priority');
            $table->index('campaign_type');
        });

        // Optional commerce FKs when host/commerce tables already exist (BC with original migration).
        if (Schema::hasTable('discounts')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->foreign('discount_id')->references('id')->on('discounts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'campaign_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'campaign_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['campaign_id']);
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['campaign_id']);
            });
        }

        Schema::dropIfExists('campaigns');
    }
};
