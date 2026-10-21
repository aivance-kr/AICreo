<?php

declare(strict_types=1);

namespace App\Libraries;

use HTMLPurifier;
use HTMLPurifier_Config;
use Throwable;

/**
 * 에디터 HTML 허용목록(allowlist) 정화.
 * 허용한 태그·속성·URI 스킴 외에는 모두 제거한다. (정규식 denylist 로는 우회가 가능하다)
 */
final class HtmlSanitizer
{
    /**
     * 에디터가 만들어내는 태그·속성만 허용
     */
    private const ALLOWED = 'p[style],br,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],'
        . 'strong,b,em,i,u,s,sub,sup,span[style],ul,ol,li,blockquote,hr,'
        . 'a[href|title|target|rel],img[src|alt|title|width|height|style],'
        . 'table[style],thead,tbody,tfoot,tr,th[style|colspan|rowspan],td[style|colspan|rowspan],'
        . 'pre[class],code[class]';

    /**
     * TinyMCE codesample 이 붙이는 언어 클래스 + 흔한 언어
     */
    private const CODE_CLASSES = [
        'language-markup', 'language-css', 'language-javascript', 'language-typescript', 'language-json',
        'language-php', 'language-ruby', 'language-python', 'language-java', 'language-c', 'language-csharp',
        'language-cpp', 'language-sql', 'language-bash', 'language-go',
    ];

    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier instanceof HTMLPurifier) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');

        // 캐시 디렉터리를 쓸 수 없는 서버(웹 서버 사용자 ≠ 배포 사용자)에서도 500 이 나지 않도록 캐시 없이 동작한다
        $cacheDir = self::writableCacheDir();
        if ($cacheDir !== null) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('Attr.AllowedClasses', self::CODE_CLASSES);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.DefaultImageAlt', '');
        $config->set('HTML.TargetNoopener', true);
        $config->set('CSS.AllowedProperties', ['text-align', 'width', 'height']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);

        return self::$purifier = new HTMLPurifier($config);
    }

    private static function writableCacheDir(): ?string
    {
        $dir = WRITEPATH . 'cache/htmlpurifier';

        try {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        } catch (Throwable) {
            return null;
        }

        return is_dir($dir) && is_writable($dir) ? $dir : null;
    }
}
