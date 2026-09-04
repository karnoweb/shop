<?php

declare(strict_types=1);

return [
    'attribute' => [
        'type' => [
            'text' => 'Text',
            'color' => 'Color',
            'select' => 'Select',
            'number' => 'Number',
            'checkbox' => 'Checkbox',
        ],
    ],
    'product_interface' => [
        'type' => [
            'simple' => 'Simple',
            'codding' => 'Variant',
        ],
        'kind' => [
            'simple' => 'Simple',
            'ingredient' => 'Ingredient',
            'composed' => 'Composed',
            'virtual' => 'Virtual',
            'bundle' => 'Bundle',
        ],
    ],
    'campaign' => [
        'type' => [
            'product_based' => 'Product-based',
            'order_based' => 'Order-based',
            'price_adjustment' => 'Price adjustment',
        ],
        'condition' => [
            'category' => 'Category',
            'brand' => 'Brand',
            'product' => 'Product',
            'user' => 'User',
            'user_group' => 'User group',
            'min_order_amount' => 'Minimum order amount',
            'min_order_count' => 'Minimum order count',
            'first_order' => 'First order',
            'date_range' => 'Date range',
        ],
    ],
    'exceptions' => [
        'invalid_price_window' => 'Price window is invalid: starts_at must be before or equal to ends_at.',
        'invalid_price_amount' => 'Price amount must be zero or greater.',
        'product_not_found' => 'Product [:id] was not found.',
        'product_interface_not_found' => 'Product interface [:id] was not found.',
    ],
];
