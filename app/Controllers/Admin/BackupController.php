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
        return $this->render('admin/backup/index', ['backups' => $this->manager()->list()]);
    }

    public function create(): ResponseInterface
    {
        try {
            $backup = $this->manager()->create();

            return redirect()->to('/admin/backup')->with('success', "백업 파일 {$backup['filename']}을 만들었습니다.");
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
}
