<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Report;

use AdrienGras\Umami\Enums\ReportType;
use AdrienGras\Umami\Stats\Filters;
use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class ReportEntrypointTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $responseBody
     */
    private function apiCapturing(array $responseBody = []): UmamiApi
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: 'tok');
        $api->withMockClient(new MockClient([
            function (PendingRequest $request) use ($responseBody): MockResponse {
                return MockResponse::make($responseBody, 200);
            },
        ]));

        return $api;
    }

    // ----- CRUD (saved reports) -----

    public function testListBuildsQueryWithWebsiteIdAndType(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->reports->list('w1', ReportType::Funnel, page: 2, search: 'check');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(
            ['websiteId' => 'w1', 'type' => 'funnel', 'page' => 2, 'search' => 'check'],
            $pending->query()->all(),
        );
        self::assertSame('/api/reports', $pending->getRequest()->resolveEndpoint());
    }

    public function testListRejectsEmptyWebsiteId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->reports->list('  ');
    }

    public function testGetHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 'r1', 'name' => 'Rep']);

        $result = $api->reports->get('r1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('Rep', $result['name']);
        self::assertSame('/api/reports/r1', $pending->getRequest()->resolveEndpoint());
    }

    public function testGetRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->reports->get('  ');
    }

    public function testCreateBuildsBodyOmittingNullDescription(): void
    {
        $api = $this->apiCapturing(['id' => 'r1']);

        $api->reports->create('w1', ReportType::Funnel, 'My Funnel', ['window' => 1000]);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            [
                'websiteId' => 'w1',
                'type' => 'funnel',
                'name' => 'My Funnel',
                'parameters' => ['window' => 1000],
            ],
            null === $body ? [] : $body->all(),
        );
        self::assertSame('/api/reports', $pending->getRequest()->resolveEndpoint());
    }

    public function testCreateIncludesDescription(): void
    {
        $api = $this->apiCapturing();

        $api->reports->create('w1', ReportType::Goal, 'G', ['type' => 'event', 'value' => 'x'], description: 'desc');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            [
                'websiteId' => 'w1',
                'type' => 'goal',
                'name' => 'G',
                'parameters' => ['type' => 'event', 'value' => 'x'],
                'description' => 'desc',
            ],
            null === $body ? [] : $body->all(),
        );
    }

    public function testCreateRejectsEmptyName(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->reports->create('w1', ReportType::Utm, '  ', []);
    }

    public function testCreateRejectsTooLongName(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->reports->create('w1', ReportType::Utm, str_repeat('a', 201), []);
    }

    public function testCreateRejectsEmptyWebsiteId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->reports->create('  ', ReportType::Utm, 'N', []);
    }

    public function testUpdateBuildsBodyAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 'r1']);

        $api->reports->update('r1', 'w1', ReportType::Retention, 'Ret', ['timezone' => 'UTC']);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            [
                'websiteId' => 'w1',
                'type' => 'retention',
                'name' => 'Ret',
                'parameters' => ['timezone' => 'UTC'],
            ],
            null === $body ? [] : $body->all(),
        );
        self::assertSame('/api/reports/r1', $pending->getRequest()->resolveEndpoint());
    }

    public function testUpdateRejectsEmptyReportId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->reports->update('  ', 'w1', ReportType::Utm, 'N', []);
    }

    public function testDeleteReturnsVoidAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['ok' => true]);

        $api->reports->delete('r1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/reports/r1', $pending->getRequest()->resolveEndpoint());
    }

    // ----- Generation (9 typed endpoints) -----

    /**
     * @return array<string, array{string, string}>
     */
    public static function generationMethods(): array
    {
        return [
            'funnel' => ['funnel', 'funnel'],
            'retention' => ['retention', 'retention'],
            'utm' => ['utm', 'utm'],
            'goal' => ['goal', 'goal'],
            'journey' => ['journey', 'journey'],
            'revenue' => ['revenue', 'revenue'],
            'attribution' => ['attribution', 'attribution'],
            'performance' => ['performance', 'performance'],
            'breakdown' => ['breakdown', 'breakdown'],
        ];
    }

    #[DataProvider('generationMethods')]
    public function testGenerationHitsTypedEndpointAndInjectsType(string $method, string $type): void
    {
        $api = $this->apiCapturing([]);

        $api->reports->{$method}('w1', ['startDate' => '2026-01-01', 'endDate' => '2026-01-31']);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertEquals(
            [
                'websiteId' => 'w1',
                'type' => $type,
                'parameters' => ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'],
                'filters' => new \stdClass(),
            ],
            null === $body ? [] : $body->all(),
        );
        self::assertSame("/api/reports/{$type}", $pending->getRequest()->resolveEndpoint());
    }

    public function testGenerationSerializesProvidedFilters(): void
    {
        $api = $this->apiCapturing([]);

        $api->reports->funnel('w1', ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'], new Filters(country: 'FR'));

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            [
                'websiteId' => 'w1',
                'type' => 'funnel',
                'parameters' => ['startDate' => '2026-01-01', 'endDate' => '2026-01-31'],
                'filters' => ['country' => 'FR'],
            ],
            null === $body ? [] : $body->all(),
        );
    }

    public function testGenerationSendsEmptyFiltersAsObject(): void
    {
        $api = $this->apiCapturing([]);

        $api->reports->utm('w1', ['startDate' => '2026-01-01', 'endDate' => '2026-01-31']);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $all = $pending->body()?->all() ?? [];
        // Must serialise to a JSON object {}, never an array [] (server expects z.object).
        self::assertStringContainsString('"filters":{}', json_encode($all, JSON_THROW_ON_ERROR));
    }

    public function testGenerationReturnsNativeListShape(): void
    {
        // funnel returns a LIST [{...}] live — not an object. Must be preserved as-is.
        $api = $this->apiCapturing([
            ['type' => 'path', 'value' => '/', 'visitors' => 0],
            ['type' => 'path', 'value' => '/about', 'visitors' => 0],
        ]);

        $result = $api->reports->funnel('w1', ['startDate' => '2026-01-01', 'endDate' => '2026-01-31']);

        self::assertSame(
            [
                ['type' => 'path', 'value' => '/', 'visitors' => 0],
                ['type' => 'path', 'value' => '/about', 'visitors' => 0],
            ],
            $result,
        );
    }

    public function testGenerationRejectsEmptyWebsiteId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->reports->funnel('  ', []);
    }
}
