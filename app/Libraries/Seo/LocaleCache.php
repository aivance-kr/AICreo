<?php

declare(strict_types=1);

namespace App\Libraries\Seo;

use Config\I18n;

/**
 * 언어별로 따로 캐시하는 RSS 피드(`seo_feed_{locale}`)와 llms.txt(`seo_llms_{locale}`)의 키·무효화 한곳 관리.
 */
final class LocaleCache
{
    public static function feedKey(string $locale): string
    {
        return 'seo_feed_' . $locale;
    }

    public static function llmsKey(string $locale): string
    {
        return 'seo_llms_' . $locale;
    }

    public static function clearFeed(): void
    {
        foreach (config(I18n::class)->locales() as $locale) {
            cache()->delete(self::feedKey($locale));
        }
    }

    public static function clearLlms(): void
    {
        foreach (config(I18n::class)->locales() as $locale) {
            cache()->delete(self::llmsKey($locale));
        }
    }
}
