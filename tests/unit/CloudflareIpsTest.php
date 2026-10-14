<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\CloudflareIps;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class CloudflareIpsTest extends CIUnitTestCase
{
    #[DataProvider('provideContains')]
    public function testContains(string $ip, bool $expected): void
    {
        $this->assertSame($expected, CloudflareIps::contains($ip));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideContains(): iterable
    {
        yield 'IPv4 172.64/13 대역' => ['172.67.10.20', true];

        yield 'IPv4 104.16/13 대역' => ['104.21.5.5', true];

        yield 'IPv4 162.158/15 대역' => ['162.159.1.1', true];

        yield 'IPv4 대역 바로 밖' => ['172.72.0.1', false];

        yield 'IPv6 2606:4700::/32' => ['2606:4700:3030::1', true];

        yield 'IPv6 대역 밖' => ['2606:4701::1', false];

        yield '일반 IPv4' => ['8.8.8.8', false];

        yield '잘못된 IP' => ['not-an-ip', false];
    }

    public function testRealClientIpIsUsedWhenRequestComesFromCloudflare(): void
    {
        $request = $this->makeRequest('172.67.10.20', '203.0.113.7');

        $this->assertSame('203.0.113.7', $request->getIPAddress());
    }

    public function testSpoofedHeaderIsIgnoredWhenRequestIsNotFromCloudflare(): void
    {
        $request = $this->makeRequest('198.51.100.9', '203.0.113.7');

        $this->assertSame('198.51.100.9', $request->getIPAddress());
    }

    private function makeRequest(string $remoteAddr, string $clientIp): IncomingRequest
    {
        $config  = new App();
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request->setGlobal('server', ['REMOTE_ADDR' => $remoteAddr]);
        $request->setHeader(CloudflareIps::CLIENT_IP_HEADER, $clientIp);

        return $request;
    }
}
