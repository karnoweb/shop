<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Enums;

enum VariantsStatusEnum: string
{
    case READY = 'ready';
    case NEEDS_SYNC = 'needs_sync';
}
