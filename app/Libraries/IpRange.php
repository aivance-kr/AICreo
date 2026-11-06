<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * IP 가 CIDR 대역(또는 단일 IP)에 드는지 판별한다. IPv4·IPv6 모두 지원.
 */
final class IpRange
{
    /**
     * @param iterable<string> $ranges `a.b.c.d/n` 또는 단일 IP
     */
    public static function containsAny(string $ip, iterable $ranges): bool
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        foreach ($ranges as $range) {
            $parts        = explode('/', $range, 2);
            $subnetPacked = inet_pton($parts[0]);

            if ($subnetPacked === false || strlen($subnetPacked) !== strlen($packed)) {
                continue;
            }

            $bits = isset($parts[1]) ? (int) $parts[1] : strlen($packed) * 8;

            if (self::matchesPrefix($packed, $subnetPacked, $bits)) {
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
