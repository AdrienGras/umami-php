<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration;

use AdrienGras\Umami\UmamiApi;
use PHPUnit\Framework\TestCase;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Base for integration tests hitting the docker Umami instance.
 *
 * Reads .env.test (written by scripts/seed-umami.sh). Skips the whole test when
 * the instance is not configured, so the suite stays green without docker.
 */
abstract class IntegrationTestCase extends TestCase
{
    /** @var array<string, string> */
    private array $env = [];

    protected string $baseUrl = '';
    protected string $websiteId = '';
    protected string $hostname = '';

    protected function setUp(): void
    {
        $file = \dirname(__DIR__, 2) . '/.env.test';

        if (!is_file($file)) {
            $this->markTestSkipped('.env.test missing — run scripts/seed-umami.sh.');
        }

        $this->env = $this->parseEnv($file);
        $this->baseUrl = $this->env['UMAMI_TEST_BASE'] ?? '';
        $this->websiteId = $this->env['UMAMI_TEST_WEBSITE_ID'] ?? '';
        $this->hostname = $this->env['UMAMI_TEST_HOSTNAME'] ?? 'umami-php.test';

        if ('' === $this->baseUrl || '' === $this->websiteId) {
            $this->markTestSkipped('UMAMI_TEST_BASE / UMAMI_TEST_WEBSITE_ID not set in .env.test.');
        }
    }

    protected function connector(?string $apiToken = null): UmamiApi
    {
        return new UmamiApi(baseUrl: $this->baseUrl, apiToken: $apiToken);
    }

    /**
     * Log in with the seeded admin and return a Bearer token for reporting calls.
     */
    protected function reportingToken(): string
    {
        $username = $this->env['UMAMI_TEST_USERNAME'] ?? 'admin';
        $password = $this->env['UMAMI_TEST_PASSWORD'] ?? 'umami';

        $login = new class ($username, $password) extends Request implements HasBody {
            use HasJsonBody;

            protected Method $method = Method::POST;

            public function __construct(
                private readonly string $username,
                private readonly string $password,
            ) {
            }

            public function resolveEndpoint(): string
            {
                return '/api/auth/login';
            }

            /** @return array<string, string> */
            protected function defaultBody(): array
            {
                return ['username' => $this->username, 'password' => $this->password];
            }
        };

        $token = $this->connector()->send($login)->json('token');

        return is_string($token) ? $token : '';
    }

    /**
     * Distinct `path` values recorded for the test website in the last hour.
     *
     * @return list<string>
     */
    protected function recordedPaths(): array
    {
        $now = (int) (microtime(true) * 1000);
        $start = $now - 3_600_000;
        $end = $now + 3_600_000;

        $request = new class ($this->websiteId, $start, $end) extends Request {
            protected Method $method = Method::GET;

            public function __construct(
                private readonly string $websiteId,
                private readonly int $startAt,
                private readonly int $endAt,
            ) {
            }

            public function resolveEndpoint(): string
            {
                return "/api/websites/{$this->websiteId}/metrics";
            }

            /** @return array<string, scalar> */
            protected function defaultQuery(): array
            {
                return ['type' => 'path', 'startAt' => $this->startAt, 'endAt' => $this->endAt];
            }
        };

        $rows = $this->connector($this->reportingToken())->send($request)->json();

        $paths = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['x']) && is_string($row['x'])) {
                    $paths[] = $row['x'];
                }
            }
        }

        return $paths;
    }

    /**
     * Poll {@see recordedPaths()} until $path shows up (or timeout).
     */
    protected function waitForPath(string $path, float $timeoutSeconds = 10.0): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            if (in_array($path, $this->recordedPaths(), true)) {
                return true;
            }

            usleep(500_000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $file): array
    {
        $env = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (2 === count($parts)) {
                $env[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $env;
    }
}
