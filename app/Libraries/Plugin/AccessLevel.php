<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

/**
 * 플러그인 접근 수준.
 *
 * DB 에 저장하지 않는다 — Entitlement 로부터 매번 계산되는 결과다.
 */
enum AccessLevel
{
    case Full;
    case ReadOnly;
    case None;

    public function canWrite(): bool
    {
        return $this === self::Full;
    }
}
