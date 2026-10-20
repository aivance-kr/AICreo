<?php

declare(strict_types=1);

if (! function_exists('asset_url')) {
    /**
     * public/ 아래 정적 파일 주소에 수정 시각(?v=YmdHis)을 붙여 브라우저 캐시를 무효화한다.
     *
     * 파일을 고치면 주소가 바뀌어 새로 받고, 안 고쳤으면 같은 주소라 캐시를 그대로 쓴다.
     * 파일이 없으면 버전 없이 주소만 돌려준다.
     */
    function asset_url(string $path): string
    {
        $path  = '/' . ltrim($path, '/');
        $mtime = @filemtime(FCPATH . ltrim($path, '/'));

        return $mtime === false ? $path : $path . '?v=' . date('YmdHis', $mtime);
    }
}
