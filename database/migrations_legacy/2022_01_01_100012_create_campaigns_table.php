<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop marketing campaigns table.
 *
 * `discount_id` and `created_by` are soft keys: commerce (discounts) and the host
 * (users) are separate bounded contexts, so this package never adds hard FKs to
 * their tables. `karnoweb/commerce` owns any FK/columns on `orders`/`order_items`.
 */
return new class extends Migration
{
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
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
