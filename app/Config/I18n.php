<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * 다국어(i18n) 설정. 기본 언어는 URL 접두어가 없고, 나머지 언어는 `/{코드}/...` 로 접근한다.
 * 켜는 방법: `.env` 에 `i18n.enabled = ko,en` (기본 언어는 항상 포함된다).
 */
class I18n extends BaseConfig
{
    /**
     * 접두어 없이 서비스하는 기본 언어
     */
    public string $defaultLocale = 'ko';

    /**
     * 쉼표로 구분한 사용 언어 목록
     */
    public string $enabled = 'ko';

    /**
     * 기본 언어를 맨 앞에 둔 중복 없는 사용 언어 코드. 형식이 맞지 않는 코드는 버린다.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = [$this->defaultLocale];

        foreach (explode(',', $this->enabled) as $code) {
            $code = strtolower(trim($code));

            if (preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $code) === 1 && ! in_array($code, $locales, true)) {
                $locales[] = $code;
            }
        }

        return $locales;
    }

    /**
     * URL 접두어를 쓰는 언어 (기본 언어 제외)
     *
     * @return list<string>
     */
    public function prefixedLocales(): array
    {
        return array_slice($this->locales(), 1);
    }
}
