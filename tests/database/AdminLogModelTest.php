<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\AdminLogModel;
use CodeIgniter\I18n\Time;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AdminLogModelTest extends DatabaseTestCase
{
    private AdminLogModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AdminLogModel();
    }

    public function testRecordStoresActorAndTarget(): void
    {
        $this->model->record('update', 'post', 7, '제목 수정', 1, '203.0.113.5');

        $row = $this->model->first();
        $this->assertSame('update', $row['action']);
        $this->assertSame('post', $row['target_type']);
        $this->assertSame(7, (int) $row['target_id']);
        $this->assertSame(1, (int) $row['user_id']);
        $this->assertSame('203.0.113.5', $row['ip_address']);
    }

    public function testRecordTruncatesLongSummary(): void
    {
        $this->model->record('update', 'setting', null, str_repeat('가', 400), 1, '127.0.0.1');

        $this->assertSame(255, mb_strlen((string) $this->model->first()['summary']));
    }

    public function testSearchFiltersByTargetTypeAndAction(): void
    {
        $this->model->record('create', 'post', 1, 'a', 1, '127.0.0.1');
        $this->model->record('delete', 'post', 2, 'b', 1, '127.0.0.1');
        $this->model->record('delete', 'user', 3, 'c', 1, '127.0.0.1');

        $this->assertSame(2, $this->model->search('', 'post', '', 1, 20)['total']);
        $this->assertSame(1, $this->model->search('delete', 'post', '', 1, 20)['total']);
        $this->assertSame(3, $this->model->search('', '', '', 1, 20)['total']);
    }

    public function testSearchKeywordMatchesSummaryAndIp(): void
    {
        $this->model->record('update', 'page', 1, '소개 페이지 수정', 1, '198.51.100.9');
        $this->model->record('update', 'post', 2, '다른 글', 1, '127.0.0.1');

        $this->assertSame(1, $this->model->search('', '', '소개', 1, 20)['total']);
        $this->assertSame(1, $this->model->search('', '', '198.51.100.9', 1, 20)['total']);
    }

    public function testSearchIsNewestFirstAndPaginated(): void
    {
        foreach (['a', 'b', 'c'] as $summary) {
            $this->model->record('update', 'post', 1, $summary, 1, '127.0.0.1');
        }

        $result = $this->model->search('', '', '', 1, 2);

        $this->assertSame(3, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertSame('c', $result['items'][0]['summary']);
    }

    public function testPurgeOlderThanDeletesOnlyOldRows(): void
    {
        $this->model->record('update', 'post', 1, 'old', 1, '127.0.0.1');
        $this->model->record('update', 'post', 1, 'new', 1, '127.0.0.1');
        $old = $this->model->where('summary', 'old')->first();
        $this->db->table('admin_logs')->where('id', $old['id'])
            ->update(['created_at' => Time::now()->subDays(200)->toDateTimeString()]);

        $this->assertSame(1, $this->model->purgeOlderThan(90));
        $this->assertSame(1, $this->model->countAllResults());
    }

    public function testChangedKeysSummaryNeverContainsValues(): void
    {
        $summary = AdminLogModel::keysSummary(['smtp_pass', 'site_name']);

        $this->assertSame('변경 항목: smtp_pass, site_name', $summary);
    }
}
