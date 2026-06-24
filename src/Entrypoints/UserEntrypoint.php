<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Enums\UserRole;
use AdrienGras\Umami\Requests\User\CreateUser;
use AdrienGras\Umami\Requests\User\DeleteUser;
use AdrienGras\Umami\Requests\User\GetUser;
use AdrienGras\Umami\Requests\User\GetUserTeams;
use AdrienGras\Umami\Requests\User\GetUserWebsites;
use AdrienGras\Umami\Requests\User\ListUsers;
use AdrienGras\Umami\Requests\User\UpdateUser;
use InvalidArgumentException;

/**
 * User façade — `…/api/users` CRUD plus a paginated admin listing and the
 * per-user teams/websites sub-routes.
 *
 * All calls require auth (the connector injects the Bearer from
 * AuthEntrypoint::login()) and most require an admin account server-side.
 * Responses are returned as decoded arrays. Input guards live here.
 */
readonly class UserEntrypoint extends AbstractEntrypoint
{
    /**
     * Paginated user list (`{data, count, page, pageSize}`), admin only.
     * Served by `/api/admin/users` — `/api/users` only exposes POST.
     *
     * @return array<string, mixed>
     */
    public function list(
        ?int $page = null,
        ?int $pageSize = null,
        ?string $search = null,
    ): array {
        $query = $this->compact([
            'page' => $page,
            'pageSize' => $pageSize,
            'search' => $search,
        ]);

        return $this->asObject($this->api->send(new ListUsers($query))->json());
    }

    /**
     * Single user by id.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->asObject($this->api->send(new GetUser($this->userId($id)))->json());
    }

    /**
     * Create a user (admin only). `username` (≤255) and `password` (8–255) are
     * required; `role` is type-safe via {@see UserRole}.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $username,
        string $password,
        UserRole $role,
        ?string $id = null,
    ): array {
        $payload = $this->compact([
            'username' => $this->username($username),
            'password' => $this->password($password),
            'role' => $role->value,
            'id' => $id,
        ]);

        return $this->asObject($this->api->send(new CreateUser($payload))->json());
    }

    /**
     * Update a user. All fields optional; only provided ones are sent.
     * `username`/`role` changes are admin-only server-side.
     *
     * @return array<string, mixed>
     */
    public function update(
        string $id,
        ?string $username = null,
        ?string $password = null,
        ?UserRole $role = null,
    ): array {
        $id = $this->userId($id);

        $payload = $this->compact([
            'username' => null === $username ? null : $this->username($username),
            'password' => null === $password ? null : $this->password($password),
            'role' => $role?->value,
        ]);

        return $this->asObject($this->api->send(new UpdateUser($id, $payload))->json());
    }

    /**
     * Delete a user (admin only). Returns nothing.
     */
    public function delete(string $id): void
    {
        $this->api->send(new DeleteUser($this->userId($id)));
    }

    /**
     * Paginated teams the user belongs to (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function teams(string $id, ?int $page = null, ?int $pageSize = null): array
    {
        $query = $this->compact(['page' => $page, 'pageSize' => $pageSize]);

        return $this->asObject($this->api->send(new GetUserTeams($this->userId($id), $query))->json());
    }

    /**
     * Paginated websites owned by the user. `includeTeams` also returns the
     * websites of teams the user owns.
     *
     * @return array<string, mixed>
     */
    public function websites(
        string $id,
        ?int $page = null,
        ?int $pageSize = null,
        ?string $search = null,
        ?bool $includeTeams = null,
    ): array {
        $query = $this->compact([
            'page' => $page,
            'pageSize' => $pageSize,
            'search' => $search,
            'includeTeams' => $includeTeams,
        ]);

        return $this->asObject($this->api->send(new GetUserWebsites($this->userId($id), $query))->json());
    }

    private function userId(string $id): string
    {
        $id = trim($id);

        if ('' === $id) {
            throw new InvalidArgumentException('id must not be empty.');
        }

        return $id;
    }

    private function username(string $username): string
    {
        $username = trim($username);

        if ('' === $username) {
            throw new InvalidArgumentException('username must not be empty.');
        }

        if (mb_strlen($username) > 255) {
            throw new InvalidArgumentException('username must be at most 255 characters.');
        }

        return $username;
    }

    /**
     * Password length guard. The 8-char minimum mirrors Umami's zod schema.
     * Not trimmed — surrounding whitespace is significant in a password.
     */
    private function password(string $password): string
    {
        $length = mb_strlen($password);

        if ($length < 8) {
            throw new InvalidArgumentException('password must be at least 8 characters.');
        }

        if ($length > 255) {
            throw new InvalidArgumentException('password must be at most 255 characters.');
        }

        return $password;
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
