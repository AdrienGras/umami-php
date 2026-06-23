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

        /** @var Method */
        $value = $reflection->getValue($request);

        return $value;
    }
}
