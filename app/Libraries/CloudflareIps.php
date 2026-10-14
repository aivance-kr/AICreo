<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Cloudflare 엣지 IP 대역(https://www.cloudflare.com/ips/).
 *
 * 서버가 Cloudflare 뒤에 있을 때 REMOTE_ADDR 는 엣지 IP 이므로, 이 대역에서 온 요청에 한해
 * CF-Connecting-IP 헤더를 실제 접속자 IP 로 신뢰한다. 대역이 바뀌면 이 목록을 갱신한다.
 */
final class CloudflareIps
{
    public const CLIENT_IP_HEADER = 'CF-Connecting-IP';

    /**
     * @var list<string>
     */
    private const RANGES = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * `Config\App::$proxyIPs` 형식(대역 => 실제 IP 헤더)으로 돌려준다.
     *
     * @return array<string, string>
     */
    public static function proxyMap(): array
    {
        return array_fill_keys(self::RANGES, self::CLIENT_IP_HEADER);
    }

    public static function contains(string $ip): bool
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        foreach (self::RANGES as $range) {
            [$subnet, $bits] = explode('/', $range);
            $subnetPacked    = (string) inet_pton($subnet);

            if (strlen($subnetPacked) !== strlen($packed)) {
                continue;
            }

            if (self::matchesPrefix($packed, $subnetPacked, (int) $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesPrefix(string $packed, string $subnetPacked, int $bits): bool
    {
        $fullBytes = intdiv($bits, 8);

        if (strncmp($packed, $subnetPacked, $fullBytes) !== 0) {
            return false;
        }

        $remainder = $bits % 8;

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$fullBytes]) & $mask) === (ord($subnetPacked[$fullBytes]) & $mask);
    }
}
