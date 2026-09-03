# محصول و کارت ویترین

```php
use Karnoweb\Shop\Facades\Shop;
use Karnoweb\Shop\Models\Product;
use Karnoweb\Shop\Models\ProductInterface;

$interfaces = ProductInterface::query()
    ->published()
    ->with(['mainProduct', 'brand'])
    ->get();

$products = Product::query()->active()->main()->get();

$cards = Shop::products()->resolveForProducts($products);
// $cards[$id] ≈ base_price, final_price, has_discount, discount_percent,
//               is_in_wishlist, is_in_cart, is_in_compare, rating
```

فلگ‌های wishlist/cart/compare/rating از قرارداد `StorefrontContext` می‌آیند؛ بدون bind میزبان معمولاً خنثی/خالی‌اند.

## قوانین

- برای لیست ویترین، `ProductInterface::published()` و محصول `active()` را ترجیح دهید.
- `inStock()` روی Product منسوخ است؛ موجودی را از انبار بگیرید.
- رویداد `ProductSaved` را در صورت نیاز از میزبان با `ShopEventDispatcher::dispatch` بعد از ذخیره بفرستید (پکیج به‌صورت خودکار fire نمی‌کند مگر جایی که صریحاً صدا زده شود).

## خطاها

سرویس‌ها معمولاً استثنای دامنهٔ اختصاصی پرتاب نمی‌کنند؛ قیمت نامعتبر به fallback `base_price` یا صفر منطقی resolver می‌رسد.

## نتیجه ذخیره‌شده

`resolveForProducts` چیزی در DB نمی‌نویسد؛ فقط آرایه/کالکشن محاسبه‌شده برمی‌گرداند.
