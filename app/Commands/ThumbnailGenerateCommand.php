<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Thumbnail;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * public/uploads 하위의 기존 이미지 전체에 썸네일을 만든다.
 * 이미 썸네일이 있는 파일은 건너뛰고, 작은 이미지는 썸네일 없이 원본을 쓴다.
 */
class ThumbnailGenerateCommand extends BaseCommand
{
    protected $group       = 'Media';
    protected $name        = 'thumbnails:generate';
    protected $description = 'public/uploads 하위 이미지의 썸네일을 일괄 생성합니다.';
    protected $usage       = 'thumbnails:generate [--force]';
    protected $options     = [
        '--force' => '이미 있는 썸네일도 다시 생성',
    ];

    public function run(array $params)
    {
        $force   = CLI::getOption('force') !== null;
        $root    = rtrim(FCPATH, '/') . '/';
        $created = $skipped = $failed = 0;

        if (! is_dir($root . 'uploads')) {
            CLI::write('uploads 디렉터리가 없습니다.', 'yellow');

            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . 'uploads', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($root));

            if (! $file->isFile() || Thumbnail::isThumbPath($relative) || ! Thumbnail::supports($relative)) {
                continue;
            }
            if (! $force && Thumbnail::resolve($relative) !== $relative) {
                $skipped++;

                continue;
            }

            if (Thumbnail::create($relative)) {
                $created++;
            } elseif (! Thumbnail::canWrite($relative)) {
                $failed++;
                CLI::error("쓰기 권한 없음: {$relative}");
            } else {
                $skipped++;
            }
        }

        CLI::write("완료: 생성 {$created} / 건너뜀 {$skipped} / 실패 {$failed}", $failed > 0 ? 'yellow' : 'green');
        if ($failed > 0) {
            CLI::write('웹서버 계정으로 실행하세요. 예: sudo -u www-data php spark thumbnails:generate', 'yellow');
        }
    }
}
