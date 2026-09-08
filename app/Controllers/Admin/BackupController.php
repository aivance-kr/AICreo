<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\BackupManager;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

final class BackupController extends BaseController
{
    public function index(): string
    {
        $manager = $this->manager();

        return $this->render('admin/backup/index', ['backups' => $manager->list(), 'jobStatus' => $manager->jobStatus()]);
    }

    public function create(): ResponseInterface
    {
        try {
            $manager = $this->manager();
            $manager->queue();

            try {
                $this->launchWorker();
            } catch (RuntimeException $exception) {
                $manager->failQueuedJob();

                throw $exception;
            }

            return redirect()->to('/admin/backup')->with('success', '백업 생성을 시작했습니다. 완료될 때까지 이 페이지에서 상태를 확인하세요.');
        } catch (RuntimeException $exception) {
            return redirect()->to('/admin/backup')->with('error', $exception->getMessage());
        }
    }

    public function download(string $filename): ResponseInterface
    {
        try {
            $path = $this->manager()->path($filename);

            return $this->response->download($path, null)->setFileName($filename);
        } catch (RuntimeException $exception) {
            return redirect()->to('/admin/backup')->with('error', $exception->getMessage());
        }
    }

    public function restore(): ResponseInterface
    {
        if ($this->request->getPost('confirmation') !== '복원') {
            return redirect()->to('/admin/backup')->with('error', '복원 확인 문구가 일치하지 않습니다.');
        }
        $file = $this->request->getFile('backup_zip');
        if (! $file || ! $file->isValid() || $file->hasMoved() || strtolower($file->getClientExtension()) !== 'zip') {
            return redirect()->to('/admin/backup')->with('error', '유효한 ZIP 백업 파일을 선택하세요.');
        }

        try {
            $this->manager()->restore($file->getTempName());

            return redirect()->to('/admin/backup')->with('success', '복원이 완료되었습니다. 복원 직전 백업도 서버에 보관했습니다.');
        } catch (RuntimeException $exception) {
            return redirect()->to('/admin/backup')->with('error', $exception->getMessage());
        }
    }

    private function manager(): BackupManager
    {
        $config = config('Database');
        $group  = $config->defaultGroup;
        /** @var array<string, mixed> $databaseConfig */
        $databaseConfig = $config->{$group};

        return new BackupManager($databaseConfig);
    }

    private function launchWorker(): void
    {
        $logPath = WRITEPATH . 'logs/backup-create.log';
        $command = implode(' ', [
            escapeshellarg(PHP_BINARY),
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
