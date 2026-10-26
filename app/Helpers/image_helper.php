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

if (! function_exists('content_thumb_url')) {
    /**
     * 본문 HTML 에서 뽑은 이미지 src 의 목록용 주소. 우리 uploads 이미지이고 썸네일이 있으면 썸네일 주소로 바꾼다.
     *
     * 외부 이미지, 쿼리스트링이 붙은 주소, 썸네일이 없는(작은) 이미지는 그대로 돌려준다.
     */
    function content_thumb_url(string $src): string
    {
        $host    = (string) parse_url(base_url(), PHP_URL_HOST);
        $pattern = '~^((?:https?:)?//' . preg_quote($host, '~') . '|/|(?:\.\./|\./)*)(?:index\.php/)?(uploads/(?:[^/?#]+/)*)([^/?#]+)$~i';

        if (preg_match($pattern, $src, $m) !== 1) {
            return $src;
        }

        $dir  = rawurldecode($m[2]);
        $name = rawurldecode($m[3]);

        return is_file(FCPATH . $dir . 'thumbs/' . $name)
            ? $m[1] . $m[2] . 'thumbs/' . $m[3]
            : $src;
    }
}
