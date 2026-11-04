<?php

declare(strict_types=1);

use Config\I18n;

if (! function_exists('locale_url')) {
    /**
     * 언어별 사이트 주소. 기본 언어는 접두어 없이, 그 밖의 사용 언어는 `/{코드}/` 접두어를 붙인다.
     * 사용하지 않는 언어 코드는 접두어 없이 돌려준다.
     */
    function locale_url(string $path = '', ?string $locale = null): string
    {
        $config = config(I18n::class);
        $locale ??= service('request')->getLocale();
        $path = trim($path, '/');

        if (in_array($locale, $config->prefixedLocales(), true)) {
            $path = trim($locale . '/' . $path, '/');
        }

        return base_url($path);
    }
}

if (! function_exists('html_lang')) {
    /**
     * 현재 요청 언어 코드 (`<html lang>` 용)
     */
    function html_lang(): string
    {
        return service('request')->getLocale();
    }
}

if (! function_exists('lang_switch_url')) {
    /**
     * 언어 전환기의 목적지. 번역이 있으면 그 주소($page['alternates']), 번역 대상 화면(글·페이지)인데 번역이 없으면
     * 그 언어 홈, 번역과 무관한 화면(목록·검색 등)은 같은 경로·쿼리를 그 언어로 보낸다.
     *
     * @param array<string, mixed> $page
     */
    function lang_switch_url(string $locale, array $page = []): string
    {
        if (isset($page['alternates'][$locale])) {
            return (string) $page['alternates'][$locale];
        }

        if (! empty($page['translatable'])) {
            return locale_url('', $locale);
        }

        $path = uri_string();

        foreach (config(I18n::class)->locales() as $known) {
            if ($path === $known || str_starts_with($path, $known . '/')) {
                $path = ltrim(substr($path, strlen($known)), '/');
                break;
            }
        }

        $query = service('request')->getUri()->getQuery();

        return locale_url($path, $locale) . ($query !== '' ? '?' . $query : '');
    }
}
