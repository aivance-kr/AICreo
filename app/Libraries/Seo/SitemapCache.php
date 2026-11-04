<?php

declare(strict_types=1);

namespace App\Libraries\Seo;

use Config\I18n;

/**
 * sitemap 캐시 키 한곳 관리 — 색인(`seo_sitemap`)과 언어별(`seo_sitemap_{locale}`)을 함께 지운다.
 */
final class SitemapCache
{
    public const INDEX_KEY = 'seo_sitemap';

    public static function localeKey(string $locale): string
    {
        return self::INDEX_KEY . '_' . $locale;
    }

    public static function clear(): void
    {
        cache()->delete(self::INDEX_KEY);

        foreach (config(I18n::class)->locales() as $locale) {
            cache()->delete(self::localeKey($locale));
        }
    }
}
