# قیمت‌گذاری

قیمت نهایی یک Product از `ProductPriceResolver` می‌آید، نه لزوماً از ستون `base_price` به‌تنهایی.

## ترتیب resolve

1. قیمت فعال برای **گروه کاربری** (`user_group_id`) در بازهٔ زمانی معتبر
2. در غیر این صورت قیمت فعال برای **tier** (مثلاً `wholesale` / `retail`)
3. در غیر این صورت قیمت فعال با گروه خالی (پیش‌فرض پنجره‌ای)
4. در نهایت `base_price` روی خود Product

`ProductService::resolvePrice` ابتدا همین resolver را صدا می‌زند؛ اگر `CampaignPriceAdjuster` در میزبان bind شده باشد، می‌تواند قیمت پایه را تعدیل کند.

## ProductPrice

- بازهٔ `starts_at` / `ends_at`
- اسکوپهای `active()`، `forGroup()`، `forTier()`، `default()`
- `tier` قابل حمل بین پروژه‌هاست؛ `user_group_id` مخصوص اکوسیستم Karno است

## ارز

پیش‌فرض config: `shop.pricing.currency = IRR`.
