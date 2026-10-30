<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BlockedIpModel;
use App\Models\BoardModel;
use App\Models\PageVisitModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PageVisitPostStatsTest extends DatabaseTestCase
{
    private PageVisitModel $model;
    private int $boardId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model   = new PageVisitModel();
        $this->boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $this->userId  = (int) (new UserModel())->insert([
            'username' => 'writer',
            'email'    => 'writer@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'nickname' => '글쓴이',
            'role'     => 'member',
        ]);
    }

    private function makePost(string $title): int
    {
        return (int) (new PostModel())->insert([
            'board_id' => $this->boardId, 'user_id' => $this->userId,
            'title'    => $title, 'content' => '본문', 'is_notice' => 0,
        ]);
    }

    private function visit(string $uri, string $ip = '1.1.1.1', ?string $at = null, int $notFound = 0): void
    {
        $this->db->table('page_visits')->insert([
            'uri'        => $uri, 'ip_address' => $ip, 'is_not_found' => $notFound,
            'created_at' => $at ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function testDayRangeCoversTodayBackwards(): void
    {
        [$start, $end] = PageVisitModel::dayRange(7);

        $this->assertSame(date('Y-m-d', strtotime('-6 days')), $start->format('Y-m-d'));
        $this->assertSame(date('Y-m-d', strtotime('+1 day')), $end->format('Y-m-d'));
    }

    public function testTopPostsRanksPostsByVisitsAndSkipsNonPostUris(): void
    {
        $a = $this->makePost('A 글');
        $b = $this->makePost('B 글');
        $this->visit("/board/notice/{$a}");
        $this->visit("/board/notice/{$b}");
        $this->visit("/board/notice/{$b}");
        $this->visit('/board/notice');
        $this->visit('/about');
        $this->visit('/board/notice/999999');

        [$start, $end] = PageVisitModel::dayRange(7);
        $rows          = $this->model->topPosts($start, $end, 10);

        $this->assertSame([$b, $a], array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $this->assertSame(2, $rows[0]['count']);
        $this->assertSame('B 글', $rows[0]['title']);
        $this->assertSame('/board/notice/' . $b, $rows[0]['uri']);
    }

    public function testStatsExcludeNotFoundAndBlockedIpVisits(): void
    {
        $a   = $this->makePost('A 글');
        $uri = "/board/notice/{$a}";
        $this->visit($uri, '1.1.1.1');
        $this->visit($uri, '2.2.2.2', null, 1);
        $this->visit($uri, '3.3.3.3');
        (new BlockedIpModel())->block('3.3.3.3', BlockedIpModel::REASON_MANUAL);

        [$start, $end] = PageVisitModel::dayRange(7);

        $this->assertSame(1, $this->model->topPosts($start, $end)[0]['count']);
        $this->assertSame(1, array_sum(array_column($this->model->bucketCounts($start, $end, false, $uri), 'count')));
    }

    public function testBucketCountsFiltersByUri(): void
    {
        $this->visit('/board/notice/1');
        $this->visit('/board/notice/2');

        [$start, $end] = PageVisitModel::dayRange(7);
        $trend         = $this->model->bucketCounts($start, $end, false, '/board/notice/1');

        $this->assertCount(7, $trend);
        $this->assertSame(1, $trend[6]['count']);
    }

    public function testReferrersAndKeywordsFilterByUri(): void
    {
        $this->db->table('page_visits')->insertBatch([
            ['uri' => '/board/notice/1', 'ip_address' => '1.1.1.1', 'referer' => 'https://search.naver.com/search?query=blog', 'created_at' => date('Y-m-d H:i:s')],
            ['uri' => '/board/notice/2', 'ip_address' => '1.1.1.2', 'referer' => 'https://example.org/x', 'created_at' => date('Y-m-d H:i:s')],
        ]);

        [$start, $end] = PageVisitModel::dayRange(7);

        $refs = $this->model->topReferrers($start, $end, '', 10, '/board/notice/1');
        $kws  = $this->model->topSearchKeywords($start, $end, 10, '/board/notice/1');

        $this->assertSame([['host' => 'search.naver.com', 'count' => 1]], $refs);
        $this->assertSame('blog', $kws[0]['keyword']);
        $this->assertSame([], $this->model->topSearchKeywords($start, $end, 10, '/board/notice/2'));
    }

    public function testToWeeklyGroupsDailyBucketsByIsoWeek(): void
    {
        $daily = [
            ['key' => '2026-10-05', 'count' => 1], // 월 (2026-W41)
            ['key' => '2026-10-11', 'count' => 2], // 일 (2026-W41)
            ['key' => '2026-10-12', 'count' => 4], // 월 (2026-W42)
        ];

        $this->assertSame(
            [['key' => '2026-W41', 'count' => 3], ['key' => '2026-W42', 'count' => 4]],
            PageVisitModel::toWeekly($daily),
        );
    }
}
