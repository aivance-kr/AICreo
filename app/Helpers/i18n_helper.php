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
