<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\TrashManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 휴지통에 보관 기간이 지난 글·댓글·페이지를 영구 삭제한다. 하루에 한 번 크론으로 실행한다.
 */
class TrashEmptyCommand extends BaseCommand
{
    protected $group       = 'Blog';
    protected $name        = 'trash:empty';
    protected $description = '휴지통에서 보관 기간이 지난 항목을 영구 삭제합니다.';
    protected $usage       = 'trash:empty [--days 30]';
    protected $options     = ['--days' => '보관 일수 (기본 ' . TrashManager::RETENTION_DAYS . ')'];

    public function run(array $params)
    {
        $days = max(1, (int) (CLI::getOption('days') ?? TrashManager::RETENTION_DAYS));

        $purged = (new TrashManager())->purgeExpired($days);

        CLI::write(sprintf(
            '완료: 글 %d건, 댓글 %d건, 페이지 %d건 영구 삭제 (보관 %d일 경과).',
            $purged[TrashManager::TYPE_POSTS],
            $purged[TrashManager::TYPE_COMMENTS],
            $purged[TrashManager::TYPE_PAGES],
            $days,
        ), 'green');
    }
}
