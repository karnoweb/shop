<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Shop\Contracts\StorefrontContext;
use Karnoweb\Shop\Models\BaseModel;
use Karnoweb\Shop\ShopServiceProvider;
use Karnoweb\Shop\Support\ShopTables;

/**
 * Squashed catalog schema — single source of truth for `karnoweb/shop`.
 *
 * Replaces the incremental `database/migrations_legacy/*` history (kept only
 * as reference, not loaded by {@see ShopServiceProvider}).
 * Early development, no production data to preserve — this is the final
 * shape of every table in one clean pass.
 *
 * Every table name is resolved via {@see ShopTables::name()}, so the
 * configured prefix (`shop.general.prefix`, default "shp_") and any exact
 * `shop.tables.<key>` override apply here exactly as they do to Eloquent
 * models (see {@see BaseModel}). Configure the prefix
 * BEFORE running this migration — changing it afterwards does not rename
 * already-created tables.
 *
 * Boundary rules (see SHOP_PACKAGE.md):
 * - Host tables (`users`, `categories`, `user_groups`, ...) are never created
 *   or hard-FK'd here — every cross-boundary reference below is a plain
 *   `unsignedBigInteger` + index (a "soft key"), never `->constrained()`/`->foreign()`.
 * - Other domain packages' tables (`karnoweb/commerce` discounts/orders/...)
 *   are never created or FK'd here either.
 * - Foreign keys are only declared between tables THIS package owns
 *   (brands, product_interfaces, products, attributes, ...) and always
 *   resolve their target table through {@see ShopTables::name()} too.
 * - No `user_wishlists` table — wishlist/cart/compare/rating session state is
 *   entirely a host concern behind {@see StorefrontContext}.
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
        $this->createCategoryPivotTables();
        $this->createAttributePivotTables();
        $this->createProductInterfaceRelationTables();
    }

    public function down(): void
    {
        // Children before parents.
        Schema::dropIfExists(ShopTables::name('product_interface_complementary'));
        Schema::dropIfExists(ShopTables::name('product_interface_secondary_categories'));
        Schema::dropIfExists(ShopTables::name('product_attribute_values'));
        Schema::dropIfExists(ShopTables::name('product_interface_attribute_values'));
        Schema::dropIfExists(ShopTables::name('product_interface_attributes'));
        Schema::dropIfExists(ShopTables::name('attribute_attribute_group'));
        Schema::dropIfExists(ShopTables::name('category_attribute_group'));
        Schema::dropIfExists(ShopTables::name('product_prices'));
        Schema::dropIfExists(ShopTables::name('campaigns'));
        Schema::dropIfExists(ShopTables::name('products'));
        Schema::dropIfExists(ShopTables::name('product_interfaces'));
        Schema::dropIfExists(ShopTables::name('attribute_values'));
        Schema::dropIfExists(ShopTables::name('attributes'));
        Schema::dropIfExists(ShopTables::name('attribute_groups'));
        Schema::dropIfExists(ShopTables::name('brands'));
    }

    private function createBrandsTable(): void
    {
        Schema::create(ShopTables::name('brands'), function (Blueprint $table): void {
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
        Schema::create(ShopTables::name('attribute_groups'), function (Blueprint $table): void {
            $table->id();

            // Translation: title, description.
            $table->text('languages')->nullable();
            $table->timestamps();
        });

        Schema::create(ShopTables::name('attributes'), function (Blueprint $table): void {
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

        Schema::create(ShopTables::name('attribute_values'), function (Blueprint $table): void {
            $table->id();

            // Translation: title (e.g. "Red", "42").
            $table->text('languages')->nullable();
            $table->foreignId('attribute_id')->constrained(ShopTables::name('attributes'), 'id', 'fk_av_attribute')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });
    }

    private function createProductInterfacesTable(): void
    {
        Schema::create(ShopTables::name('product_interfaces'), function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();

            // Soft host key — `categories` is owned by the host CMS. Nullable
            // + indexed so this package stays standalone-friendly even when
            // no host category system is wired up yet.
            $table->unsignedBigInteger('category_id')->nullable()->index();

            // Variant/configuration shape: simple|codding|digital|service.
            $table->string('type')->default('simple');

            // Generic, inventory-agnostic business classification:
            // physical|service|digital|bundle. Independent from `type` above
            // and never used to gate stock/availability (see ProductKindEnum).
            $table->string('kind')->default('physical')->index();

            $table->foreignId('brand_id')->nullable()->constrained(ShopTables::name('brands'), 'id', 'fk_pi_brand')->nullOnDelete();
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
        Schema::create(ShopTables::name('products'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_interface_id')->constrained(ShopTables::name('product_interfaces'), 'id', 'fk_p_interface')->cascadeOnDelete();
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
        // `external_discount_id` and `created_by` are soft keys: commerce
        // (discounts) and the host (users) are separate bounded contexts, so
        // this package never adds hard FKs to their tables. `campaign_type`
        // defaults to "price_adjustment" (catalog/pricing-scoped) rather than
        // an order-lifecycle type — this package never models order-based
        // campaign behavior; evaluating whether a campaign applies to an
        // order is entirely the host's `CampaignPriceAdjuster` bridge.
        Schema::create(ShopTables::name('campaigns'), function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('external_discount_id')->nullable()->index();
            $table->string('campaign_type')->default('price_adjustment')->comment('Campaign type: product_based, order_based (legacy), or price_adjustment');
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
        Schema::create(ShopTables::name('product_prices'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained(ShopTables::name('products'), 'id', 'fk_pp_product')->cascadeOnDelete();

            // Soft host key — generic segmentation (typically a user group),
            // owned by the host. Renamed from `user_group_id` to the generic
            // `segment_id`; `ProductPriceBuilder::userGroupId()` /
            // `QuoteBuilder::userGroupId()` remain as aliases.
            $table->unsignedBigInteger('segment_id')->nullable()->index();

            // Portable price tier (e.g. "retail", "wholesale"); independent from segment_id.
            $table->string('tier')->nullable();

            $table->decimal('price', 15, 0)->unsigned();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'segment_id']);
            $table->index(['product_id', 'tier']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    private function createCategoryPivotTables(): void
    {
        // Soft host key — `categories` is owned by the host; `attribute_groups` is owned here.
        Schema::create(ShopTables::name('category_attribute_group'), function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->index();
            $table->foreignId('attribute_group_id')->constrained(ShopTables::name('attribute_groups'), 'id', 'fk_cag_group')->cascadeOnDelete();

            $table->unique(['category_id', 'attribute_group_id'], 'category_attribute_group_unique');
        });
    }

    private function createAttributePivotTables(): void
    {
        // Links attributes to attribute groups (e.g. "Screen Size" -> "Mobile Phone").
        Schema::create(ShopTables::name('attribute_attribute_group'), function (Blueprint $table): void {
            $table->foreignId('attribute_group_id')->constrained(ShopTables::name('attribute_groups'), 'id', 'fk_aag_group')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained(ShopTables::name('attributes'), 'id', 'fk_aag_attribute')->cascadeOnDelete();

            $table->unique(['attribute_group_id', 'attribute_id'], 'attribute_attribute_group_unique');
        });
    }

    private function createProductInterfaceRelationTables(): void
    {
        $productInterfacesTable = ShopTables::name('product_interfaces');
        $attributesTable = ShopTables::name('attributes');
        $attributeValuesTable = ShopTables::name('attribute_values');
        $productsTable = ShopTables::name('products');

        // Links product interfaces to attributes (optionally as variant/"codding" axes).
        Schema::create(ShopTables::name('product_interface_attributes'), function (Blueprint $table) use ($productInterfacesTable, $attributesTable): void {
            $table->foreignId('product_interface_id')->constrained($productInterfacesTable, 'id', 'fk_pia_interface')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained($attributesTable, 'id', 'fk_pia_attribute')->cascadeOnDelete();
            $table->boolean('codding')->default(false);

            $table->unique(['product_interface_id', 'attribute_id'], 'product_interface_attribute_unique');
        });

        // Links product interfaces to shared (non-variant) attribute values.
        Schema::create(ShopTables::name('product_interface_attribute_values'), function (Blueprint $table) use ($productInterfacesTable, $attributesTable, $attributeValuesTable): void {
            $table->foreignId('product_interface_id')->constrained($productInterfacesTable, 'id', 'fk_piav_interface')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained($attributeValuesTable, 'id', 'fk_piav_value')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained($attributesTable, 'id', 'fk_piav_attribute')->cascadeOnDelete();

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
        Schema::create(ShopTables::name('product_attribute_values'), function (Blueprint $table) use ($productsTable, $attributesTable, $attributeValuesTable): void {
            $table->foreignId('product_id')->constrained($productsTable, 'id', 'fk_pav_product')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained($attributeValuesTable, 'id', 'fk_pav_value')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained($attributesTable, 'id', 'fk_pav_attribute')->cascadeOnDelete();

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

        Schema::create(ShopTables::name('product_interface_secondary_categories'), function (Blueprint $table) use ($productInterfacesTable): void {
            $table->unsignedBigInteger('product_interface_id');

            // Soft host key — `categories` is owned by the host CMS.
            $table->unsignedBigInteger('category_id');

            $table->foreign('product_interface_id', 'pi_sec_cat_pi_fk')
                ->references('id')
                ->on($productInterfacesTable)
                ->cascadeOnDelete();

            $table->unique(['product_interface_id', 'category_id'], 'pi_secondary_categories_unique');
        });

        Schema::create(ShopTables::name('product_interface_complementary'), function (Blueprint $table) use ($productInterfacesTable): void {
            $table->unsignedBigInteger('product_interface_id');
            $table->unsignedBigInteger('complementary_id');

            $table->primary(['product_interface_id', 'complementary_id']);

            $table->foreign('product_interface_id', 'fk_pic_interface')
                ->references('id')
                ->on($productInterfacesTable)
                ->cascadeOnDelete();

            $table->foreign('complementary_id', 'fk_pic_complementary')
                ->references('id')
                ->on($productInterfacesTable)
                ->cascadeOnDelete();
        });
    }
};
