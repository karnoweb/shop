# قیمت

```php
use Karnoweb\Shop\Facades\Shop;

// بر اساس کاربر (گروه کاربری)
$amount = Shop::pricing()->resolve($product, auth()->user());

// بر اساس tier قابل حمل
$amount = Shop::pricing()->resolve($product, null, 'wholesale');

// قیمت کارت + امکان تعدیل کمپین
$priced = Shop::products()->resolvePrice($product, auth()->user());
```

ساخت قیمت پنجره‌ای:

```php
use Karnoweb\Shop\Models\ProductPrice;

ProductPrice::query()->create([
    'product_id' => $product->id,
    'price' => 1_200_000,
    'tier' => 'retail',
    'user_group_id' => null,
    'starts_at' => now()->subDay(),
    'ends_at' => now()->addMonth(),
]);
```

## قوانین

- ترتیب resolve: گروه کاربری → tier → قیمت پیش‌فرض پنجره‌ای → `base_price`.
- برای تخفیف کمپین، `CampaignPriceAdjuster` را در Service Provider میزبان bind کنید.
- ارز نمایشی از `shop.pricing.currency` خوانده می‌شود؛ تبدیل ارز داخل پکیج نیست.

## خطاها

اگر ردیف قیمت معتبری نباشد، به `base_price` محصول برمی‌گردد.

## نتیجه ذخیره‌شده

فقط وقتی خودتان `ProductPrice` (یا `base_price`) را ذخیره کنید؛ `resolve` فقط می‌خواند.
