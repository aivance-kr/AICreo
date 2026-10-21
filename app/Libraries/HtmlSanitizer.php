<?php

declare(strict_types=1);

namespace App\Libraries;

use HTMLPurifier;
use HTMLPurifier_Config;

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

        $cacheDir = WRITEPATH . 'cache/htmlpurifier';
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.SerializerPath', $cacheDir);
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('Attr.AllowedClasses', self::CODE_CLASSES);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.DefaultImageAlt', '');
        $config->set('HTML.TargetNoopener', true);
        $config->set('CSS.AllowedProperties', ['text-align', 'width', 'height']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);

        return self::$purifier = new HTMLPurifier($config);
    }
}
