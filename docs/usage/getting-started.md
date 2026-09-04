# نصب و راه‌اندازی

```bash
composer require karnoweb/shop:^13.0
php artisan vendor:publish --tag=shop-config
php artisan vendor:publish --tag=shop-migrations   # الزامی
php artisan vendor:publish --tag=shop-lang         # اختیاری
php artisan migrate
```

از نسخهٔ ۱۳.۳ به بعد کل schema در **یک migration فشرده** (`database/migrations_squashed`) پابلیش می‌شود؛ مایگریشن‌های قدیمی تکه‌تکه (`database/migrations_legacy`) فقط برای مرجع تاریخی نگه داشته شده‌اند و اجرا نمی‌شوند.

از نسخهٔ ۱۳.۴ به بعد همهٔ جداول این پکیج **پیشوند** دارند (پیش‌فرض `shp_`؛ مثلاً `shp_products`, `shp_brands`) — دقیقاً مثل الگوی `karnoweb/laravel-inventory`. **قبل از `migrate`** پیشوند یا نام دقیق هر جدول را تنظیم کنید؛ تغییر بعدی جداول موجود را rename نمی‌کند:

```env
SHOP_TABLE_PREFIX=shp_
# برای بازگشت به نام‌های بدون پیشوند (دیپلوی‌های قدیمی‌تر):
# SHOP_TABLE_PREFIX=

# override دقیق یک جدول به‌جای پیشوند:
SHOP_TABLE_BRANDS=catalog_brands

SHOP_CURRENCY=IRR
SHOP_USER_KEY_TYPE=int
SHOP_BRANCH_KEY_TYPE=int
```

مدل‌ها را به subclass میزبان اشاره دهید (پیش‌فرضهای config همین الگوی `App\Models\…` را دارند):

```env
SHOP_PRODUCT_MODEL=App\Models\Product
SHOP_PRODUCT_INTERFACE_MODEL=App\Models\ProductInterface
SHOP_BRAND_MODEL=App\Models\Brand
# … سایر SHOP_*_MODEL
```

## فاساد

```php
use Karnoweb\Shop\Facades\Shop;

Shop::config('pricing.currency');
Shop::model('product');      // class-string
Shop::newModel('brand');
Shop::pricing();             // ProductPriceResolver
Shop::products();            // ProductService
Shop::filters();             // ProductFilterService

Shop::macro('featured', fn () => Shop::model('product')::query()->main()->limit(8)->get());
```

## قوانین

- قبل از migrate، جداول وابستهٔ میزبان مثل `users` / `categories` (در صورت FK) باید وجود داشته باشند یا مایگریشن‌ها با ترتیب پروژه سازگار شوند.
- ترجمهٔ فیلدهای Brand و ProductInterface از `karnoweb/translation` می‌آید؛ آن پکیج را هم نصب و migrate کنید.

## خطاها

خطاهای نصب معمولاً migration/config لاراول‌اند. مدل نامعتبر در `Shop::model()` استثنا می‌دهد.

## نتیجه ذخیره‌شده

جداول کاتالوگ، pivot ویژگی‌ها، `product_prices` و `campaigns` (همگی با پیشوند تنظیم‌شده) و morph mapهای `shop_*` در boot ثبت می‌شوند. این پکیج دیگر جدول `user_wishlists` نمی‌سازد — وضعیت علاقه‌مندی/سبد/مقایسه کاملاً از طریق قرارداد میزبان `StorefrontContext` است.
