<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BlockedIpModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class BlockedIpModelTest extends DatabaseTestCase
{
    public function testBlockedIpIsReportedBlocked(): void
    {
        $model = new BlockedIpModel();
        $model->block('1.2.3.4', BlockedIpModel::REASON_MANUAL);

        $this->assertTrue($model->isBlocked('1.2.3.4'));
        $this->assertFalse($model->isBlocked('1.2.3.5'));
    }

    public function testCloudflareIpIsNeverBlocked(): void
    {
        $model = new BlockedIpModel();

        $this->assertFalse($model->block('172.67.10.20', BlockedIpModel::REASON_AUTO));
        $this->assertSame(0, $model->countAllResults());
    }

    public function testBlockingAgainExtendsInsteadOfDuplicating(): void
    {
        $model = new BlockedIpModel();
        $model->block('1.2.3.4', BlockedIpModel::REASON_AUTO);
        $model->block('1.2.3.4', BlockedIpModel::REASON_MANUAL);

        $this->assertSame(1, $model->countAllResults());
        $this->assertSame(BlockedIpModel::REASON_MANUAL, $model->first()['reason']);
    }

    public function testExpiredBlockIsNotBlockedAndIsPurged(): void
    {
        $this->db->table('blocked_ips')->insert(['ip_address' => '9.9.9.9', 'reason' => 'auto', 'blocked_until' => '2000-01-01 00:00:00']);
        $model = new BlockedIpModel();

        $this->assertFalse($model->isBlocked('9.9.9.9'));
        $this->assertSame(1, $model->purgeExpired());
        $this->assertSame(0, $model->countAllResults());
    }
}
