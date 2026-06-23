# Websites CRUD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer le domaine Websites de la lib `umami-php` — `$umami->websites` : CRUD (`list/get/create/update/delete`) + sous-routes `reset/transfer/daterange/values`.

**Architecture:** Transport-only Saloon v4 (cf. `docs/SALOON_LIBRARY_DESIGN.md`). Une `Request` stupide par appel HTTP, gardes + transformations dans `WebsiteEntrypoint`, sorties en array décodé via `AbstractEntrypoint::asObject()`/`asList()`. Cohérent avec Tracking/Auth/Stats déjà livrés. Bloc imbriqué `replayConfig` → value object readonly `ReplayConfig` + enum backed `MaskLevel`. `values` réutilise le `Period` existant.

**Tech Stack:** PHP 8.2+ (runtime hôte 8.5.7), Saloon ^4.0, PHPUnit 11, phpstan max, php-cs-fixer.

## Global Constraints

- **Langue** : docs/commits en français ; code + phpdoc publique en **anglais** (Packagist).
- `declare(strict_types=1);` en tête de **chaque** fichier PHP.
- **Propriétés de Request réservées** : jamais `$body`/`$query`/`$headers`/`$config`. Body → `$payload`, query → `$queryParams` (fatal sinon, cf. QUIRKS).
- **Optionnels nuls omis** du body/query (pas de `null` envoyé).
- **Gardes d'entrée** dans l'Entrypoint, avant tout I/O, levant `\InvalidArgumentException` ; `trim()` avant validation ET avant envoi.
- **TDD strict** : RED (test échoue) vu avant chaque GREEN.
- **Porte de validation** `bash scripts/check.sh` **verte** avant chaque commit (règle d'or 8) : composer validate/audit + php-cs-fixer + phpstan + phpunit unit. Aucun commit si un seul échoue.
- **Commits gitmoji** (✨ feature, ✅ tests, ♻️ refactor…).
- Mock Saloon v4 : `MockResponse::make($body, $status)` ; `$api->withMockClient(new MockClient([$mock]))` ; inspecter la requête résolue via `$response->getPendingRequest()->body()->all()` / `->query()->all()` / `->headers()->all()`. Pour capturer une requête sortante sans vraie réponse : `new MockClient([fn(\Saloon\Http\PendingRequest $r) => MockResponse::make([], 200)])` puis lire `$r`.
- Tests d'intégration **hors** porte (docker requis) : lancés séparément.

---

### Task 1: Enum `MaskLevel` + value object `ReplayConfig`

Le bloc imbriqué `replayConfig` de `update`. VO readonly à named args, `toArray()` omet les nuls (miroir de `Tracking/Payload`). `maskLevel` typé par une enum backed (miroir de `MetricType`/`CollectionType`).

**Files:**
- Create: `src/Enums/MaskLevel.php`
- Create: `src/Website/ReplayConfig.php`
- Test: `tests/Unit/Website/ReplayConfigTest.php`

**Interfaces:**
- Produces:
  - `enum MaskLevel: string { case Strict = 'strict'; case Moderate = 'moderate'; }`
  - `final readonly class ReplayConfig` — ctor `__construct(?float $sampleRate = null, ?MaskLevel $maskLevel = null, ?int $maxDuration = null, ?string $blockSelector = null)` ; méthode `toArray(): array<string,mixed>` (omet les nuls ; `maskLevel` sérialisé en `->value`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Website;

use AdrienGras\Umami\Enums\MaskLevel;
use AdrienGras\Umami\Website\ReplayConfig;
use PHPUnit\Framework\TestCase;

final class ReplayConfigTest extends TestCase
{
    public function testToArrayOmitsNulls(): void
    {
        $config = new ReplayConfig(sampleRate: 0.5, maskLevel: MaskLevel::Strict);

        self::assertSame(
            ['sampleRate' => 0.5, 'maskLevel' => 'strict'],
            $config->toArray(),
        );
    }

    public function testToArrayFullPayload(): void
    {
        $config = new ReplayConfig(
            sampleRate: 1.0,
            maskLevel: MaskLevel::Moderate,
            maxDuration: 3600,
            blockSelector: '.private',
        );

        self::assertSame(
            [
                'sampleRate' => 1.0,
                'maskLevel' => 'moderate',
                'maxDuration' => 3600,
                'blockSelector' => '.private',
            ],
            $config->toArray(),
        );
    }

    public function testToArrayEmptyWhenAllNull(): void
    {
        self::assertSame([], (new ReplayConfig())->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Website/ReplayConfigTest.php`
Expected: FAIL (`Class "AdrienGras\Umami\Enums\MaskLevel" not found` ou `ReplayConfig` not found).

- [ ] **Step 3: Write the enum**

`src/Enums/MaskLevel.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Enums;

/**
 * Session replay masking level (website replayConfig).
 *
 * Mirrors the `maskLevel` enum of Umami v3.1.0
 * (`websites/[websiteId]/route.ts` replayConfig schema).
 */
enum MaskLevel: string
{
    case Strict = 'strict';
    case Moderate = 'moderate';
}
```

- [ ] **Step 4: Write the value object**

`src/Website/ReplayConfig.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Website;

use AdrienGras\Umami\Enums\MaskLevel;

/**
 * Session replay configuration for a website update.
 *
 * Input value object only — no guards live here (transport-only pattern).
 * {@see toArray()} omits null fields so optional values are never sent.
 */
final readonly class ReplayConfig
{
    public function __construct(
        public ?float $sampleRate = null,
        public ?MaskLevel $maskLevel = null,
        public ?int $maxDuration = null,
        public ?string $blockSelector = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [];

        if (null !== $this->sampleRate) {
            $config['sampleRate'] = $this->sampleRate;
        }

        if (null !== $this->maskLevel) {
            $config['maskLevel'] = $this->maskLevel->value;
        }

        if (null !== $this->maxDuration) {
            $config['maxDuration'] = $this->maxDuration;
        }

        if (null !== $this->blockSelector) {
            $config['blockSelector'] = $this->blockSelector;
        }

        return $config;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Website/ReplayConfigTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Gate + commit**

```bash
bash scripts/check.sh
git add src/Enums/MaskLevel.php src/Website/ReplayConfig.php tests/Unit/Website/ReplayConfigTest.php
git commit -m "✨ Websites : enum MaskLevel + value object ReplayConfig (étape 7.4)"
```
Expected: check.sh vert, commit créé.

---

### Task 2: Requests Websites (les 9 classes)

Les 9 `Request` Saloon, descriptives et autonomes (pas de base partagée — formes hétérogènes). Body → `$payload`, query → `$queryParams`. Toutes Bearer (pas de `SkipsAuth`). Testées ensemble par un test de structure léger ; le golden body/query exact est couvert via l'Entrypoint en Task 3+ (l'Entrypoint construit les payloads).

**Files:**
- Create: `src/Requests/Website/ListWebsites.php`
- Create: `src/Requests/Website/GetWebsite.php`
- Create: `src/Requests/Website/CreateWebsite.php`
- Create: `src/Requests/Website/UpdateWebsite.php`
- Create: `src/Requests/Website/DeleteWebsite.php`
- Create: `src/Requests/Website/ResetWebsite.php`
- Create: `src/Requests/Website/TransferWebsite.php`
- Create: `src/Requests/Website/GetWebsiteDateRange.php`
- Create: `src/Requests/Website/GetWebsiteValues.php`
- Test: `tests/Unit/Website/WebsiteRequestsTest.php`

**Interfaces:**
- Consumes: rien (transport pur).
- Produces (ctors + endpoints) :
  - `ListWebsites(array $queryParams = [])` → GET `/api/websites`, `defaultQuery()`.
  - `GetWebsite(string $websiteId)` → GET `/api/websites/{id}`.
  - `CreateWebsite(array $payload)` → POST `/api/websites`, `HasBody`, `defaultBody()`.
  - `UpdateWebsite(string $websiteId, array $payload)` → POST `/api/websites/{id}`, `HasBody`.
  - `DeleteWebsite(string $websiteId)` → DELETE `/api/websites/{id}`.
  - `ResetWebsite(string $websiteId)` → POST `/api/websites/{id}/reset`.
  - `TransferWebsite(string $websiteId, array $payload)` → POST `/api/websites/{id}/transfer`, `HasBody`.
  - `GetWebsiteDateRange(string $websiteId)` → GET `/api/websites/{id}/daterange`.
  - `GetWebsiteValues(string $websiteId, array $queryParams = [])` → GET `/api/websites/{id}/values`, `defaultQuery()`.
  - Tous : `array<string,mixed>` pour `$payload`/`$queryParams`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Website;

use AdrienGras\Umami\Requests\Website\CreateWebsite;
use AdrienGras\Umami\Requests\Website\DeleteWebsite;
use AdrienGras\Umami\Requests\Website\GetWebsite;
use AdrienGras\Umami\Requests\Website\GetWebsiteDateRange;
use AdrienGras\Umami\Requests\Website\GetWebsiteValues;
use AdrienGras\Umami\Requests\Website\ListWebsites;
use AdrienGras\Umami\Requests\Website\ResetWebsite;
use AdrienGras\Umami\Requests\Website\TransferWebsite;
use AdrienGras\Umami\Requests\Website\UpdateWebsite;
use PHPUnit\Framework\TestCase;
use Saloon\Enums\Method;

final class WebsiteRequestsTest extends TestCase
{
    public function testEndpointsAndMethods(): void
    {
        self::assertSame('/api/websites', (new ListWebsites())->resolveEndpoint());
        self::assertSame(Method::GET, $this->method(new ListWebsites()));

        self::assertSame('/api/websites/abc', (new GetWebsite('abc'))->resolveEndpoint());

        self::assertSame('/api/websites', (new CreateWebsite(['name' => 'x']))->resolveEndpoint());
        self::assertSame(Method::POST, $this->method(new CreateWebsite(['name' => 'x'])));

        self::assertSame('/api/websites/abc', (new UpdateWebsite('abc', ['name' => 'y']))->resolveEndpoint());
        self::assertSame('/api/websites/abc', (new DeleteWebsite('abc'))->resolveEndpoint());
        self::assertSame(Method::DELETE, $this->method(new DeleteWebsite('abc')));

        self::assertSame('/api/websites/abc/reset', (new ResetWebsite('abc'))->resolveEndpoint());
        self::assertSame('/api/websites/abc/transfer', (new TransferWebsite('abc', ['userId' => 'u']))->resolveEndpoint());
        self::assertSame('/api/websites/abc/daterange', (new GetWebsiteDateRange('abc'))->resolveEndpoint());
        self::assertSame('/api/websites/abc/values', (new GetWebsiteValues('abc'))->resolveEndpoint());
    }

    private function method(\Saloon\Http\Request $request): Method
    {
        $reflection = new \ReflectionProperty($request, 'method');

        return $reflection->getValue($request);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Website/WebsiteRequestsTest.php`
Expected: FAIL (classes Request introuvables).

- [ ] **Step 3: Write the GET requests**

`src/Requests/Website/ListWebsites.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites` — paginated website list (Bearer). */
class ListWebsites extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly array $queryParams = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/websites';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
```

`src/Requests/Website/GetWebsite.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites/{id}` — single website (Bearer). */
class GetWebsite extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}";
    }
}
```

`src/Requests/Website/GetWebsiteDateRange.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites/{id}/daterange` — data date span (Bearer). */
class GetWebsiteDateRange extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/daterange";
    }
}
```

`src/Requests/Website/GetWebsiteValues.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites/{id}/values` — distinct values for a field (Bearer). */
class GetWebsiteValues extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly string $websiteId,
        protected readonly array $queryParams = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/values";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
```

- [ ] **Step 4: Write the POST/DELETE requests**

`src/Requests/Website/CreateWebsite.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** `POST /api/websites` — create a website (Bearer). */
class CreateWebsite extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/websites';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
```

`src/Requests/Website/UpdateWebsite.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** `POST /api/websites/{id}` — update a website (Bearer). */
class UpdateWebsite extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $websiteId,
        private readonly array $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
```

`src/Requests/Website/DeleteWebsite.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `DELETE /api/websites/{id}` — delete a website (Bearer). */
class DeleteWebsite extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}";
    }
}
```

`src/Requests/Website/ResetWebsite.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `POST /api/websites/{id}/reset` — wipe analytics data (Bearer). */
class ResetWebsite extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/reset";
    }
}
```

`src/Requests/Website/TransferWebsite.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** `POST /api/websites/{id}/transfer` — transfer ownership (Bearer). */
class TransferWebsite extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $websiteId,
        private readonly array $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/transfer";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Website/WebsiteRequestsTest.php`
