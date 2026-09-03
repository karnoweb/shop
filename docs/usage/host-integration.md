# اتصال به اپ میزبان

## subclass مدل‌ها

```php
namespace App\Models;

class Product extends \Karnoweb\Shop\Models\Product
{
    // Media, Inventory, BooleanEnum, Activity log, …
}
```

سپس env/config را به همین کلاس‌ها اشاره دهید.

## bind قراردادها

```php
use Karnoweb\Shop\Contracts\CampaignPriceAdjuster;
use Karnoweb\Shop\Contracts\StorefrontContext;

$this->app->singleton(CampaignPriceAdjuster::class, CampaignPriceAdjusterBridge::class);
$this->app->singleton(StorefrontContext::class, HostStorefrontContext::class);
```

## morph و OrderItem

Morph map پکیج کلیدهایی مثل `shop_product` ثبت می‌کند. در commerce، `OrderItem.itemable` باید به `config('shop.models.product')` وصل شود نه کلاس hard-coded پکیج.

## رویداد

```php
use Karnoweb\Shop\Events\ProductSaved;
use Karnoweb\Shop\Support\ShopEventDispatcher;

ShopEventDispatcher::dispatch(new ProductSaved(
    productId: (int) $product->id,
    productInterfaceId: (int) $product->product_interface_id,
));
```

در listener میزبان کش ویترین را باطل کنید.

## قوانین

- از import کردن `App\Models\*` داخل کد پکیج خودداری شده؛ شما هم برای توسعهٔ پکیج همان مرز را نگه دارید.
- Actions و Livewire در میزبان بمانند.

## نتیجه ذخیره‌شده

bind قراردادها و subclass فقط wiring هستند؛ مگر listener شما چیزی بنویسد.
