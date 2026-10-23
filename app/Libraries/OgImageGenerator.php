<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * 글 제목을 얹은 OG 썸네일(1200x630 PNG)을 GD 로 만든다.
 */
final class OgImageGenerator
{
    public const WIDTH       = 1200;
    public const HEIGHT      = 630;
    private const MARGIN     = 80;
    private const TITLE_SIZE = 60;
    private const MAX_LINES  = 4;

    /**
     * 한글을 그릴 수 있는 시스템 폰트 후보 (.env 의 OG_FONT_PATH 가 우선)
     */
    private const SYSTEM_FONTS = [
        '/usr/share/fonts/truetype/noto/NotoSansCJK-Bold.ttc',
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
        '/usr/share/fonts/noto-cjk/NotoSansCJK-Bold.ttc',
        '/usr/share/fonts/truetype/nanum/NanumGothicBold.ttf',
        '/usr/share/fonts/truetype/nanum/NanumGothic.ttf',
        '/System/Library/Fonts/AppleSDGothicNeo.ttc',
    ];

    public function __construct(private readonly string $fontPath)
    {
    }

    /**
     * 사용할 폰트 경로를 찾는다. 없으면 null (호출 측은 사이트 공통 OG 이미지로 폴백).
     *
     * @param list<string>|null $fallbacks null 이면 시스템 후보 목록 사용
     */
    public static function findFont(?string $configured = null, ?array $fallbacks = null): ?string
    {
        $configured ??= (string) env('OG_FONT_PATH', '');

        foreach ([$configured, ...($fallbacks ?? self::SYSTEM_FONTS)] as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return string PNG 바이너리
     */
    public function render(string $title, string $siteName): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($image === false) {
            throw new RuntimeException('GD 이미지를 만들 수 없습니다.');
        }

        $bg     = imagecolorallocate($image, 31, 41, 55);
        $accent = imagecolorallocate($image, 96, 165, 250);
        $white  = imagecolorallocate($image, 255, 255, 255);
        $muted  = imagecolorallocate($image, 156, 163, 175);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $bg);
        imagefilledrectangle($image, self::MARGIN, 70, self::MARGIN + 80, 76, $accent);

        $y = 190;

        foreach ($this->wrap($title, self::TITLE_SIZE, self::WIDTH - self::MARGIN * 2) as $line) {
            imagettftext($image, self::TITLE_SIZE, 0, self::MARGIN, $y, $white, $this->fontPath, $line);
            $y += (int) (self::TITLE_SIZE * 1.45);
        }
        imagettftext($image, 30, 0, self::MARGIN, self::HEIGHT - 60, $muted, $this->fontPath, $siteName);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * 글자 단위로 줄바꿈한다 (한글은 공백이 드물어 단어 단위가 맞지 않는다). 넘치면 말줄임.
     *
     * @return list<string>
     */
    private function wrap(string $text, int $size, int $maxWidth): array
    {
        $chars = mb_str_split(preg_replace('/\s+/u', ' ', trim($text)) ?? '');
        $lines = [];
        $line  = '';

        foreach ($chars as $i => $char) {
            if ($line !== '' && $this->width($line . $char, $size) > $maxWidth) {
                $lines[] = rtrim($line);
                $line    = ltrim($char);

                if (count($lines) === self::MAX_LINES) {
                    // 남은 글자가 있으니 마지막 줄을 말줄임으로 닫는다
                    $lines[self::MAX_LINES - 1] = $this->ellipsize($lines[self::MAX_LINES - 1], $size, $maxWidth);

                    return $lines;
                }
            } else {
                $line .= $char;
            }
        }

        if (trim($line) !== '') {
            $lines[] = trim($line);
        }

        return $lines;
    }

    private function ellipsize(string $line, int $size, int $maxWidth): string
    {
        while ($line !== '' && $this->width($line . '…', $size) > $maxWidth) {
            $line = mb_substr($line, 0, -1);
        }

        return $line . '…';
    }

    private function width(string $text, int $size): int
    {
        $box = imagettfbbox($size, 0, $this->fontPath, $text);

        return $box === false ? 0 : abs($box[2] - $box[0]);
    }
}
