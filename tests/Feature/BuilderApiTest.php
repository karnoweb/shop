<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\Shop\DTOs\PriceQuote;
use Karnoweb\Shop\Exceptions\InvalidPriceAmountException;
use Karnoweb\Shop\Exceptions\InvalidPriceWindowException;
use Karnoweb\Shop\Exceptions\ProductNotFoundException;
use Karnoweb\Shop\Facades\Shop as ShopFacade;
use Karnoweb\Shop\Models\Brand;
use Karnoweb\Shop\Models\Product;
use Karnoweb\Shop\Models\ProductInterface;
use Karnoweb\Shop\Models\ProductPrice;
use Karnoweb\Shop\Services\ProductPriceResolver;
use Karnoweb\Shop\Tests\TestCase;

/**
 * Exercises the Accounting-like builder/quote surface end to end, matching
 * the exact scenario documented in docs/usage.md:
 * Brand -> ProductInterface (kind/extra) -> Product (extra) -> ProductPrice -> PriceQuote.
 */
final class BuilderApiTest extends TestCase
{
    use RefreshDatabase;

    // No defineDatabaseMigrations() override — ShopServiceProvider::boot()
    // already registers the real squashed schema for every test.

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Builders resolve models via config('shop.models.*'); standalone tests
        // have no App\Models\* host subclasses, so point config at the package's
        // own lean models instead (mirrors how a host would point it at its subclasses).
        $app['config']->set('shop.models.brand', Brand::class);
        $app['config']->set('shop.models.product_interface', ProductInterface::class);
        $app['config']->set('shop.models.product', Product::class);
        $app['config']->set('shop.models.product_price', ProductPrice::class);
    }

    public function test_full_builder_flow_creates_catalog_records(): void
    {
        $brand = ShopFacade::brand()
            ->slug('acme')
            ->published(true)
            ->create();

        $this->assertSame('acme', $brand->slug);
        $this->assertTrue($brand->published);

        $productInterface = ShopFacade::productInterface()
            ->slug('coffee-beans-1kg')
            ->type('simple')
            ->kind('simple')
            ->brandId($brand->id)
            ->categoryId(10)
            ->published(true)
            ->sku('COF-1KG')
            ->basePrice(1_200_000)
            ->weightGrams(1000)
            ->productPublished(true)
            ->extra(['origin' => 'brazil', 'roast' => 'medium'])
            ->create();

        $this->assertSame('coffee-beans-1kg', $productInterface->slug);
        $this->assertSame($brand->id, $productInterface->brand_id);
        $this->assertSame(10, $productInterface->category_id);
        $this->assertSame('simple', $productInterface->kind->value);
        $this->assertSame(
            ['origin' => 'brazil', 'roast' => 'medium'],
            $productInterface->extra_attributes
        );

        $product = $productInterface->mainProduct;
        $this->assertNotNull($product);
        $this->assertSame('COF-1KG', $product->sku);
        $this->assertSame(1200000, (int) $product->base_price);
        $this->assertTrue($product->is_main);
        $this->assertTrue($product->published);
        $this->assertSame(1000, $product->weight_grams);
        $this->assertSame($productInterface->id, $product->product_interface_id);
        $this->assertSame(1, $productInterface->products()->count());
    }

    public function test_product_interface_builder_extra_merges_across_calls(): void
    {
        $productInterface = ShopFacade::productInterface()
            ->slug('multi-extra-'.uniqid())
            ->type('simple')
            ->kind('virtual')
            ->categoryId(10)
            ->extra(['a' => 1])
            ->extra(['b' => 2])
            ->create();

        $this->assertSame(['a' => 1, 'b' => 2], $productInterface->extra_attributes);
        $this->assertSame('virtual', $productInterface->kind->value);
        $this->assertNotNull($productInterface->mainProduct);
        $this->assertTrue($productInterface->mainProduct->is_main);
        $this->assertFalse($productInterface->mainProduct->published);
    }

    public function test_quote_resolves_user_group_price_over_default_price(): void
    {
        [$product] = $this->createProductWithTwoPrices();

        $quote = ShopFacade::quote()
            ->productId($product->id)
            ->tier('retail')
            ->userGroupId(7)
            ->resolve();

        $this->assertInstanceOf(PriceQuote::class, $quote);
        $this->assertSame($product->id, $quote->productId);
        $this->assertSame('shop.product', $quote->itemType);
        $this->assertSame($product->id, $quote->itemId);
        $this->assertNull($quote->uomCode);
        $this->assertSame(1000000, $quote->basePrice);
        $this->assertSame(1000000, $quote->finalPrice);
        $this->assertFalse($quote->hasDiscount);
        $this->assertSame(0, $quote->discountAmount);
        $this->assertNull($quote->discountPercent);
        $this->assertNull($quote->campaignId);
        $this->assertSame('user_group_price', $quote->source);
        $this->assertSame(7, $quote->segmentId);
        $this->assertSame('retail', $quote->tier);

        $snapshot = $quote->toCommerceSnapshot();
        $this->assertSame([
            'item_type' => 'shop.product',
            'item_id' => $product->id,
            'product_id' => $product->id,
            'tier' => 'retail',
            'segment_id' => 7,
            'user_group_id' => 7,
            'base_price' => 1000000,
            'final_price' => 1000000,
            'has_discount' => false,
            'discount_amount' => 0,
            'discount_percent' => null,
            'campaign_id' => null,
            'source' => 'user_group_price',
            'uom_code' => null,
        ], $snapshot);

        // Minimum stable contract for a generic sellable snapshot (Commerce handoff).
        $this->assertArrayHasKey('item_type', $snapshot);
        $this->assertArrayHasKey('item_id', $snapshot);
        $this->assertArrayHasKey('final_price', $snapshot);

        foreach ($snapshot as $key => $value) {
            $this->assertTrue(
                is_scalar($value) || is_array($value) || $value === null,
                "Snapshot key [{$key}] must be scalar, array, or null; got ".get_debug_type($value)
            );
        }

        $rebuilt = PriceQuote::fromArray($snapshot);
        $this->assertEquals($quote, $rebuilt);
    }

    public function test_quote_reports_uom_code_and_generic_item_type_when_set(): void
    {
        $productInterface = ShopFacade::productInterface()
            ->slug('sack-of-rice-'.uniqid())
            ->type('simple')
            ->kind('simple')
            ->categoryId(10)
            ->published(true)
            ->sku('RICE-'.uniqid())
            ->basePrice(500_000)
            ->defaultUomCode('kg')
            ->productPublished(true)
            ->create();

        $product = $productInterface->mainProduct;

        $this->assertSame('kg', $product->default_uom_code);

        $quote = ShopFacade::quote()
            ->itemType('shop.product')
            ->itemId($product->id)
            ->resolve();

        $this->assertSame('kg', $quote->uomCode);
        $this->assertSame('shop.product', $quote->itemType);
        $this->assertSame($product->id, $quote->itemId);
        $this->assertSame('kg', $quote->toCommerceSnapshot()['uom_code']);
    }

    public function test_price_and_quote_builders_accept_canonical_segment_id(): void
    {
        $product = $this->createBaseProduct();

        ShopFacade::price()
            ->productId($product->id)
            ->segmentId(9)
            ->amount(1_500_000)
            ->save();

        $quote = ShopFacade::quote()
            ->productId($product->id)
            ->segmentId(9)
            ->resolve();

        $this->assertSame(9, $quote->segmentId);
        $this->assertSame(1500000, $quote->basePrice);
        $this->assertSame('user_group_price', $quote->source);
        $this->assertSame(9, $quote->toCommerceSnapshot()['segment_id']);
    }

    public function test_quote_falls_back_to_default_price_for_unknown_user_group(): void
    {
        [$product] = $this->createProductWithTwoPrices();

        $quote = ShopFacade::quote()
            ->productId($product->id)
            ->userGroupId(999)
            ->resolve();

        // No price row for group 999, and no tier requested -> falls through to the default (group-less) price row.
        $this->assertSame(1200000, $quote->basePrice);
        $this->assertSame('default_price', $quote->source);
    }

    public function test_quote_falls_back_to_base_price_when_no_price_rows_exist(): void
    {
        $product = $this->createBaseProduct();

        $quote = ShopFacade::quote()
            ->productId($product->id)
            ->resolve();

        $this->assertSame(1200000, $quote->basePrice);
        $this->assertSame(1200000, $quote->finalPrice);
        $this->assertSame(0, $quote->discountAmount);
        $this->assertSame('base_price', $quote->source);
    }

    public function test_resolve_for_user_group_id_works_directly(): void
    {
        [$product] = $this->createProductWithTwoPrices();

        $resolver = new ProductPriceResolver;

        $this->assertSame(1000000, $resolver->resolveForUserGroupId($product, 7));
        $this->assertSame(1200000, $resolver->resolveForUserGroupId($product, null));
    }

    public function test_legacy_resolve_with_user_object_still_works(): void
    {
        [$product] = $this->createProductWithTwoPrices();

        $resolver = new ProductPriceResolver;

        $userInGroup = (object) ['profile' => (object) ['user_group_id' => 7]];
        $userWithoutGroup = (object) ['profile' => (object) ['user_group_id' => null]];

        $this->assertSame(1000000, $resolver->resolve($product, $userInGroup));
        $this->assertSame(1200000, $resolver->resolve($product, $userWithoutGroup));
        $this->assertSame(1200000, $resolver->resolve($product, null));
    }

    public function test_price_builder_rejects_negative_amount(): void
    {
        $product = $this->createBaseProduct();

        $this->expectException(InvalidPriceAmountException::class);

        ShopFacade::price()
            ->productId($product->id)
            ->amount(-1)
            ->save();
    }

    public function test_price_builder_rejects_inverted_window(): void
    {
        $product = $this->createBaseProduct();

        $this->expectException(InvalidPriceWindowException::class);

        ShopFacade::price()
            ->productId($product->id)
            ->amount(1000)
            ->startsAt(now())
            ->endsAt(now()->subDay())
            ->save();
    }

    public function test_price_builder_rejects_unknown_product(): void
    {
        $this->expectException(ProductNotFoundException::class);

        ShopFacade::price()
            ->productId(999999)
            ->amount(1000)
            ->save();
    }

    public function test_quote_builder_rejects_unknown_product(): void
    {
        $this->expectException(ProductNotFoundException::class);

        ShopFacade::quote()
            ->productId(999999)
            ->resolve();
    }

    /**
     * @return array{0: Model}
     */
    private function createProductWithTwoPrices(): array
    {
        $product = $this->createBaseProduct();

        ShopFacade::price()
            ->productId($product->id)
            ->tier('retail')
            ->userGroupId(null)
            ->amount(1_200_000)
            ->startsAt(now()->subDay())
            ->endsAt(now()->addMonth())
            ->save();

        ShopFacade::price()
            ->productId($product->id)
            ->tier('retail')
            ->userGroupId(7)
            ->amount(1_000_000)
            ->startsAt(now()->subDay())
            ->endsAt(now()->addMonth())
            ->save();

        return [$product];
    }

    private function createBaseProduct(): Model
    {
        $brand = ShopFacade::brand()->slug('acme-'.uniqid())->published(true)->create();

        $productInterface = ShopFacade::productInterface()
            ->slug('coffee-beans-'.uniqid())
            ->type('simple')
            ->kind('simple')
            ->brandId($brand->id)
            ->categoryId(10)
            ->published(true)
            ->sku('COF-'.uniqid())
            ->basePrice(1_200_000)
            ->productPublished(true)
            ->create();

        return $productInterface->mainProduct;
    }
}