Expected: PASS.

- [ ] **Step 6: Gate + commit**

```bash
bash scripts/check.sh
git add src/Requests/Website tests/Unit/Website/WebsiteRequestsTest.php
git commit -m "✨ Websites : 9 Requests Saloon (list/get/create/update/delete/reset/transfer/daterange/values)"
```
Expected: check.sh vert, commit créé.

---

### Task 3: `WebsiteEntrypoint` — CRUD (list/get/create/update/delete) + branchement Connector

La façade pour le CRUD de base, branchée `$umami->websites`. Gardes + construction des payloads ici. `update` sérialise `ReplayConfig` via `toArray()`. Les sous-routes (reset/transfer/daterange/values) arrivent en Task 4.

**Files:**
- Create: `src/Entrypoints/WebsiteEntrypoint.php`
- Modify: `src/UmamiApi.php` (ajouter `public readonly WebsiteEntrypoint $websites;` + init)
- Test: `tests/Unit/Website/WebsiteEntrypointTest.php`

**Interfaces:**
- Consumes: `AbstractEntrypoint::asObject()` ; `ReplayConfig::toArray()` ; Requests `ListWebsites`, `GetWebsite`, `CreateWebsite`, `UpdateWebsite`, `DeleteWebsite` ; `UmamiApi::send()`.
- Produces (méthodes Task 4 viendront s'ajouter à cette même classe) :
  - `list(?int $page = null, ?int $pageSize = null, ?string $search = null, ?bool $includeTeams = null): array<string,mixed>`
  - `get(string $id): array<string,mixed>`
  - `create(string $name, string $domain, ?string $shareId = null, ?string $teamId = null, ?string $id = null): array<string,mixed>`
  - `update(string $id, ?string $name = null, ?string $domain = null, ?string $shareId = null, ?bool $replayEnabled = null, ?ReplayConfig $replayConfig = null): array<string,mixed>`
  - `delete(string $id): void`
  - private `websiteId(string $id): string` (trim + non-vide guard) — réutilisé par Task 4.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Website;

use AdrienGras\Umami\Enums\MaskLevel;
use AdrienGras\Umami\UmamiApi;
use AdrienGras\Umami\Website\ReplayConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class WebsiteEntrypointTest extends TestCase
{
    /** @return array{0: UmamiApi, 1: array<int, PendingRequest>} */
    private function apiCapturing(array $responseBody = []): array
    {
        $captured = [];
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: 'tok');
        $api->withMockClient(new MockClient([
            function (PendingRequest $request) use (&$captured, $responseBody): MockResponse {
                $captured[] = $request;

                return MockResponse::make($responseBody, 200);
            },
        ]));

        return [$api, &$captured];
    }

    public function testCreateBuildsBodyOmittingNulls(): void
    {
        [$api, $captured] = $this->apiCapturing(['id' => 'w1', 'name' => 'Site']);

        $result = $api->websites->create(name: 'Site', domain: 'example.com');

        self::assertSame('w1', $result['id']);
        self::assertSame(['name' => 'Site', 'domain' => 'example.com'], $captured[0]->body()->all());
        self::assertSame('/api/websites', $captured[0]->getRequest()->resolveEndpoint());
    }

    public function testCreateIncludesOptionalFields(): void
    {
        [$api, $captured] = $this->apiCapturing();

        $api->websites->create(name: 'S', domain: 'd.com', shareId: 'sh', teamId: 't1', id: 'fixed');

        self::assertSame(
            ['name' => 'S', 'domain' => 'd.com', 'shareId' => 'sh', 'teamId' => 't1', 'id' => 'fixed'],
            $captured[0]->body()->all(),
        );
    }

    public function testCreateRejectsEmptyName(): void
    {
        [$api] = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->create(name: '  ', domain: 'd.com');
    }

    public function testCreateRejectsTooLongDomain(): void
    {
        [$api] = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->create(name: 'S', domain: str_repeat('a', 501));
    }

    public function testUpdateSerializesReplayConfig(): void
    {
        [$api, $captured] = $this->apiCapturing();

        $api->websites->update(
            id: 'w1',
            name: 'New',
            replayEnabled: true,
            replayConfig: new ReplayConfig(sampleRate: 0.5, maskLevel: MaskLevel::Strict),
        );

        self::assertSame(
            [
                'name' => 'New',
                'replayEnabled' => true,
                'replayConfig' => ['sampleRate' => 0.5, 'maskLevel' => 'strict'],
            ],
            $captured[0]->body()->all(),
        );
        self::assertSame('/api/websites/w1', $captured[0]->getRequest()->resolveEndpoint());
    }

    public function testListBuildsQueryOmittingNulls(): void
    {
        [$api, $captured] = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->websites->list(page: 2, search: 'foo');

        self::assertSame(['page' => 2, 'search' => 'foo'], $captured[0]->query()->all());
    }

    public function testGetRejectsEmptyId(): void
    {
        [$api] = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->get('  ');
    }

    public function testDeleteReturnsVoidAndHitsEndpoint(): void
    {
        [$api, $captured] = $this->apiCapturing(['ok' => true]);

        $api->websites->delete('w1');

        self::assertSame('/api/websites/w1', $captured[0]->getRequest()->resolveEndpoint());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Website/WebsiteEntrypointTest.php`
Expected: FAIL (`UmamiApi::$websites` introuvable / `WebsiteEntrypoint` not found).

- [ ] **Step 3: Write the entrypoint (CRUD)**

`src/Entrypoints/WebsiteEntrypoint.php` :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Requests\Website\CreateWebsite;
use AdrienGras\Umami\Requests\Website\DeleteWebsite;
use AdrienGras\Umami\Requests\Website\GetWebsite;
use AdrienGras\Umami\Requests\Website\ListWebsites;
use AdrienGras\Umami\Requests\Website\UpdateWebsite;
use AdrienGras\Umami\Website\ReplayConfig;
use InvalidArgumentException;

/**
 * Website façade — `…/api/websites` CRUD and sub-routes.
 *
 * All calls require auth; the connector injects the Bearer obtained via
 * AuthEntrypoint::login(). Responses are returned as decoded arrays
 * (shapes documented in docs/API_UMAMI.md §4.2). Input guards live here.
 */
readonly class WebsiteEntrypoint extends AbstractEntrypoint
{
    /**
     * Paginated website list (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function list(
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

        return $this->asObject($this->api->send(new ListWebsites($query))->json());
    }

    /**
     * Single website by id.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->asObject($this->api->send(new GetWebsite($this->websiteId($id)))->json());
    }

    /**
     * Create a website. `name` (≤100) and `domain` (≤500) are required.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $domain,
        ?string $shareId = null,
        ?string $teamId = null,
        ?string $id = null,
    ): array {
        $name = $this->nonEmpty($name, 'name');
        $domain = $this->nonEmpty($domain, 'domain');

        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('create() name must be at most 100 characters.');
        }

        if (mb_strlen($domain) > 500) {
            throw new InvalidArgumentException('create() domain must be at most 500 characters.');
        }

        $payload = $this->compact([
            'name' => $name,
            'domain' => $domain,
            'shareId' => $shareId,
            'teamId' => $teamId,
            'id' => $id,
        ]);

        return $this->asObject($this->api->send(new CreateWebsite($payload))->json());
    }

    /**
     * Update a website. All fields optional; only provided ones are sent.
     *
     * @return array<string, mixed>
     */
    public function update(
        string $id,
        ?string $name = null,
        ?string $domain = null,
        ?string $shareId = null,
        ?bool $replayEnabled = null,
        ?ReplayConfig $replayConfig = null,
    ): array {
        $payload = $this->compact([
            'name' => $name,
            'domain' => $domain,
            'shareId' => $shareId,
            'replayEnabled' => $replayEnabled,
            'replayConfig' => null === $replayConfig ? null : $replayConfig->toArray(),
        ]);

        return $this->asObject($this->api->send(new UpdateWebsite($this->websiteId($id), $payload))->json());
    }

    /**
     * Delete a website. Returns nothing.
     */
    public function delete(string $id): void
    {
        $this->api->send(new DeleteWebsite($this->websiteId($id)));
    }

    private function websiteId(string $id): string
    {
        return $this->nonEmpty($id, 'id');
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
```

- [ ] **Step 4: Wire the entrypoint on the connector**

Dans `src/UmamiApi.php`, après la déclaration `public readonly StatsEntrypoint $stats;` ajouter :

```php
    /** Website façade: CRUD + reset/transfer/daterange/values. */
    public readonly WebsiteEntrypoint $websites;
```

Ajouter l'import en tête (groupe `use AdrienGras\Umami\Entrypoints\…`) :

```php
use AdrienGras\Umami\Entrypoints\WebsiteEntrypoint;
```

Dans le constructeur, après `$this->stats = new StatsEntrypoint($this);` ajouter :

```php
        $this->websites = new WebsiteEntrypoint($this);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Website/WebsiteEntrypointTest.php`
Expected: PASS (8 tests).

- [ ] **Step 6: Gate + commit**

```bash
bash scripts/check.sh
git add src/Entrypoints/WebsiteEntrypoint.php src/UmamiApi.php tests/Unit/Website/WebsiteEntrypointTest.php
git commit -m "✨ Websites : WebsiteEntrypoint CRUD (list/get/create/update/delete) + branchement \$umami->websites"
```
Expected: check.sh vert, commit créé.

---

### Task 4: `WebsiteEntrypoint` — sous-routes (reset/transfer/daterange/values)

Ajoute les 4 sous-routes à la classe existante. `transfer` impose **exactement un** de `userId`/`teamId`. `values` réutilise `Period` (déjà livré) + `type` string non-vide.

**Files:**
- Modify: `src/Entrypoints/WebsiteEntrypoint.php` (ajouter 4 méthodes + imports)
- Modify: `tests/Unit/Website/WebsiteEntrypointTest.php` (ajouter les tests)

**Interfaces:**
- Consumes: `AbstractEntrypoint::asObject()`/`asList()` ; `Period::toQuery()` ; Requests `ResetWebsite`, `TransferWebsite`, `GetWebsiteDateRange`, `GetWebsiteValues` ; private `websiteId()`/`nonEmpty()`/`compact()` de Task 3.
- Produces:
  - `reset(string $id): void`
  - `transfer(string $id, ?string $userId = null, ?string $teamId = null): array<string,mixed>`
  - `dateRange(string $id): array<string,mixed>`
  - `values(string $id, string $type, Period $period, ?string $search = null): list<array<string,mixed>>`

- [ ] **Step 1: Write the failing tests (append to WebsiteEntrypointTest)**

Ajouter ces méthodes dans la classe `WebsiteEntrypointTest` (et l'import `use AdrienGras\Umami\Stats\Period;` en tête) :

```php
    public function testResetHitsEndpoint(): void
    {
        [$api, $captured] = $this->apiCapturing(['ok' => true]);

        $api->websites->reset('w1');

        self::assertSame('/api/websites/w1/reset', $captured[0]->getRequest()->resolveEndpoint());
    }

    public function testTransferToUserBuildsBody(): void
    {
        [$api, $captured] = $this->apiCapturing(['id' => 'w1']);

        $api->websites->transfer('w1', userId: 'u1');

        self::assertSame(['userId' => 'u1'], $captured[0]->body()->all());
        self::assertSame('/api/websites/w1/transfer', $captured[0]->getRequest()->resolveEndpoint());
    }

    public function testTransferToTeamBuildsBody(): void
    {
        [$api, $captured] = $this->apiCapturing(['id' => 'w1']);

        $api->websites->transfer('w1', teamId: 't1');

        self::assertSame(['teamId' => 't1'], $captured[0]->body()->all());
    }

    public function testTransferRejectsNoTarget(): void
    {
        [$api] = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->transfer('w1');
    }

    public function testTransferRejectsBothTargets(): void
    {
        [$api] = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->transfer('w1', userId: 'u1', teamId: 't1');
    }

    public function testDateRangeReturnsObject(): void
    {
        [$api, $captured] = $this->apiCapturing(['mindate' => '2026-01-01', 'maxdate' => '2026-06-01']);

        $result = $api->websites->dateRange('w1');

        self::assertSame('2026-01-01', $result['mindate']);
        self::assertSame('/api/websites/w1/daterange', $captured[0]->getRequest()->resolveEndpoint());
    }

    public function testValuesBuildsQueryAndReturnsList(): void
    {
        [$api, $captured] = $this->apiCapturing([['value' => '/home'], ['value' => '/about']]);

        $result = $api->websites->values('w1', 'path', Period::between(1000, 2000), search: 'ho');

        self::assertSame([['value' => '/home'], ['value' => '/about']], $result);
        $query = $captured[0]->query()->all();
        self::assertSame('path', $query['type']);
        self::assertSame('ho', $query['search']);
        self::assertSame(1000, $query['startAt']);
        self::assertSame(2000, $query['endAt']);
        self::assertSame('/api/websites/w1/values', $captured[0]->getRequest()->resolveEndpoint());
    }

    public function testValuesRejectsEmptyType(): void
    {
        [$api] = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->values('w1', '  ', Period::between(1000, 2000));
    }
```

> Note : vérifier la signature exacte de `Period::between()` (epoch ms) dans `src/Stats/Period.php` avant d'écrire — adapter `startAt`/`endAt` aux clés réellement produites par `Period::toQuery()` (lire le fichier ; ajuster les asserts si les clés diffèrent).

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Website/WebsiteEntrypointTest.php`
Expected: FAIL (méthodes `reset`/`transfer`/`dateRange`/`values` introuvables).

- [ ] **Step 3: Add the sub-route methods**

Ajouter dans `src/Entrypoints/WebsiteEntrypoint.php` les imports manquants en tête :

```php
use AdrienGras\Umami\Requests\Website\GetWebsiteDateRange;
use AdrienGras\Umami\Requests\Website\GetWebsiteValues;
use AdrienGras\Umami\Requests\Website\ResetWebsite;
use AdrienGras\Umami\Requests\Website\TransferWebsite;
use AdrienGras\Umami\Stats\Period;
```

Et ces méthodes publiques (avant les méthodes privées `websiteId`/`nonEmpty`/`compact`) :

```php
    /**
     * Reset (wipe) a website's analytics data. Returns nothing.
     */
    public function reset(string $id): void
    {
        $this->api->send(new ResetWebsite($this->websiteId($id)));
    }

    /**
     * Transfer a website to a user OR a team — exactly one is required.
     *
     * @return array<string, mixed>
     */
    public function transfer(string $id, ?string $userId = null, ?string $teamId = null): array
    {
        $userId = null === $userId ? null : trim($userId);
        $teamId = null === $teamId ? null : trim($teamId);

        $hasUser = null !== $userId && '' !== $userId;
        $hasTeam = null !== $teamId && '' !== $teamId;

        if ($hasUser === $hasTeam) {
            throw new InvalidArgumentException('transfer() requires exactly one of userId or teamId.');
        }

        $payload = $hasUser ? ['userId' => $userId] : ['teamId' => $teamId];

        return $this->asObject($this->api->send(new TransferWebsite($this->websiteId($id), $payload))->json());
    }

    /**
     * Date span of the website's data (`{mindate, maxdate}`).
     *
     * @return array<string, mixed>
     */
    public function dateRange(string $id): array
    {
        return $this->asObject($this->api->send(new GetWebsiteDateRange($this->websiteId($id)))->json());
    }

    /**
     * Distinct values for a field (`type` ∈ EVENT_COLUMNS ∪ SESSION_COLUMNS),
     * over a period. Returns a list of `{value}` entries.
     *
     * @return list<array<string, mixed>>
     */
    public function values(string $id, string $type, Period $period, ?string $search = null): array
    {
        $type = $this->nonEmpty($type, 'type');

        $query = $this->compact(array_merge(
            $period->toQuery(),
            ['type' => $type, 'search' => $search],
        ));

        return $this->asList($this->api->send(new GetWebsiteValues($this->websiteId($id), $query))->json());
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Website/WebsiteEntrypointTest.php`
Expected: PASS (16 tests au total).

- [ ] **Step 5: Gate + commit**

```bash
bash scripts/check.sh
git add src/Entrypoints/WebsiteEntrypoint.php tests/Unit/Website/WebsiteEntrypointTest.php
git commit -m "✨ Websites : sous-routes reset/transfer/daterange/values (transfer = exactly-one)"
```
Expected: check.sh vert, commit créé.

---

### Task 5: Tests d'intégration (docker) + mémoire

Valide le domaine contre l'instance docker réelle (cycle CRUD complet + sous-routes), lève les `⚠ à vérifier (live)`, met à jour la mémoire projet. **Hors porte** (docker requis) — lancé séparément.

**Files:**
- Create: `tests/Integration/Website/WebsiteIntegrationTest.php`
- Modify: `docs/INDEX.md`, `docs/HANDOFF.md`, `docs/API_UMAMI.md`, `docs/BACKLOG.md`
- (lire d'abord `tests/Integration/IntegrationTestCase.php` pour le harnais existant : chargement `.env.test`, skip si absent, login.)

**Interfaces:**
- Consumes: `IntegrationTestCase` (login + `.env.test` : `UMAMI_TEST_WEBSITE_ID`, etc.), `$umami->websites`, `Period`.

- [ ] **Step 1: Read the existing integration harness**

Run: `cat tests/Integration/IntegrationTestCase.php tests/Integration/Stats/StatsIntegrationTest.php`
But: réutiliser exactement le bootstrap (`$this->api()` / login / `.env.test` skip) — adapter les noms réels observés.

- [ ] **Step 2: Write the integration test**

`tests/Integration/Website/WebsiteIntegrationTest.php` — squelette (ajuster aux accesseurs réels de `IntegrationTestCase`) :

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Website;

use AdrienGras\Umami\Exceptions\UmamiApiException;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

final class WebsiteIntegrationTest extends IntegrationTestCase
{
    public function testCrudCycle(): void
    {
        $api = $this->authenticatedApi(); // adapter au nom réel du harnais

        $created = $api->websites->create(name: 'umami-php-crud', domain: 'crud.umami-php.test');
        self::assertArrayHasKey('id', $created);
        $id = (string) $created['id'];

        $fetched = $api->websites->get($id);
        self::assertSame('umami-php-crud', $fetched['name']);

        $api->websites->update($id, name: 'umami-php-crud-2');
        self::assertSame('umami-php-crud-2', $api->websites->get($id)['name']);

        $list = $api->websites->list(pageSize: 100);
        $ids = array_column($list['data'] ?? [], 'id');
        self::assertContains($id, $ids);

        $api->websites->reset($id);
        $api->websites->delete($id);

        $this->expectException(UmamiApiException::class);
        $api->websites->get($id);
    }

    public function testDateRangeAndValuesOnSeededWebsite(): void
    {
        $api = $this->authenticatedApi();
        $websiteId = $this->seededWebsiteId(); // adapter au nom réel

        $range = $api->websites->dateRange($websiteId);
        self::assertIsArray($range);

        $values = $api->websites->values($websiteId, 'path', $this->lastDays(7)); // adapter au helper Period réel
        self::assertIsArray($values);
    }
}
```

> Adapter `authenticatedApi()`, `seededWebsiteId()`, `lastDays()` aux helpers réels lus au Step 1. Si aucun helper `Period` n'existe, construire `Period::between($startMs, $endMs)` directement. **Aucun assert sur le seul status** (règle d'or intégration).

- [ ] **Step 3: Run integration suite**

```bash
docker compose -f docker-compose.test.yml up -d
bash scripts/seed-umami.sh
vendor/bin/phpunit --testsuite integration
```
Expected: tests Websites verts (+ les domaines existants restent verts). Si `dateRange`/`values` renvoient des formes inattendues, ajuster les asserts ET consigner la forme réelle dans `docs/API_UMAMI.md`.

- [ ] **Step 4: Update project memory**

- `docs/INDEX.md` : ajouter la ligne Websites sous Stats (Feature, date, spec=`2026-06-23-websites-crud-design.md`, plan=`2026-06-23-websites-crud.md`, statut ✅, notes : surface + nb tests).
- `docs/API_UMAMI.md` §4.2 : retirer les `⚠ à vérifier (live)` confirmés (`daterange` `{mindate,maxdate}`, `values` `[{value}]`, `transfer`, `reset`) avec la forme réelle observée.
- `docs/HANDOFF.md` : entrée datée en haut (Dernière chose faite : Websites livré ; Trucs en suspens ; Prochaine chose : étape 7.5 Users/Teams/Reports ou README ; Notes pour future Claude).
- `docs/BACKLOG.md` : noter les sous-routes websites non couvertes restantes (`active` déjà via Stats, `realtime`, `shares`, `export`, `segments`, `event-data/*`, `session-data/*`, `revenue`).

- [ ] **Step 5: Commit**

```bash
git add tests/Integration/Website docs/INDEX.md docs/API_UMAMI.md docs/HANDOFF.md docs/BACKLOG.md
git commit -m "✅ Websites : tests d'intégration (cycle CRUD + daterange/values) + mémoire à jour (étape 7.4)"
```
Expected: commit créé. (check.sh facultatif ici : pas de code `src/` modifié, mais le lancer ne coûte rien.)

---

## Notes de cohérence (self-review)

- **Couverture spec** : list/get/create/update/delete (T3) ; reset/transfer/daterange/values (T4) ; ReplayConfig+MaskLevel (T1) ; Requests (T2) ; tests unit golden+gardes (T3/T4) + intégration cycle réel (T5) ; mémoire (T5). Tous les points du spec §3-§6 ont une tâche.
- **Types** : `compact()`/`nonEmpty()`/`websiteId()` définis en T3, réutilisés en T4. `ReplayConfig::toArray()` (T1) consommé en T3 `update`. `Period::toQuery()` (existant) consommé en T4 `values`. Signatures `$payload`/`$queryParams` des Requests (T2) consommées par l'Entrypoint (T3/T4).
- **Point à vérifier à l'exécution** : clés exactes produites par `Period::toQuery()` (`startAt`/`endAt` en ms) — lire `src/Stats/Period.php` avant d'écrire les asserts de T4 Step 1.
- **Risque connu** : `WebsiteEntrypointTest` grossit (16 tests) — acceptable, un seul fichier de test par domaine est la convention du projet (cf. Stats).
```