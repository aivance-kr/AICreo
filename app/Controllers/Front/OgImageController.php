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

        if (! is_file($cacheKey)) {
            if (! is_dir($cacheDir) && ! mkdir($cacheDir, 0775, true) && ! is_dir($cacheDir)) {
                throw PageNotFoundException::forPageNotFound();
            }
            file_put_contents($cacheKey, (new OgImageGenerator($font))->render($post['title'], $siteName), LOCK_EX);
        }

        return $this->response
            ->setContentType('image/png', '')
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody((string) file_get_contents($cacheKey));
    }

    /**
     * 제목·사이트명이 바뀌면 캐시 파일과 URL 이 함께 바뀐다.
     */
    public static function version(string $title, string $siteName): string
    {
        return substr(md5($title . '|' . $siteName), 0, 8);
    }
}
