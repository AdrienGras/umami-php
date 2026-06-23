<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration;

use AdrienGras\Umami\Enums\MetricType;
use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\UmamiApi;
use PHPUnit\Framework\TestCase;

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

    protected function username(): string
    {
        return $this->env['UMAMI_TEST_USERNAME'] ?? 'admin';
    }

    protected function password(): string
    {
        return $this->env['UMAMI_TEST_PASSWORD'] ?? 'umami';
    }

    /**
     * Log in with the seeded admin and return a Bearer token for reporting calls.
     *
     * Dogfoods AuthEntrypoint::login().
     */
    protected function reportingToken(): string
    {
        return $this->connector()->auth->login($this->username(), $this->password())->token;
    }

    /**
     * Distinct `path` values recorded for the test website in the last hour.
     *
     * Dogfoods StatsEntrypoint::metrics().
     *
     * @return list<string>
     */
    protected function recordedPaths(): array
    {
        $now = (int) (microtime(true) * 1000);

        $rows = $this->connector($this->reportingToken())->stats->metrics(
            $this->websiteId,
            MetricType::Path,
            Period::between($now - 3_600_000, $now + 3_600_000),
        );

        $paths = [];

        foreach ($rows as $row) {
            if (isset($row['x']) && is_string($row['x'])) {
                $paths[] = $row['x'];
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
