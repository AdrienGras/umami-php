<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Enums\TeamRole;
use AdrienGras\Umami\Requests\Team\AddTeamUser;
use AdrienGras\Umami\Requests\Team\CreateTeam;
use AdrienGras\Umami\Requests\Team\DeleteTeam;
use AdrienGras\Umami\Requests\Team\GetTeam;
use AdrienGras\Umami\Requests\Team\GetTeamUser;
use AdrienGras\Umami\Requests\Team\JoinTeam;
use AdrienGras\Umami\Requests\Team\ListAllTeams;
use AdrienGras\Umami\Requests\Team\ListTeams;
use AdrienGras\Umami\Requests\Team\ListTeamUsers;
use AdrienGras\Umami\Requests\Team\ListTeamWebsites;
use AdrienGras\Umami\Requests\Team\RemoveTeamUser;
use AdrienGras\Umami\Requests\Team\UpdateTeam;
use AdrienGras\Umami\Requests\Team\UpdateTeamUser;
use InvalidArgumentException;

/**
 * Team façade — `…/api/teams` CRUD, the `join` flow, per-team membership
 * management and the team-owned websites listing.
 *
 * All calls require auth (the connector injects the Bearer from
 * AuthEntrypoint::login()); most member operations require team manager/owner
 * rights server-side. Responses are returned as decoded arrays. Guards live here.
 */
readonly class TeamEntrypoint extends AbstractEntrypoint
{
    /**
     * Paginated teams the current user belongs to (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function list(?int $page = null, ?int $pageSize = null): array
    {
        $query = $this->compact(['page' => $page, 'pageSize' => $pageSize]);

        return $this->asObject($this->api->send(new ListTeams($query))->json());
    }

    /**
     * Paginated list of every team (admin only). Served by `/api/admin/teams`.
     *
     * @return array<string, mixed>
     */
    public function listAll(?int $page = null, ?int $pageSize = null, ?string $search = null): array
    {
        $query = $this->compact(['page' => $page, 'pageSize' => $pageSize, 'search' => $search]);

        return $this->asObject($this->api->send(new ListAllTeams($query))->json());
    }

    /**
     * Single team with its members.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->asObject($this->api->send(new GetTeam($this->teamId($id)))->json());
    }

    /**
     * Create a team. `name` (≤50) is required; `ownerId` defaults to the caller.
     *
     * @return array<string, mixed>
     */
    public function create(string $name, ?string $ownerId = null): array
    {
        $payload = $this->compact([
            'name' => $this->name($name),
            'ownerId' => $ownerId,
        ]);

        $decoded = $this->api->send(new CreateTeam($payload))->json();

        // Live quirk: POST /api/teams returns a [team, ownerMembership] tuple
        // (the Prisma transaction leaks both rows). Unwrap to the team object.
        if (is_array($decoded) && array_is_list($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
            $decoded = $decoded[0];
        }

        return $this->asObject($decoded);
    }

    /**
     * Update a team. All fields optional; only provided ones are sent.
     *
     * @return array<string, mixed>
     */
    public function update(string $id, ?string $name = null, ?string $accessCode = null): array
    {
        $id = $this->teamId($id);

        $payload = $this->compact([
            'name' => null === $name ? null : $this->name($name),
            'accessCode' => null === $accessCode ? null : $this->accessCode($accessCode),
        ]);

        return $this->asObject($this->api->send(new UpdateTeam($id, $payload))->json());
    }

    /**
     * Delete a team. Returns nothing.
     */
    public function delete(string $id): void
    {
        $this->api->send(new DeleteTeam($this->teamId($id)));
    }

    /**
     * Join a team using its access code. The caller becomes a `team-member`.
     *
     * @return array<string, mixed>
     */
    public function join(string $accessCode): array
    {
        $payload = ['accessCode' => $this->accessCode($accessCode)];

        return $this->asObject($this->api->send(new JoinTeam($payload))->json());
    }

    /**
     * Paginated members of a team (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function members(string $id, ?int $page = null, ?int $pageSize = null, ?string $search = null): array
    {
        $query = $this->compact(['page' => $page, 'pageSize' => $pageSize, 'search' => $search]);

        return $this->asObject($this->api->send(new ListTeamUsers($this->teamId($id), $query))->json());
    }

    /**
     * Single membership (the user's role within the team).
     *
     * @return array<string, mixed>
     */
    public function member(string $id, string $userId): array
    {
        return $this->asObject(
            $this->api->send(new GetTeamUser($this->teamId($id), $this->userId($userId)))->json(),
        );
    }

    /**
     * Add a member to a team with the given role.
     *
     * @return array<string, mixed>
     */
    public function addMember(string $id, string $userId, TeamRole $role): array
    {
        $payload = ['userId' => $this->userId($userId), 'role' => $role->value];

        return $this->asObject($this->api->send(new AddTeamUser($this->teamId($id), $payload))->json());
    }

    /**
     * Change an existing member's role.
     *
     * @return array<string, mixed>
     */
    public function updateMember(string $id, string $userId, TeamRole $role): array
    {
        $payload = ['role' => $role->value];

        return $this->asObject(
            $this->api->send(new UpdateTeamUser($this->teamId($id), $this->userId($userId), $payload))->json(),
        );
    }

    /**
     * Remove a member from a team. Returns nothing.
     */
    public function removeMember(string $id, string $userId): void
    {
        $this->api->send(new RemoveTeamUser($this->teamId($id), $this->userId($userId)));
    }

    /**
     * Paginated websites owned by a team (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function websites(string $id, ?int $page = null, ?int $pageSize = null, ?string $search = null): array
    {
        $query = $this->compact(['page' => $page, 'pageSize' => $pageSize, 'search' => $search]);

        return $this->asObject($this->api->send(new ListTeamWebsites($this->teamId($id), $query))->json());
    }

    private function teamId(string $id): string
    {
        return $this->nonEmpty($id, 'id');
    }

    private function userId(string $userId): string
    {
        return $this->nonEmpty($userId, 'userId');
    }

    private function name(string $name): string
    {
        $name = $this->nonEmpty($name, 'name');

        if (mb_strlen($name) > 50) {
            throw new InvalidArgumentException('name must be at most 50 characters.');
        }

        return $name;
    }

    private function accessCode(string $accessCode): string
    {
        $accessCode = $this->nonEmpty($accessCode, 'accessCode');

        if (mb_strlen($accessCode) > 50) {
            throw new InvalidArgumentException('accessCode must be at most 50 characters.');
        }

        return $accessCode;
    }

    private function nonEmpty(string $value, string $field): string
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidArgumentException(\sprintf('%s must not be empty.', $field));
        }

        return $value;
    }

    /**
     * Keep only non-null entries (optionals are never sent).
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function compact(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => null !== $value);
    }
}
