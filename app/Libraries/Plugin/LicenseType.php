<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

/**
 * 플러그인 과금 모델.
 */
enum LicenseType: string
{
    case Onetime      = 'onetime';
    case Subscription = 'subscription';
    case Credit       = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Onetime      => '일회성 구매',
            self::Subscription => '구독',
            self::Credit       => '크레딧',
        };
    }
}
