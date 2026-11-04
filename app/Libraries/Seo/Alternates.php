<?php

declare(strict_types=1);

namespace App\Libraries\Seo;

use App\Models\PageModel;
use App\Models\PostModel;
use Config\I18n;

/**
 * hreflang 용 언어별 대체 주소. 반환은 `언어 => 절대 주소`이며, 번역이 없어 자기 언어뿐이면 빈 배열이다
 * (hreflang 은 서로를 가리키는 둘 이상일 때만 의미가 있다). 사용하지 않는 언어는 뺀다.
 */
final class Alternates
{
    /**
     * @return array<string, string>
     */
    public static function forPost(int $postId): array
    {
        $urls = [];

        foreach ((new PostModel())->publicTranslationRows($postId) as $row) {
            $urls[$row['locale']] = locale_url('board/' . $row['board_slug'] . '/' . $row['id'], $row['locale']);
        }

        return self::finish($urls);
    }

    /**
     * @return array<string, string>
     */
    public static function forPage(int $pageId): array
    {
        $urls = [];

        foreach ((new PageModel())->publishedTranslationRows($pageId) as $row) {
            $urls[$row['locale']] = locale_url($row['slug'], $row['locale']);
        }

        return self::finish($urls);
    }

    /**
     * 모든 사용 언어에 같은 구조의 주소가 있는 화면(홈 등).
     *
     * @return array<string, string>
     */
    public static function forPath(string $path): array
    {
        $urls = [];

        foreach (config(I18n::class)->locales() as $locale) {
            $urls[$locale] = locale_url($path, $locale);
        }

        return self::finish($urls);
    }

    /**
     * @param array<string, string> $urls
     *
     * @return array<string, string>
     */
    private static function finish(array $urls): array
    {
        $locales = config(I18n::class)->locales();
        $urls    = array_filter($urls, static fn (string $locale): bool => in_array($locale, $locales, true), ARRAY_FILTER_USE_KEY);

        // 기본 언어를 앞에 두어 출력 순서를 고정한다
        uksort($urls, static fn (string $a, string $b): int => array_search($a, $locales, true) <=> array_search($b, $locales, true));

        return count($urls) > 1 ? $urls : [];
    }
}
