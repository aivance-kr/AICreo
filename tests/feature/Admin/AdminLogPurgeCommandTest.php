<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminLogModel;
use CodeIgniter\I18n\Time;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class AdminLogPurgeCommandTest extends DatabaseTestCase
{
    public function testPurgeCommandRemovesOldLogsOnly(): void
    {
        $model = new AdminLogModel();
        $model->record('update', 'post', 1, 'old', 1, '127.0.0.1');
        $model->record('update', 'post', 1, 'new', 1, '127.0.0.1');
        $this->db->table('admin_logs')->where('summary', 'old')
            ->update(['created_at' => Time::now()->subDays(400)->toDateTimeString()]);

        command('admin-logs:purge --days 180');

        $this->assertSame(['new'], array_column($model->findAll(), 'summary'));
    }
}
