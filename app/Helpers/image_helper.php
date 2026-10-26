<?php

declare(strict_types=1);

use App\Libraries\Thumbnail;

if (! function_exists('thumb_url')) {
    /**
     * 업로드 이미지의 목록·미리보기용 주소. 썸네일이 있으면 썸네일, 없으면(작은 이미지) 원본을 돌려준다.
     */
    function thumb_url(string $relativePath): string
    {
        return '/' . Thumbnail::resolve(ltrim($relativePath, '/'));
    }
}
