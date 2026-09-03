<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Contracts;

/**
 * Optional host bridge for campaign-based catalog price adjustments.
 */
interface CampaignPriceAdjuster
{
    /**
     * @param object      $product Catalog product (host or package model)
     * @param object|null $user    Authenticated user or null
     *
     * @return array{base_price: int, final_price: int, has_discount: bool, discount_percent: mixed}|null
     *                                                                                                    Null means "no campaign adjustment — use base price only".
     */
    public function adjust(object $product, ?object $user, int $basePrice): ?array;
}
