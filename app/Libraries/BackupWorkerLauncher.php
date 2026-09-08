<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

final class BackupWorkerLauncher
{
    public function __construct(private readonly string $phpBinary)
    {
    }

    public function launch(): void
    {
        if ($this->phpBinary === '' || ! is_file($this->phpBinary) || ! is_executable($this->phpBinary)) {
            throw new RuntimeException('백업 worker용 PHP CLI 실행 파일을 찾을 수 없습니다. backup.phpBinary 설정을 확인하세요.');
        }

        $logPath = WRITEPATH . 'logs/backup-create.log';
        $command = implode(' ', [
            escapeshellarg($this->phpBinary),
            escapeshellarg(ROOTPATH . 'spark'),
            'backup:create',
            '>',
            escapeshellarg($logPath),
            '2>&1',
            '&',
        ]);
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('백업 작업을 시작할 수 없습니다. 서버 PHP CLI 설정을 확인하세요.');
        }
    }
}
