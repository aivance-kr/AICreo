<?php

declare(strict_types=1);

namespace App\Libraries;

use GdImage;

/**
 * 업로드 이미지의 썸네일을 GD 로 만든다.
 *
 * 썸네일은 원본과 같은 폴더의 `thumbs/` 아래 같은 파일명으로 둔다.
 * 원본이 작아 줄일 이득이 없으면 만들지 않고, 보여줄 때 {@see self::resolve()} 가 원본을 돌려준다.
 */
final class Thumbnail
{
    public const MAX_EDGE = 400;
    private const DIR     = 'thumbs';

    /**
     * GD 로 리사이즈할 수 있는 확장자 (gif 는 움직이는 이미지가 깨지고 svg 는 벡터라 제외)
     */
    private const SUPPORTED = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * 원본 상대 경로(uploads/...)에 대응하는 썸네일 상대 경로
     */
    public static function pathFor(string $relativePath): string
    {
        $dir = dirname($relativePath);

        return ($dir === '.' ? '' : $dir . '/') . self::DIR . '/' . basename($relativePath);
    }

    public static function isThumbPath(string $relativePath): bool
    {
        return basename(dirname($relativePath)) === self::DIR;
    }

    /**
     * 썸네일이 있으면 썸네일 경로, 없으면(작은 이미지 등) 원본 경로
     */
    public static function resolve(string $relativePath): string
    {
        $thumb = self::pathFor($relativePath);

        return is_file(FCPATH . $thumb) ? $thumb : $relativePath;
    }

    public static function remove(string $relativePath): void
    {
        $thumb = FCPATH . self::pathFor($relativePath);
        if (is_file($thumb) && is_writable(dirname($thumb))) {
            unlink($thumb);
        }
    }

    /**
     * 썸네일 폴더(없으면 원본 폴더)에 쓸 수 있는지. 웹서버 계정이 만든 폴더를 다른 계정으로 CLI 실행하면 false.
     */
    public static function canWrite(string $relativePath): bool
    {
        $dir = dirname(FCPATH . self::pathFor($relativePath));

        return is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir));
    }

    public static function supports(string $relativePath): bool
    {
        return in_array(strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)), self::SUPPORTED, true);
    }

    /**
     * 썸네일을 (재)생성한다.
     *
     * @return bool 썸네일을 만들었으면 true, 만들 필요가 없거나 실패했으면 false
     */
    public static function create(string $relativePath): bool
    {
        $source = FCPATH . $relativePath;
        if (! self::supports($relativePath) || ! is_file($source)) {
            return false;
        }

        $info = @getimagesize($source);
        if ($info === false || max($info[0], $info[1]) <= self::MAX_EDGE) {
            self::remove($relativePath);

            return false;
        }

        $image = self::load($source, strtolower(pathinfo($source, PATHINFO_EXTENSION)));
        if ($image === null) {
            return false;
        }

        $image  = self::applyExifOrientation($image, $source);
        $width  = imagesx($image);
        $height = imagesy($image);
        $ratio  = self::MAX_EDGE / max($width, $height);

        $thumb = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
        if ($thumb === false) {
            return false;
        }

        if (! self::canWrite($relativePath)) {
            return false;
        }

        $target = FCPATH . self::pathFor($relativePath);
        if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true)) {
            return false;
        }

        $saved = self::save($thumb, $target, strtolower(pathinfo($target, PATHINFO_EXTENSION)));

        // 썸네일이 원본보다 작지 않으면 굳이 쓰지 않는다
        if ($saved && filesize($target) >= filesize($source)) {
            unlink($target);

            return false;
        }

        return $saved;
    }

    private static function load(string $path, string $ext): ?GdImage
    {
        $image = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'webp'        => @imagecreatefromwebp($path),
            default       => false,
        };

        return $image === false ? null : $image;
    }

    private static function save(GdImage $image, string $path, string $ext): bool
    {
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return match ($ext) {
            'png'   => imagepng($image, $path, 6),
            'webp'  => imagewebp($image, $path, 82),
            default => imagejpeg($image, $path, 82),
        };
    }

    /**
     * EXIF 회전값을 픽셀에 반영한다 (썸네일에는 EXIF 가 남지 않아 안 하면 눕거나 뒤집혀 보인다).
     */
    private static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) {
            return $image;
        }

        $exif    = @exif_read_data($path);
        $degrees = match ($exif['Orientation'] ?? 1) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        return imagerotate($image, $degrees, 0) ?: $image;
    }
}
