# Usage Guide — Catalog, Pricing & Quotes (English)

Canonical "0 → 100" guide to the `Shop` facade: the fluent catalog builders,
generic product kinds, extra attributes, price writing, and the quote
handoff to checkout/commerce. Contract-first — inputs, outputs, events, and
extension points. Implementation details live in `src/`.

For the pre-existing, Persian, section-by-section walkthrough of the
service-based surface (`Shop::products()`, `Shop::filters()`, host
integration), see [README.md](README.md) and its linked pages. This document
is the single English reference for the builder/quote surface and is kept in
sync with the Facade's `@method` annotations.

---

## Contents

- [Entry point](#entry-point)
- [Installation](#installation)
- [0 → 100: building a catalog](#0--100-building-a-catalog)
- [Product kinds (generic business classification)](#product-kinds-generic-business-classification)
- [Variant modeling: ProductInterface vs Product](#variant-modeling-productinterface-vs-product)
- [Extra attributes (structured JSON extension point)](#extra-attributes-structured-json-extension-point)
- [Price records (ProductPrice)](#price-records-productprice)
- [Quote: pure DTO handoff to commerce](#quote-pure-dto-handoff-to-commerce)
- [Existing pricing API (unchanged)](#existing-pricing-api-unchanged)
- [Existing product/filter services (unchanged)](#existing-productfilter-services-unchanged)
- [Validation & exceptions](#validation--exceptions)
- [Events](#events)
- [Extension points (host bridges)](#extension-points-host-bridges)
- [What stays in host](#what-stays-in-host)

---

## Entry point

Everything goes through the `Shop` facade or the injected `Karnoweb\Shop\Shop`
manager:

```php
use Karnoweb\Shop\Facades\Shop;
```

| Surface | Accessor | Returns |
|---|---|---|
| Config | `Shop::config($key, $default)` | `mixed` |
| Model class | `Shop::model($key)` / `Shop::newModel($key)` | `class-string<Model>` / `Model` |
| Brand builder | `Shop::brand()` | `Builders\BrandBuilder` |
| ProductInterface builder | `Shop::productInterface()` | `Builders\ProductInterfaceBuilder` |
| Product builder | `Shop::product()` | `Builders\ProductBuilder` |
| Price writer | `Shop::price()` | `Builders\ProductPriceBuilder` |
| Quote reader | `Shop::quote()` | `Builders\QuoteBuilder` |
| Quote service | `Shop::quotes()` | `Services\QuoteService` |
| Pricing (existing) | `Shop::pricing()` | `Services\ProductPriceResolver` |
| Product cards (existing) | `Shop::products()` | `Services\ProductService` |
| Filters (existing) | `Shop::filters()` | `Services\ProductFilterService` |

Every builder call (`brand()`, `productInterface()`, `product()`, `price()`,
`quote()`) returns a **fresh, isolated instance** — nothing is shared or
reset between calls, so builders are safe to reuse in loops without leaking
state.

## Installation

See the [package README](../README.md) for the full install steps. Short version:

```bash
composer require karnoweb/shop:^13.0
php artisan vendor:publish --tag=shop-config
php artisan vendor:publish --tag=shop-migrations
php artisan migrate
```

Point `shop.models.*` (or the matching `SHOP_*_MODEL` env vars) at your host
subclasses (e.g. `App\Models\Brand extends Karnoweb\Shop\Models\Brand`).
Builders always resolve the model class through this config, so host
subclasses are used automatically — nothing in `src/Builders` hardcodes a
concrete model.

## 0 → 100: building a catalog

The full scenario below uses **only public API** and is exercised end to end
by `tests/Feature/BuilderApiTest.php`.

```php
use Karnoweb\Shop\Facades\Shop;

$brand = Shop::brand()
    ->slug('acme')
    ->published(true)
    ->create();

$pi = Shop::productInterface()
    ->slug('coffee-beans-1kg')
    ->type('simple')
    ->kind('physical')        // physical|service|digital|bundle
    ->brandId($brand->id)
    ->categoryId(10)          // soft host key
    ->published(true)
    ->create();

$product = Shop::product()
    ->productInterfaceId($pi->id)
    ->sku('COF-1KG')
    ->isMain(true)
    ->basePrice(1_200_000)
    ->published(true)
    ->create();

Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->userGroupId(null)
    ->amount(1_200_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();

Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->userGroupId(7)
    ->amount(1_000_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();

$quote = Shop::quote()
    ->productId($product->id)
    ->tier('retail')
    ->userGroupId(7)
    ->resolve(); // returns PriceQuote DTO

$snapshot = $quote->toCommerceSnapshot();
// $snapshot is a pure array, stable keys, no external class references.
```

**Inputs/outputs:**

| Builder | Required inputs | Returns |
|---|---|---|
| `Shop::brand()` | `slug()` (unique) | Model configured at `shop.models.brand` |
| `Shop::productInterface()` | `slug()` (unique), `categoryId()` | Model configured at `shop.models.product_interface` |
| `Shop::product()` | `productInterfaceId()` | Model configured at `shop.models.product` |

All three also accept `attribute($key, $value)` (single raw attribute),
`extra(array $attributes)` (structured JSON extension point — see below),
and `fill(array $attributes)` (bulk) as escape hatches for host-specific
columns without waiting on a new builder method.

`categoryId()` and `brandId()` are **soft host keys**: plain integers/ids,
stored without `->constrained()`/`->foreign()`. The host owns the `categories`
table (or equivalent); this package never creates or reads it directly.

## Product kinds (generic business classification)

`ProductInterface.kind` is a **generic, inventory-agnostic** business
classification — "what kind of thing is this to the business" — independent
from `type` (the variant/configuration shape: `simple`/`codding`/`digital`/`service`)
and independent from stock/inventory (`karnoweb/laravel-inventory` on the host):

| Kind | Meaning |
|---|---|
| `physical` (default) | A stockable, shippable good |
| `service` | A non-stock service (labor, consulting, subscription seat, ...) |
| `digital` | A non-stock digital good (license key, download, ...) |
| `bundle` | A composite/kit of other products (composition is host/commerce concern) |

```php
use Karnoweb\Shop\Enums\ProductKindEnum;

Shop::productInterface()->slug('consulting-hour')->kind('service')->categoryId(20)->create();
Shop::productInterface()->slug('ebook-license')->kind(ProductKindEnum::DIGITAL)->categoryId(21)->create();
```

`kind` accepts a plain string or a `ProductKindEnum` case and is cast to
`ProductKindEnum` on the model (`$productInterface->kind->value`,
`$productInterface->kind->title()` for a translated label). This package
never uses `kind` to gate stock/availability logic — that stays entirely
with the host's inventory integration.

## Variant modeling: ProductInterface vs Product

- **`ProductInterface`** — the parent: shared, translatable attributes
  (`title`, `description`, `body`), `type`, `kind`, `brand_id`, `category_id`,
  publishing flags. One row per "product family".
- **`Product`** — a variant/SKU: `sku`, `base_price`, `is_main`, and
  variant-specific attribute values. One or more rows per `ProductInterface`
  (`ProductInterface::hasMany(Product::class)` / `Product::belongsTo(ProductInterface::class)`).

```php
$pi = Shop::productInterface()->slug('t-shirt')->type('codding')->kind('physical')->categoryId(5)->create();

$small = Shop::product()->productInterfaceId($pi->id)->sku('TSHIRT-S')->basePrice(400_000)->isMain(true)->create();
$large = Shop::product()->productInterfaceId($pi->id)->sku('TSHIRT-L')->basePrice(420_000)->create();
```

Pricing, price windows, and quotes are always resolved **per `Product`**
(per SKU/variant), never per `ProductInterface` — see
[Price records](#price-records-productprice) below.

## Extra attributes (structured JSON extension point)

Both `ProductInterface.extra_attributes` and `Product.extra_attributes` are
nullable `json` columns (`Brand.extra_attributes` also exists, for parity),
cast to `array` on the model — a documented, query-friendly place for
business-specific data that doesn't warrant a dedicated column:

```php
$pi = Shop::productInterface()
    ->slug('coffee-beans-1kg')
    ->categoryId(10)
    ->extra(['origin' => 'brazil', 'roast' => 'medium'])
    ->create();

$product = Shop::product()
    ->productInterfaceId($pi->id)
    ->sku('COF-1KG')
    ->basePrice(1_200_000)
    ->extra(['weight_grams' => 1000])
    ->create();

$pi->extra_attributes;      // ['origin' => 'brazil', 'roast' => 'medium']
$product->extra_attributes; // ['weight_grams' => 1000]
```

`->extra(array $attributes)` is available on `Shop::brand()`,
`Shop::productInterface()`, and `Shop::product()`. Repeated calls **merge**
(shallow) into the same array rather than replacing it:

```php
Shop::productInterface()->extra(['a' => 1])->extra(['b' => 2]); // extra_attributes === ['a' => 1, 'b' => 2]
```

Because the column is plain `json`, it stays query-friendly with
`whereJsonContains()` / `->where('extra_attributes->origin', 'brazil')` on
supporting databases — no separate EAV tables are introduced.

## Price records (ProductPrice)

`Shop::price()` is a **writer**, not a resolver — use it to create
time-windowed price rows, one per `Product` (SKU). Reading happens through
`Shop::pricing()` or `Shop::quote()` (below).

```php
Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->userGroupId(null)
    ->amount(1_200_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();

Shop::price()
    ->productId($product->id)
    ->tier('retail')
    ->userGroupId(7)
    ->amount(1_000_000)
    ->startsAt(now()->subDay())
    ->endsAt(now()->addMonth())
    ->save();
```

**Inputs:** `productId()` (required), `tier()`, `userGroupId()` (null =
default/group-less row — a **soft host key**, never FK-constrained), `amount()`
(int|float, >= 0), `startsAt()` / `endsAt()` (`DateTimeInterface|string|null`).

**Output:** the created model configured at `shop.models.product_price`.

**Invariants** (see [Validation & exceptions](#validation--exceptions)):
`startsAt <= endsAt` when both are set; `amount >= 0`; `productId` must
resolve to an existing row on the model configured at `shop.models.product`.

## Quote: pure DTO handoff to commerce

`Shop::quote()` resolves a **portable, immutable `PriceQuote` DTO** — no
dependency on `karnoweb/commerce` or any host class, in either direction.
It accepts `userGroupId`/`tier` **explicitly**; it never assumes a host user
object shape (no `auth()->user()`, no `data_get($user, 'profile...')`).

```php
$quote = Shop::quote()
    ->productId($product->id)
    ->tier('retail')
    ->userGroupId(7)
    ->resolve(); // returns Karnoweb\Shop\DTOs\PriceQuote
```

**Inputs:** `productId()` (required, must exist), `tier()` (optional),
`userGroupId()` (optional).

**Output — `PriceQuote` fields:**

| Field | Type | Meaning |
|---|---|---|
| `productId` | `int` | Product (SKU) the quote is for |
| `tier` | `string\|null` | The tier the caller asked for |
| `userGroupId` | `int\|null` | The soft host user-group key the caller asked for |
| `basePrice` | `int` | Price resolved by `ProductPriceResolver` for this tier/group — see `source` — before any campaign adjustment |
| `finalPrice` | `int` | Price after optional campaign adjustment (equals `basePrice` when no adjuster is bound, or none applied) |
| `hasDiscount` | `bool` | Whether a campaign adjustment changed the price |
| `discountAmount` | `int` | `basePrice - finalPrice`, always `>= 0` |
| `discountPercent` | `float\|null` | Discount percent, when known/computable |
| `campaignId` | `int\|null` | Campaign id, when the host's `CampaignPriceAdjuster` reports one |
| `source` | `string` | Which strategy produced `basePrice`: `"user_group_price"` \| `"tier_price"` \| `"default_price"` \| `"base_price"` |

```php
$quote->basePrice;   // int
$quote->source;      // "user_group_price" | "tier_price" | "default_price" | "base_price"
$quote->toCommerceSnapshot(); // array safe to store in commerce OrderItem.extra_attributes
```

### Handoff to commerce

`PriceQuote::toCommerceSnapshot()` returns a **plain array** of
scalars/`null` (no objects, no package classes) — the stable contract the
host/commerce layer can depend on:

```php
[
    'product_id' => 123,
    'tier' => 'retail',
    'user_group_id' => 7,
    'base_price' => 1_000_000,
    'final_price' => 1_000_000,
    'has_discount' => false,
    'discount_amount' => 0,
    'discount_percent' => null,
    'campaign_id' => null,
    'source' => 'user_group_price',
]
```

Persist this array as-is in something like `commerce.OrderItem.extra_attributes`
at the moment of checkout. `PriceQuote::fromArray($snapshot)` rebuilds the DTO
from a stored snapshot when you need to re-inspect it later (e.g. for
refunds/audits) — this package never reads or writes commerce tables itself.

### QuoteService (without the builder)

`Shop::quotes()` returns the underlying `QuoteService` directly, if you'd
rather call it without the fluent builder (e.g. when you already loaded the
product model):

```php
$quote = Shop::quotes()->resolve($product, userGroupId: 7, tier: 'retail');
```

## Existing pricing API (unchanged)

The lower-level resolver keeps working exactly as before — no breaking
changes:

```php
Shop::pricing()->resolve($product, $userObj, 'retail');
```

Two additive methods were added to `ProductPriceResolver` for callers that
don't have (or don't want to assume) a host user object:

```php
Shop::pricing()->resolveForUserGroupId($product, userGroupId: 7, tier: 'retail'); // int
Shop::pricing()->resolveDetailedForUserGroupId($product, userGroupId: 7, tier: 'retail'); // ['price' => int, 'source' => string]
```

`resolve()` is now a thin backward-compatible adapter over
`resolveForUserGroupId()` — same resolve order, same return type, same
signature. Order: user group → explicit tier → default (null-group) window →
`base_price`.

## Existing product/filter services (unchanged)

`Shop::products()` (card pricing/flags) and `Shop::filters()` (category
tree, facets, price range) are untouched. See
[usage/products.md](usage/products.md) and [usage/filters.md](usage/filters.md).

## Validation & exceptions

Builders throw dedicated, catchable exceptions instead of generic
`\Exception` — all under `Karnoweb\Shop\Exceptions`:

| Exception | Thrown by | When |
|---|---|---|
| `ProductNotFoundException` | `Shop::price()->save()`, `Shop::quote()->resolve()` | `productId` missing or does not exist on the configured Product model |
| `InvalidPriceAmountException` | `Shop::price()->save()` | `amount` is negative |
| `InvalidPriceWindowException` | `Shop::price()->save()` | `startsAt` is after `endsAt` |

```php
use Karnoweb\Shop\Exceptions\ProductNotFoundException;

try {
    Shop::price()->productId($missingId)->amount(1000)->save();
} catch (ProductNotFoundException $e) {
    // $e->productId
}
```

## Events

Domain events dispatch **after DB commit** via `ShopEventDispatcher`
(immediately if there is no open transaction).

| Event | Fired by | Payload |
|---|---|---|
| `ProductSaved` | `Shop::product()->create()` | `productId`, `productInterfaceId` |

```php
use Karnoweb\Shop\Events\ProductSaved;

Event::listen(ProductSaved::class, function (ProductSaved $event) {
    // $event->productId, $event->productInterfaceId
    // e.g. invalidate host showcase/category caches here.
});
```

Creating records through the raw Eloquent models (bypassing the builders)
does **not** fire this event automatically — dispatch it yourself via
`ShopEventDispatcher::dispatch(new ProductSaved(...))`. See
[usage/host-integration.md](usage/host-integration.md).

## Extension points (host bridges)

Optional contracts the host may bind — builders and `QuoteService` work
without them, just with reduced behavior:

| Contract | Used by | Effect when bound |
|---|---|---|
| `Contracts\CampaignPriceAdjuster` | `Shop::products()`, `Shop::quote()` / `QuoteService` | Adjusts `finalPrice`/`hasDiscount`/`discountAmount`/`discountPercent`/`campaignId` on top of the resolved `basePrice` |
| `Contracts\StorefrontContext` | `Shop::products()` | Supplies wishlist/cart/compare/rating flags for product cards |

Binding is entirely the host's job (typically in a host service provider);
this package never requires either contract to function.

## What stays in host

Per `SHOP_PACKAGE.md`, this package remains a **catalog domain** package.
The following are explicitly **not** implemented here, even by the new
builders:

- **UI** — no controllers, routes, Livewire, or admin screens.
- **Campaign condition evaluation** — `Campaign`/`CampaignConditionTypeEnum`
  are persistence + enums only; evaluating whether a campaign applies (and
  computing its discount) is the host's `CampaignPriceAdjuster` bridge.
- **Orders, invoices, payments** — owned by `karnoweb/commerce`. `PriceQuote`
  is deliberately a plain array/DTO so commerce/host code can consume it
  without this package depending on commerce, and vice versa.
- **Bundle composition** — `kind('bundle')` is classification only; which
  products make up a bundle, and how it's priced/fulfilled, is host/commerce.
- **Storefront session state** — wishlist/cart/compare/rating live behind
  `StorefrontContext`, bound by the host.
- **SMS, mail, or any other host action** — this package only reads/writes
  its own catalog tables.
- **Tenant/branch resolution** — the host resolves `user_id`/`branch_id`; this
  package never calls `Tenant::current()` or reads request headers.
