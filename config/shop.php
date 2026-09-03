<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Host models (soft references only — never FK-constrained in package code)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => env('SHOP_USER_MODEL', 'App\\Models\\User'),
        'user_group' => env('SHOP_USER_GROUP_MODEL', 'App\\Models\\UserGroup'),
        'category' => env('SHOP_CATEGORY_MODEL', 'App\\Models\\Category'),
        'address' => env('SHOP_ADDRESS_MODEL', 'App\\Models\\Address'),
        'product' => env('SHOP_PRODUCT_MODEL', 'App\\Models\\Product'),
        'product_interface' => env('SHOP_PRODUCT_INTERFACE_MODEL', 'App\\Models\\ProductInterface'),
        'product_price' => env('SHOP_PRODUCT_PRICE_MODEL', 'App\\Models\\ProductPrice'),
        'brand' => env('SHOP_BRAND_MODEL', 'App\\Models\\Brand'),
        'attribute' => env('SHOP_ATTRIBUTE_MODEL', 'App\\Models\\Attribute'),
        'attribute_group' => env('SHOP_ATTRIBUTE_GROUP_MODEL', 'App\\Models\\AttributeGroup'),
        'attribute_value' => env('SHOP_ATTRIBUTE_VALUE_MODEL', 'App\\Models\\AttributeValue'),
        'wishlist' => env('SHOP_WISHLIST_MODEL', 'App\\Models\\WishList'),
        'campaign' => env('SHOP_CAMPAIGN_MODEL', 'App\\Models\\Campaign'),
        'discount' => env('SHOP_DISCOUNT_MODEL', 'App\\Models\\Discount'),
        'order_item' => env('SHOP_ORDER_ITEM_MODEL', 'App\\Models\\OrderItem'),
        'comment' => env('SHOP_COMMENT_MODEL', 'App\\Models\\Comment'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key type strategy
    |--------------------------------------------------------------------------
    */
    'keys' => [
        'user_key_type' => env('SHOP_USER_KEY_TYPE', 'int'),
        'branch_key_type' => env('SHOP_BRANCH_KEY_TYPE', 'int'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    |
    | Existing Karno deployments use unprefixed catalog tables (brands, products).
    | Default empty prefix preserves table names during extraction.
    |
    */
    'tables' => [
        'prefix' => env('SHOP_TABLE_PREFIX', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph map aliases (shop-owned sellables)
    |--------------------------------------------------------------------------
    */
    'morph_map' => [
        'shop_product' => env('SHOP_PRODUCT_MODEL', 'App\\Models\\Product'),
        'shop_product_interface' => env('SHOP_PRODUCT_INTERFACE_MODEL', 'App\\Models\\ProductInterface'),
        'shop_brand' => env('SHOP_BRAND_MODEL', 'App\\Models\\Brand'),
        'shop_campaign' => env('SHOP_CAMPAIGN_MODEL', 'App\\Models\\Campaign'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing defaults
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        'currency' => env('SHOP_CURRENCY', 'IRR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filter / published helpers
    |--------------------------------------------------------------------------
    |
    | Host BooleanEnum::ENABLE value is 1. Category product type string is "product".
    |
    */
    'published_enabled_value' => 1,
    'category_product_type' => 'product',
];
