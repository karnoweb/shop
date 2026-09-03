# معماری پکیج

پکیج یک **دامنه کاتالوگ** است، نه فروشگاه کامل. سفارش، پرداخت، UI و Policy داخل پکیج نیستند.

## لایه‌ها

| لایه | مسیر | نقش |
|------|------|------|
| Service | `src/Services/` | قیمت، فیلتر، دادهٔ کارت محصول |
| Contract | `src/Contracts/` | پل اختیاری به کمپین و ویترین میزبان |
| Model | `src/Models/` | persistence و رابطه |
| Event | `src/Events/` + `ShopEventDispatcher` | رویداد بعد از commit |
| Enum | `src/Enums/` | نوع ویژگی، نوع محصول، کمپین |
| Support | `src/Support/` | morph map و resolve مدل‌ها |

قواعد:

1. اپ میزبان مدل‌ها را معمولاً subclass می‌کند (`App\Models\Product extends Karnoweb\Shop\Models\Product`).
2. وابستگی به User، Category، Discount فقط از `config('shop.models.*')` است — بدون import سخت به `App\Models`.
3. سفارش و کیف پول در `karnoweb/commerce` است؛ موجودی حرکتی در inventory.
4. فیلد `stock` روی Product **legacy/منسوخ** است؛ برای موجودی واقعی از پکیج انبار استفاده کنید.

## قراردادهای میزبان

| Contract | نقش |
|----------|------|
| `CampaignPriceAdjuster` | اعمال تخفیف کمپین روی قیمت پایه |
| `StorefrontContext` | wishlist / cart / compare / rating برای کارت محصول |

اگر bind نشوند، سرویس‌ها بدون آن‌ها (یا با رفتار خنثی) کار می‌کنند.

## بیرون از scope

Livewire ادمین، سبد session، درگاه پرداخت، سند حسابداری، CRM.
