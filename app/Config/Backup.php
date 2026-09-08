<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Backup extends BaseConfig
{
    /**
     * HTTP 요청에서 백업 worker를 실행할 PHP CLI 절대 경로.
     */
    public string $phpBinary = '/usr/bin/php';

    public function __construct()
    {
        parent::__construct();

        $this->phpBinary = (string) env('backup.phpBinary', $this->phpBinary);
    }
}
