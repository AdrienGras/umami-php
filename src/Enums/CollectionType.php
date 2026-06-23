<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Enums;

/**
 * The `type` discriminator of a tracking hit (`/api/send`).
 *
 * Mirrors COLLECTION_TYPE (lib/constants.ts). Verified against v3.1.0 source.
 */
enum CollectionType: string
{
    case Event = 'event';
    case Identify = 'identify';
    case Performance = 'performance';
}
