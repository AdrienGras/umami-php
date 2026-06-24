<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Enums;

/**
 * Account role assignable to a user.
 *
 * Mirrors `userRoleParam` of Umami v3.1.0
 * (`z.enum(['admin', 'user', 'view-only'])`, `src/lib/schema.ts`).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case ViewOnly = 'view-only';
}
