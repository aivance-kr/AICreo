<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

/**
 * 보유 상태.
 *
 * 시각으로 계산할 수 없는 것만 담는다 — 만료는 expires_at 으로 계산하므로
 * 여기에 expired 가 없다.
 */
enum EntitlementStatus: string
{
    case Active    = 'active';
    case Cancelled = 'cancelled';
    case Revoked   = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active    => '정상',
            self::Cancelled => '해지 예약',
            self::Revoked   => '회수됨',
        };
    }
}
