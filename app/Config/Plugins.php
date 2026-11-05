<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * 플러그인 운영 설정.
 *
 * ZIP 업로드는 서버에 임의 PHP 를 올리는 기능이라 기본으로 꺼 둔다.
 * 켜는 방법: 서버 `.env` 에 `plugins.allowUpload = true`.
 * 관리자 화면(DB 설정)이 아니라 .env 에 둔 이유 — 관리자 계정이 탈취돼도
 * 웹에서 스스로 업로드를 켜 코드를 올릴 수 없게 하기 위해서다.
 */
class Plugins extends BaseConfig
{
    public bool $allowUpload = false;
}
