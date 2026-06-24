<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Enums;

/**
 * Role assignable to a team member.
 *
 * Mirrors `teamRoleParam` of Umami v3.1.0
 * (`z.enum(['team-member', 'team-view-only', 'team-manager'])`, `src/lib/schema.ts`).
 *
 * Note: `team-owner` is the implicit role of a team's creator and is NOT
 * assignable through the API — hence it is absent here on purpose.
 */
enum TeamRole: string
{
    case Member = 'team-member';
    case ViewOnly = 'team-view-only';
    case Manager = 'team-manager';
}
