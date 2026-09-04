# Usage Guide — Builders & Quotes (English)

Canonical "0 to 100" guide to the new Accounting-like, fluent surface on the
`Shop` facade: catalog builders, price writing, and the quote handoff to
checkout. Contract-first — inputs, outputs, events, and extension points.
Implementation details live in `src/`.

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
| Brand builder | `Shop::brand()` | `Builders\BrandBuilder` (new) |
| ProductInterface builder | `Shop::productInterface()` | `Builders\ProductInterfaceBuilder` (new) |
| Product builder | `Shop::product()` | `Builders\ProductBuilder` (new) |
| Price writer | `Shop::price()` | `Builders\ProductPriceBuilder` (new) |
| Quote reader | `Shop::quote()` | `Builders\QuoteBuilder` (new) |
| Quote service | `Shop::quotes()` | `Services\QuoteService` (new) |
| Pricing (existing) | `Shop::pricing()` | `Services\ProductPriceResolver` |
| Product cards (existing) | `Shop::products()` | `Services\ProductService` |
| Filters (existing) | `Shop::filters()` | `Services\ProductFilterService` |

Every builder call (`brand()`, `productInterface()`, `product()`, `price()`,
`quote()`) returns a **fresh, isolated instance** — nothing is shared or
reset between calls, so builders are safe to reuse in loops without leaking
state.

## Installation

See the [package README](../../README.md) for the full install steps. Short version:

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

```php
use Karnoweb\Shop\Facades\Shop;

$brand = Shop::brand()
    ->slug('acme')
    ->published(true)
    ->create();

$productInterface = Shop::productInterface()
    ->slug('coffee-beans-1kg')
    ->type('simple')
    ->brandId($brand->id)
    ->categoryId(10)          // soft key — plain integer, never FK-constrained
    ->published(true)
    ->create();

$product = Shop::product()
    ->productInterfaceId($productInterface->id)
    ->sku('COF-1KG')
    ->basePrice(1_200_000)
    ->published(true)
    ->isMain(true)
    ->create();
```

**Inputs/outputs:**

| Builder | Required inputs | Returns |
|---|---|---|
| `Shop::brand()` | `slug()` (unique) | Model configured at `shop.models.brand` |
| `Shop::productInterface()` | `slug()` (unique), `categoryId()` | Model configured at `shop.models.product_interface` |
| `Shop::product()` | `productInterfaceId()` | Model configured at `shop.models.product` |

All three also accept `attribute($key, $value)` (single raw attribute) and
`fill(array $attributes)` (bulk) as an escape hatch for host-specific columns
without waiting on a new builder method.

`categoryId()` and `brandId()` are **soft host keys**: plain integers/ids,
stored without `->constrained()`/`->foreign()`. The host owns the `categories`
table (or equivalent); this package never creates or reads it directly.

## Price records (ProductPrice)

`Shop::price()` is a **writer**, not a resolver — use it to create
time-windowed price rows. Reading happens through `Shop::pricing()` or
`Shop::quote()` (below).

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
default/group-less row), `amount()` (int|float, >= 0), `startsAt()` /
`endsAt()` (`DateTimeInterface|string|null`).

**Output:** the created model configured at `shop.models.product_price`.

**Invariants** (see [Validation & exceptions](#validation--exceptions)):
`startsAt <= endsAt` when both are set; `amount >= 0`; `productId` must
resolve to an existing row on the model configured at `shop.models.product`.

## Quote: pure DTO handoff to commerce

`Shop::quote()` resolves a **portable, immutable `PriceQuote` DTO** — no
dependency on `karnoweb/commerce` or any host class, in either direction.

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
| `productId` | `int` | Product the quote is for |
| `unitPrice` | `int` | Price resolved by `ProductPriceResolver` (before any campaign adjustment) |
| `basePrice` | `int` | The product's raw `base_price` column |
| `finalPrice` | `int` | Price after optional campaign adjustment (equals `unitPrice` when no adjuster is bound) |
| `hasDiscount` | `bool` | Whether a campaign adjustment changed the price |
| `discountPercent` | `int\|float\|null` | Discount percent, when known |
| `campaignId` | `int\|null` | Campaign id, when the host's `CampaignPriceAdjuster` reports one |
| `tier` | `string\|null` | The tier the caller asked for |
| `userGroupId` | `int\|null` | The user group the caller asked for |
| `source` | `string` | One of `"user_group"`, `"tier"`, `"default"`, `"base_price"` — which strategy matched |

```php
$quote->unitPrice;      // int
$quote->campaignId;     // ?int
$quote->source;         // "user_group" | "tier" | "default" | "base_price"
$quote->toCommerceSnapshot(); // array safe to store in commerce OrderItem.extra_attributes
```

### Handoff to commerce

`PriceQuote::toCommerceSnapshot()` returns a **plain array** (no objects,
no package classes) — the contract the host/commerce layer can depend on:

```php
[
    'product_id' => 123,
    'unit_price' => 1_000_000,
    'base_price' => 1_200_000,
    'final_price' => 1_000_000,
    'has_discount' => false,
    'discount_percent' => null,
    'campaign_id' => null,
    'tier' => 'retail',
    'user_group_id' => 7,
    'source' => 'user_group',
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
```

`resolve()` is now a thin backward-compatible adapter over
`resolveForUserGroupId()` — same resolve order, same return type, same
signature. Order: user group → explicit tier → default (null-group) window →
`base_price`.

## Existing product/filter services (unchanged)

`Shop::products()` (card pricing/flags) and `Shop::filters()` (category
tree, facets, price range) are untouched. See
[products.md](products.md) and [filters.md](filters.md).

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
(immediately if there is no open transaction). `Shop::product()->create()`
dispatches `ProductSaved` automatically:

```php
use Karnoweb\Shop\Events\ProductSaved;

Event::listen(ProductSaved::class, function (ProductSaved $event) {
    // $event->productId, $event->productInterfaceId
    // e.g. invalidate host showcase/category caches here.
});
```

Creating records through the raw Eloquent models (bypassing the builders)
does **not** fire this event automatically — dispatch it yourself via
`ShopEventDispatcher::dispatch(new ProductSaved(...))`, same as before this
pass. See [host-integration.md](host-integration.md).

## Extension points (host bridges)

Optional contracts the host may bind — builders and `QuoteService` work
without them, just with reduced behavior:

| Contract | Used by | Effect when bound |
|---|---|---|
| `Contracts\CampaignPriceAdjuster` | `Shop::products()`, `Shop::quote()` / `QuoteService` | Adjusts `finalPrice`/`hasDiscount`/`discountPercent`/`campaignId` on top of the resolved `unitPrice` |
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
- **Storefront session state** — wishlist/cart/compare/rating live behind
  `StorefrontContext`, bound by the host.
- **SMS, mail, or any other host action** — this package only reads/writes
  its own catalog tables.
- **Tenant/branch resolution** — the host resolves `user_id`/`branch_id`; this
  package never calls `Tenant::current()` or reads request headers.
