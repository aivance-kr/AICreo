<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BlockedIpModel;
use App\Models\PageVisitModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;

class StatsController extends BaseController
{
    private const DAY_PAGE_SIZE = 50;

    /**
     * 글별 통계에서 고를 수 있는 기간(일). 많은 방문 로그를 훑으므로 허용 값만 받는다.
     */
    private const POST_STATS_DAYS = [7, 30, 90];

    private const POST_STATS_CACHE_TTL = 600;

    private readonly PageVisitModel $visitModel;

    public function __construct()
    {
        $this->visitModel = new PageVisitModel();
    }

    public function index(): string
    {
        $period = (string) ($this->request->getGet('period') ?? '');
        $value  = (string) ($this->request->getGet('value') ?? '');
        $range  = PageVisitModel::periodRange($period, $value);

        if ($range !== null) {
            return $this->renderPeriod($period, $value, $range);
        }

        $todayStart = Time::now()->setTime(0, 0, 0);
        $weekStart  = Time::now()->subDays(6)->setTime(0, 0, 0);
        $monthStart = Time::now()->subDays(29)->setTime(0, 0, 0);

        $now = Time::now();

        return $this->render('admin/stats/index', [
            'cards' => [
                ['label' => '오늘 방문', 'value' => $this->visitModel->countSince($todayStart), 'icon' => 'bi-eye', 'color' => 'primary'],
                ['label' => '오늘 순방문', 'value' => $this->visitModel->countUniqueIpsSince($todayStart), 'icon' => 'bi-person-check', 'color' => 'success'],
                ['label' => '최근 7일 방문', 'value' => $this->visitModel->countSince($weekStart), 'icon' => 'bi-calendar-week', 'color' => 'info'],
                ['label' => '최근 30일 방문', 'value' => $this->visitModel->countSince($monthStart), 'icon' => 'bi-calendar-month', 'color' => 'warning'],
            ],
            'trendTitle' => '최근 14일 방문 추이',
            'trend'      => array_map(
                static fn (array $d): array => ['key' => $d['date'], 'count' => $d['count']],
                $this->visitModel->dailyCounts(14),
            ),
            'monthly'   => false,
            'topPages'  => $this->visitModel->topPages(30, 10),
            'topTitle'  => '인기 페이지 (최근 30일)',
            'referrers' => $this->visitModel->topReferrers($monthStart, $now->addDays(1)->setTime(0, 0, 0), $this->request->getUri()->getHost()),
            'keywords'  => $this->visitModel->topSearchKeywords($monthStart, $now->addDays(1)->setTime(0, 0, 0)),
            'period'    => '',
            'value'     => '',
        ]);
    }

    /**
     * 글별 조회 통계: 기간 내 상위 글 순위, 선택한 글의 일간(90일은 주간) 추이·유입 경로·검색어.
     * 방문 로그 집계가 무거워 10분 캐시한다(글 단위로 무효화하지 않고 TTL 에 맡긴다).
     */
    public function posts(): string
    {
        $days   = (int) ($this->request->getGet('days') ?? 30);
        $days   = in_array($days, self::POST_STATS_DAYS, true) ? $days : 30;
        $postId = (int) ($this->request->getGet('post') ?? 0);
        $host   = $this->request->getUri()->getHost();

        $stats = cache()->remember(
            "admin_post_stats_{$days}_{$postId}_{$host}",
            self::POST_STATS_CACHE_TTL,
            fn (): array => $this->buildPostStats($days, $postId, $host),
        );

        return $this->render('admin/stats/posts', $stats + ['days' => $days, 'dayOptions' => self::POST_STATS_DAYS]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPostStats(int $days, int $postId, string $host): array
    {
        [$start, $end] = PageVisitModel::dayRange($days);

        $topPosts = $this->visitModel->topPosts($start, $end);
        $selected = $postId > 0 ? $this->visitModel->postTarget($postId) : null;

        if ($selected === null && $topPosts !== []) {
            $selected = $this->visitModel->postTarget($topPosts[0]['id']);
        }

        $trend     = [];
        $referrers = [];
        $keywords  = [];

        if ($selected !== null) {
            $trend     = $this->visitModel->bucketCounts($start, $end, false, $selected['uri']);
            $referrers = $this->visitModel->topReferrers($start, $end, $host, 10, $selected['uri']);
            $keywords  = $this->visitModel->topSearchKeywords($start, $end, 10, $selected['uri']);
        }

        $weekly = $days >= 90;

        return [
            'topPosts'  => $topPosts,
            'selected'  => $selected,
            'weekly'    => $weekly,
            'trend'     => $weekly ? PageVisitModel::toWeekly($trend) : $trend,
            'referrers' => $referrers,
            'keywords'  => $keywords,
        ];
    }

    public function day(string $date): string
    {
        $range = PageVisitModel::periodRange('day', $date);

        if ($range === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));
        $total = $this->visitModel->countOnDate($date);

        return $this->render('admin/stats/day', [
            'date'        => $date,
            'total'       => $total,
            'visits'      => $this->visitModel->visitsOnDate($date, self::DAY_PAGE_SIZE, ($page - 1) * self::DAY_PAGE_SIZE),
            'currentPage' => $page,
            'totalPages'  => (int) ceil($total / self::DAY_PAGE_SIZE),
        ]);
    }

    public function blockIp(): ResponseInterface
    {
        $ip = (string) $this->request->getPost('ip');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return redirect()->back()->with('error', '올바른 IP 주소가 아닙니다.');
        }

        if ($ip === $this->request->getIPAddress()) {
            return redirect()->back()->with('error', '현재 접속 중인 내 IP 는 차단할 수 없습니다.');
        }

        if (! (new BlockedIpModel())->block($ip, BlockedIpModel::REASON_MANUAL)) {
            return redirect()->back()->with('error', 'Cloudflare IP 는 정상 방문자까지 막을 수 있어 차단할 수 없습니다.');
        }

        return redirect()->back()->with('success', "{$ip} 을(를) " . BlockedIpModel::BLOCK_DAYS . '일간 차단했습니다.');
    }

    /**
     * @param array{start: Time, end: Time, label: string} $range
     */
    private function renderPeriod(string $period, string $value, array $range): string
    {
        $start   = $range['start'];
        $end     = $range['end'];
        $monthly = $period === 'year';

        return $this->render('admin/stats/index', [
            'cards' => [
                ['label' => $range['label'] . ' 방문', 'value' => $this->visitModel->countBetween($start, $end), 'icon' => 'bi-eye', 'color' => 'primary'],
                ['label' => $range['label'] . ' 순방문', 'value' => $this->visitModel->countUniqueIpsBetween($start, $end), 'icon' => 'bi-person-check', 'color' => 'success'],
            ],
            'trendTitle' => $range['label'] . ' 방문 추이',
            'trend'      => $this->visitModel->bucketCounts($start, $end, $monthly),
            'monthly'    => $monthly,
            'topPages'   => $this->visitModel->topPagesBetween($start, $end),
            'topTitle'   => '인기 페이지 (' . $range['label'] . ')',
            'referrers'  => $this->visitModel->topReferrers($start, $end, $this->request->getUri()->getHost()),
            'keywords'   => $this->visitModel->topSearchKeywords($start, $end),
            'period'     => $period,
            'value'      => $value,
        ]);
    }
}
