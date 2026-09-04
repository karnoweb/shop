<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Shop\ShopServiceProvider;

/**
 * Squashed catalog schema — single source of truth for `karnoweb/shop`.
 *
 * Replaces the incremental `database/migrations_legacy/*` history (kept only
 * as reference, not loaded by {@see ShopServiceProvider}).
 * Early development, no production data to preserve — this is the final
 * shape of every table in one clean pass.
 *
 * Boundary rules (see SHOP_PACKAGE.md):
 * - Host tables (`users`, `categories`, `user_groups`, ...) are never created
 *   or hard-FK'd here — every cross-boundary reference below is a plain
 *   `unsignedBigInteger` + index (a "soft key"), never `->constrained()`/`->foreign()`.
 * - Other domain packages' tables (`karnoweb/commerce` discounts/orders/...)
 *   are never created or FK'd here either.
 * - Foreign keys are only declared between tables THIS package owns
 *   (brands, product_interfaces, products, attributes, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createBrandsTable();
        $this->createAttributeTables();
        $this->createProductInterfacesTable();
        $this->createProductsTable();
        $this->createCampaignsTable();
        $this->createProductPricesTable();
        $this->createWishlistsTable();
        $this->createCategoryPivotTables();
        $this->createAttributePivotTables();
        $this->createProductInterfaceRelationTables();
    }

    public function down(): void
    {
        // Children before parents.
        Schema::dropIfExists('product_interface_complementary');
        Schema::dropIfExists('product_interface_secondary_categories');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_interface_attribute_values');
        Schema::dropIfExists('product_interface_attributes');
        Schema::dropIfExists('attribute_attribute_group');
        Schema::dropIfExists('category_attribute_group');
        Schema::dropIfExists('user_wishlists');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_interfaces');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_groups');
        Schema::dropIfExists('brands');
    }

    private function createBrandsTable(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();

            // Translation: title, description, body (karnoweb/translation).
            $table->text('languages')->nullable();
            $table->string('slug')->unique()->index();
            $table->unsignedInteger('ordering')->default(1);
            $table->boolean('published')->default(true)->index();
            $table->unsignedBigInteger('view_count')->default(0);

            // Structured, query-friendly extension point (see docs/usage.md).
            $table->json('extra_attributes')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function createAttributeTables(): void
    {
        Schema::create('attribute_groups', function (Blueprint $table): void {
            $table->id();

            // Translation: title, description.
            $table->text('languages')->nullable();
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();

            // Translation: title (e.g. "Color", "Size").
            $table->text('languages')->nullable();
            $table->string('type')->default('text');
            $table->boolean('filterable')->default(true);
            $table->boolean('comparable')->default(true);
            $table->boolean('special')->default(false);
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();

            // Translation: title (e.g. "Red", "42").
            $table->text('languages')->nullable();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });
    }

    private function createProductInterfacesTable(): void
    {
        Schema::create('product_interfaces', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();

            // Soft host key — `categories` is owned by the host CMS.
            $table->unsignedBigInteger('category_id')->index();

            // Variant/configuration shape: simple|codding|digital|service.
            $table->string('type')->default('simple');

            // Generic, inventory-agnostic business classification:
            // physical|service|digital|bundle. Independent from `type` above
            // and never used to gate stock/availability (see ProductKindEnum).
            $table->string('kind')->default('physical')->index();

            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->unsignedInteger('warning_quantity')->nullable();
            $table->unsignedInteger('max_discount_percent')->nullable();
            $table->timestamp('ladder_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('wish_count')->default(0);
            $table->boolean('need_stock_confirm')->default(false)->index();
            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();

            // Translation: title, description, body.
            $table->text('languages')->nullable();

            // Structured, query-friendly extension point (see docs/usage.md).
            $table->json('extra_attributes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['category_id', 'published'], 'idx_pi_category_published');
            $table->index('type', 'idx_pi_type');
        });
    }

    private function createProductsTable(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_interface_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_main')->default(false)->index();
            $table->string('sku')->nullable()->index();

            // Smallest currency unit, integer amount — matches product_prices.price.
            $table->decimal('base_price', 15, 0)->unsigned()->default(0);

            /** @deprecated Legacy catalog stock column. Prefer host inventory (`karnoweb/laravel-inventory`). */
            $table->unsignedBigInteger('stock')->default(0);
            $table->unsignedInteger('minimum_sale')->nullable();
            $table->unsignedInteger('maximum_sale')->nullable();
            $table->decimal('weight')->nullable();
            $table->decimal('height')->nullable();
            $table->decimal('length')->nullable();
            $table->decimal('width')->nullable();
            $table->json('searchable_title')->nullable();

            // Optional default unit-of-measure code (e.g. "kg", "pcs"), purely
            // informational for purchase/sales documents — this package never
            // integrates with `karnoweb/laravel-inventory` UOM tables here.
            $table->string('default_uom_code')->nullable();

            // Structured, query-friendly extension point (see docs/usage.md).
            $table->json('extra_attributes')->nullable();

            $table->boolean('published')->default(false)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_interface_id', 'published'], 'idx_p_interface_published');
            $table->index(['published', 'stock'], 'idx_p_published_stock');
            $table->index('base_price', 'idx_p_base_price');
        });
    }

    private function createCampaignsTable(): void
    {
        // `discount_id` and `created_by` are soft keys: commerce (discounts) and
        // the host (users) are separate bounded contexts, so this package never
        // adds hard FKs to their tables.
        Schema::create('campaigns', function (Blueprint $table): void {
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

    private function createProductPricesTable(): void
    {
        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Soft host key — `user_groups` is owned by the host.
            $table->unsignedBigInteger('user_group_id')->nullable()->index();

            // Portable price tier (e.g. "retail", "wholesale"); independent from user_group_id.
            $table->string('tier')->nullable();

            $table->decimal('price', 15, 0)->unsigned();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'user_group_id']);
            $table->index(['product_id', 'tier']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    private function createWishlistsTable(): void
    {
        Schema::create('user_wishlists', function (Blueprint $table): void {
            $table->id();

            // Soft host key — `users` is owned by the host.
            $table->unsignedBigInteger('user_id')->index();
            $table->morphs('morphable');
            $table->timestamps();
        });
    }

    private function createCategoryPivotTables(): void
    {
        // Soft host key — `categories` is owned by the host; `attribute_groups` is owned here.
        Schema::create('category_attribute_group', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->index();
            $table->foreignId('attribute_group_id')->constrained()->cascadeOnDelete();

            $table->unique(['category_id', 'attribute_group_id'], 'category_attribute_group_unique');
        });
    }

    private function createAttributePivotTables(): void
    {
        // Links attributes to attribute groups (e.g. "Screen Size" -> "Mobile Phone").
        Schema::create('attribute_attribute_group', function (Blueprint $table): void {
            $table->foreignId('attribute_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();

            $table->unique(['attribute_group_id', 'attribute_id'], 'attribute_attribute_group_unique');
        });
    }

    private function createProductInterfaceRelationTables(): void
    {
        // Links product interfaces to attributes (optionally as variant/"codding" axes).
        Schema::create('product_interface_attributes', function (Blueprint $table): void {
            $table->foreignId('product_interface_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->boolean('codding')->default(false);

            $table->unique(['product_interface_id', 'attribute_id'], 'product_interface_attribute_unique');
        });

        // Links product interfaces to shared (non-variant) attribute values.
        Schema::create('product_interface_attribute_values', function (Blueprint $table): void {
            $table->foreignId('product_interface_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();

            $table->unique(
                ['product_interface_id', 'attribute_value_id', 'attribute_id'],
                'product_interface_attribute_value_unique'
            );

            // Covering index for WHERE product_interface_id = ? lookups.
            $table->index(
                ['product_interface_id', 'attribute_id', 'attribute_value_id'],
                'idx_piav_covering'
            );
        });

        // Links product variants/SKUs to their specific attribute values.
        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();

            $table->unique(
                ['product_id', 'attribute_value_id', 'attribute_id'],
                'product_attribute_value_unique'
            );

            // Covering index for WHERE product_id = ? lookups.
            $table->index(
                ['product_id', 'attribute_id', 'attribute_value_id'],
                'idx_pav_covering'
            );
        });

        Schema::create('product_interface_secondary_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_interface_id');

            // Soft host key — `categories` is owned by the host CMS.
            $table->unsignedBigInteger('category_id');

            $table->foreign('product_interface_id', 'pi_sec_cat_pi_fk')
                ->references('id')
                ->on('product_interfaces')
                ->cascadeOnDelete();

            $table->unique(['product_interface_id', 'category_id'], 'pi_secondary_categories_unique');
        });

        Schema::create('product_interface_complementary', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_interface_id');
            $table->unsignedBigInteger('complementary_id');

            $table->primary(['product_interface_id', 'complementary_id']);

            $table->foreign('product_interface_id')
                ->references('id')
                ->on('product_interfaces')
                ->cascadeOnDelete();

            $table->foreign('complementary_id')
                ->references('id')
                ->on('product_interfaces')
                ->cascadeOnDelete();
        });
    }
};
