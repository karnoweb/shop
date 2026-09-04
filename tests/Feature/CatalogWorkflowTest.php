<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\Shop\Contracts\BranchContextResolverContract;
use Karnoweb\Shop\Enums\ProductInterfaceTypeEnum;
use Karnoweb\Shop\Enums\ProductKindEnum;
use Karnoweb\Shop\Enums\VariantsStatusEnum;
use Karnoweb\Shop\Facades\Shop as ShopFacade;
use Karnoweb\Shop\Models\Attribute;
use Karnoweb\Shop\Models\AttributeValue;
use Karnoweb\Shop\Models\Product;
use Karnoweb\Shop\Shop;
use Karnoweb\Shop\Support\ShopContext;
use Karnoweb\Shop\Tests\TestCase;

final class CatalogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_product_interface_always_creates_main_product(): void
    {
        $pi = ShopFacade::productInterface()
            ->slug('tea-tin')
            ->type(ProductInterfaceTypeEnum::SIMPLE)
            ->kind(ProductKindEnum::SIMPLE)
            ->create();

        $this->assertNotNull($pi->mainProduct);
        $this->assertTrue($pi->mainProduct->is_main);
        $this->assertFalse($pi->mainProduct->published);
        $this->assertSame('tea-tin', $pi->mainProduct->sku);
        $this->assertSame($pi->id, $pi->mainProduct->product_interface_id);
        $this->assertNull($pi->branch_id);
        $this->assertNull($pi->mainProduct->branch_id);
        $this->assertSame(1, Product::query()->where('product_interface_id', $pi->id)->count());
    }

    public function test_main_product_inherits_branch_and_uom(): void
    {
        $pi = ShopFacade::productInterface()
            ->slug('branch-tea')
            ->branchId(4)
            ->defaultUomCode('pcs')
            ->create();

        $this->assertSame(4, $pi->branch_id);
        $this->assertSame(4, $pi->mainProduct->branch_id);
        $this->assertSame('pcs', $pi->mainProduct->default_uom_code);
    }

    public function test_codding_preview_returns_cartesian_count(): void
    {
        [$pi, $axes] = $this->makeCoddingInterfaceWithTwoByTwoAxes();

        $preview = ShopFacade::variants()
            ->forProductInterface($pi->id)
            ->codingAxes($axes)
            ->preview();

        $this->assertSame(4, $preview->count());
        $this->assertCount(4, $preview->variants);
        $this->assertSame(1, Product::query()->where('product_interface_id', $pi->id)->count());
        $this->assertNotSame('', $preview->variants[0]['sku']);
        $this->assertNotSame('', $preview->variants[0]['signature']);
    }

    public function test_safe_sync_creates_missing_variants_and_does_not_delete_locked(): void
    {
        [$pi, $axes] = $this->makeCoddingInterfaceWithTwoByTwoAxes();

        $first = ShopFacade::variants()
            ->forProductInterface($pi->id)
            ->codingAxes($axes)
            ->sync('safe');

        $this->assertSame(4, $first->created);
        $this->assertSame(5, Product::query()->where('product_interface_id', $pi->id)->count());
        $this->assertSame(VariantsStatusEnum::READY, $pi->fresh()->variants_status);

        $locked = Product::query()
            ->where('product_interface_id', $pi->id)
            ->where('is_main', false)
            ->orderBy('id')
            ->first();

        ShopFacade::products()->lock($locked->id, 'sold', 99);
        $this->assertNotNull($locked->fresh()->locked_at);

        $colorId = array_key_first($axes);
        $shrunk = $axes;
        $shrunk[$colorId]['values'] = [($axes[$colorId]['values'][0])];

        $second = ShopFacade::variants()
            ->forProductInterface($pi->id)
            ->codingAxes($shrunk)
            ->sync('safe');

        $this->assertSame(0, $second->created);
        $this->assertSame(5, Product::query()->where('product_interface_id', $pi->id)->count());
        $this->assertNotNull($locked->fresh()->locked_at);
        $this->assertFalse($locked->fresh()->isSuspended());
        $this->assertGreaterThan(0, $second->skippedLocked);
        $this->assertGreaterThan(0, $second->suspended);

        $suspended = Product::query()
            ->where('product_interface_id', $pi->id)
            ->where('is_main', false)
            ->where('id', '!=', $locked->id)
            ->get()
            ->filter(fn (Product $product): bool => $product->isSuspended());

        $this->assertNotEmpty($suspended);
    }

    public function test_pricing_resolve_respects_currency(): void
    {
        $product = $this->makePricedProduct();

        ShopFacade::price()
            ->productId($product->id)
            ->currency('IRR')
            ->amount(1_000_000)
            ->save();

        ShopFacade::price()
            ->productId($product->id)
            ->currency('USD')
            ->amount(12)
            ->save();

        $this->assertSame(1_000_000, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'IRR'));
        $this->assertSame(12, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'USD'));
    }

    public function test_pricing_resolve_respects_inherit_global_branch_mode(): void
    {
        config(['shop.catalog.branch_mode' => 'inherit_global']);

        $product = $this->makePricedProduct();

        ShopFacade::price()
            ->productId($product->id)
            ->branchId(null)
            ->currency('IRR')
            ->amount(500_000)
            ->save();

        ShopFacade::price()
            ->productId($product->id)
            ->branchId(3)
            ->currency('IRR')
            ->amount(700_000)
            ->save();

        $this->assertSame(700_000, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'IRR', 3));
        $this->assertSame(500_000, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'IRR', 9));
        $this->assertSame(500_000, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'IRR', null));
    }

    public function test_pricing_resolve_respects_strict_branch_mode(): void
    {
        config(['shop.catalog.branch_mode' => 'strict']);

        $product = $this->makePricedProduct();

        ShopFacade::price()
            ->productId($product->id)
            ->branchId(null)
            ->currency('IRR')
            ->amount(500_000)
            ->save();

        ShopFacade::price()
            ->productId($product->id)
            ->branchId(3)
            ->currency('IRR')
            ->amount(700_000)
            ->save();

        $this->assertSame(700_000, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'IRR', 3));
        $this->assertSame((int) $product->base_price, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'IRR', 9));
        $this->assertSame(500_000, ShopFacade::pricing()->resolveForUserGroupId($product, null, null, 'IRR', null));
    }

    public function test_bulk_prices_write_for_interface_products(): void
    {
        $pi = ShopFacade::productInterface()->slug('bulk-tea')->basePrice(10)->create();

        ShopFacade::product()
            ->productInterfaceId($pi->id)
            ->sku('bulk-tea-extra')
            ->create();

        $rows = ShopFacade::prices()
            ->forProductInterface($pi->id)
            ->tier('retail')
            ->segmentId(null)
            ->currency('IRR')
            ->amount(1_200_000)
            ->saveAll();

        $this->assertCount(2, $rows);
        $this->assertSame(1_200_000, ShopFacade::pricing()->resolveForUserGroupId($pi->mainProduct, null, 'retail', 'IRR'));
    }

    public function test_context_defaults_to_resolver_branch(): void
    {
        $this->app->instance(BranchContextResolverContract::class, new class implements BranchContextResolverContract
        {
            public function branchId(): ?int
            {
                return 8;
            }
        });
        $this->app->forgetInstance(ShopContext::class);
        $this->app->forgetInstance('shop');
        $this->app->forgetInstance(Shop::class);
        ShopFacade::clearResolvedInstances();

        $this->assertSame(8, ShopFacade::context()->branchId());

        $pi = ShopFacade::productInterface()->slug('resolved-branch')->create();
        $this->assertSame(8, $pi->branch_id);
        $this->assertSame(8, $pi->mainProduct->branch_id);
    }

    /**
     * @return array{0: Model, 1: array<int, array{coding: bool, values: list<int>}>}
     */
    private function makeCoddingInterfaceWithTwoByTwoAxes(): array
    {
        $color = Attribute::query()->create(['type' => 'select', 'order' => 1]);
        $size = Attribute::query()->create(['type' => 'select', 'order' => 2]);

        $red = AttributeValue::query()->create(['attribute_id' => $color->id, 'order' => 1]);
        $blue = AttributeValue::query()->create(['attribute_id' => $color->id, 'order' => 2]);
        $small = AttributeValue::query()->create(['attribute_id' => $size->id, 'order' => 1]);
        $large = AttributeValue::query()->create(['attribute_id' => $size->id, 'order' => 2]);

        $pi = ShopFacade::productInterface()
            ->slug('hoodie')
            ->type(ProductInterfaceTypeEnum::CODDING)
            ->kind(ProductKindEnum::SIMPLE)
            ->create();

        $axes = [
            $color->id => ['coding' => true, 'values' => [$red->id, $blue->id]],
            $size->id => ['coding' => true, 'values' => [$small->id, $large->id]],
        ];

        return [$pi, $axes];
    }

    private function makePricedProduct(): Product
    {
        $pi = ShopFacade::productInterface()
            ->slug('priced-'.uniqid())
            ->basePrice(100)
            ->create();

        return $pi->mainProduct;
    }
}
