<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\OgImageGenerator;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class OgImageController extends BaseController
{
    public function post(int $postId): ResponseInterface
    {
        $post = (new PostModel())->getPublicOgSource($postId);
        $font = OgImageGenerator::findFont();
        if ($post === null || $font === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $siteName = (string) ($this->viewData['settings']['site_name'] ?? '');
        $cacheDir = WRITEPATH . 'cache/og';
        $cacheKey = $cacheDir . '/' . $postId . '-' . self::version($post['title'], $siteName) . '.png';

        $png = is_file($cacheKey) ? file_get_contents($cacheKey) : false;
        if ($png === false || $png === '') {
            $png = (new OgImageGenerator($font))->render($post['title'], $siteName);
            $this->storeCache($cacheDir, $cacheKey, $postId, $png);
        }

        return $this->response
            ->setContentType('image/png', '')
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody($png);
    }

    /**
     * 임시 파일에 쓴 뒤 rename 으로 교체(읽는 쪽이 반쯤 쓴 파일을 보지 않는다)하고, 그 글의 옛 캐시는 지운다.
     */
    private function storeCache(string $dir, string $path, int $postId, string $png): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return;
        }

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $png) === false) {
            return;
        }
        if (! rename($tmp, $path)) {
            unlink($tmp);

            return;
        }

        foreach (glob($dir . '/' . $postId . '-*.png') ?: [] as $old) {
            if ($old !== $path) {
                unlink($old);
            }
        }
    }

    /**
     * 제목·사이트명이 바뀌면 캐시 파일과 URL 이 함께 바뀐다.
     */
    public static function version(string $title, string $siteName): string
    {
        return substr(md5($title . '|' . $siteName), 0, 8);
    }
}
