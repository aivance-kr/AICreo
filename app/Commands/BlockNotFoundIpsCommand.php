<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\BlockedIpModel;
use App\Models\PageVisitModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

/**
 * 최근 24시간 방문 로그에서 1시간 안에 존재하지 않는 주소로 3회 이상 접속한 IP 를 30일간 차단한다.
 * 만료된 차단도 함께 정리한다. 하루 한 번 크론으로 실행한다.
 */
class BlockNotFoundIpsCommand extends BaseCommand
{
    private const LOOKBACK_HOURS = 24;
    private const THRESHOLD      = 3;
    private const WINDOW_SECONDS = 3600;

    protected $group       = 'Security';
    protected $name        = 'ip:auto-block';
    protected $description = '짧은 시간에 존재하지 않는 주소로 반복 접속한 IP 를 한 달간 차단합니다.';
    protected $usage       = 'ip:auto-block';

    public function run(array $params)
    {
        $blockedModel = new BlockedIpModel();

        $ips = (new PageVisitModel())->notFoundBurstIps(
            Time::now()->subHours(self::LOOKBACK_HOURS),
            self::THRESHOLD,
            self::WINDOW_SECONDS,
        );

        $blocked = 0;

        foreach ($ips as $ip) {
            if (! $blockedModel->block($ip, BlockedIpModel::REASON_AUTO)) {
                CLI::write("건너뜀(Cloudflare IP): {$ip}", 'yellow');

                continue;
            }

            $blocked++;
            CLI::write("차단: {$ip}");
        }

        $purged = $blockedModel->purgeExpired();

        CLI::write(sprintf('완료: 신규/연장 차단 %d건, 만료 정리 %d건.', $blocked, $purged), 'green');
    }
}
