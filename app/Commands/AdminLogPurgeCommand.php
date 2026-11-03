<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\AdminLogModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 보관 기간이 지난 관리자 활동 로그를 지운다. 하루에 한 번 크론으로 실행한다.
 */
class AdminLogPurgeCommand extends BaseCommand
{
    private const DEFAULT_DAYS = 180;

    protected $group       = 'Security';
    protected $name        = 'admin-logs:purge';
    protected $description = '보관 기간이 지난 관리자 활동 로그를 삭제합니다.';
    protected $usage       = 'admin-logs:purge [--days 180]';
    protected $options     = ['--days' => '보관 일수(기본 ' . self::DEFAULT_DAYS . ')'];

    public function run(array $params)
    {
        $days = (int) (CLI::getOption('days') ?? self::DEFAULT_DAYS);
        if ($days < 1) {
            CLI::error('--days 는 1 이상이어야 합니다.');

            return EXIT_USER_INPUT;
        }

        $deleted = (new AdminLogModel())->purgeOlderThan($days);

        CLI::write(sprintf('완료: %d일 지난 로그 %d건 삭제.', $days, $deleted), 'green');
    }
}
