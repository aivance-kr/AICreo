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

    public function testWhitelistedIpIsNeverBlocked(): void
    {
        putenv('security.ipWhitelist=9.9.9.9, 8.8.8.8');
        $_ENV['security.ipWhitelist'] = '9.9.9.9, 8.8.8.8';

        try {
            $model = new BlockedIpModel();

            $this->assertFalse($model->block('8.8.8.8', BlockedIpModel::REASON_AUTO));
            $this->assertSame(0, $model->countAllResults());

            $model->insert(['ip_address' => '9.9.9.9', 'reason' => BlockedIpModel::REASON_MANUAL, 'blocked_until' => '2999-01-01 00:00:00']);
            $this->assertFalse($model->isBlocked('9.9.9.9'));
        } finally {
            putenv('security.ipWhitelist');
            unset($_ENV['security.ipWhitelist']);
        }
    }

    public function testGooglebotRangesAreNeverBlocked(): void
    {
        $model = new BlockedIpModel();

        foreach (['66.249.66.1', '34.64.1.1', '34.22.85.30', '192.178.5.9'] as $ip) {
            $this->assertFalse($model->block($ip, BlockedIpModel::REASON_AUTO), $ip);
        }
        $this->assertTrue($model->block('66.249.96.1', BlockedIpModel::REASON_AUTO));
        $this->assertTrue($model->block('34.22.85.32', BlockedIpModel::REASON_AUTO));
    }

    public function testEnvWhitelistAcceptsCidr(): void
    {
        putenv('security.ipWhitelist=10.1.0.0/16');
        $_ENV['security.ipWhitelist'] = '10.1.0.0/16';

        try {
            $this->assertTrue(BlockedIpModel::isWhitelisted('10.1.200.3'));
            $this->assertFalse(BlockedIpModel::isWhitelisted('10.2.0.1'));
        } finally {
            putenv('security.ipWhitelist');
            unset($_ENV['security.ipWhitelist']);
        }
    }

    public function testSearchActiveSupportsPartialAndWildcard(): void
    {
        $model = new BlockedIpModel();
        $model->block('1.2.3.4', BlockedIpModel::REASON_AUTO);
        $model->block('1.9.3.44', BlockedIpModel::REASON_AUTO);

        $this->assertSame(2, $model->searchActive('3.4', 1, 20)['total']);
        $this->assertSame(1, $model->searchActive('1.2.*', 1, 20)['total']);
        $this->assertSame(2, $model->searchActive('*3.4*', 1, 20)['total']);
        $this->assertSame(2, $model->searchActive('1.*.3.4', 1, 20)['total']);
        $this->assertSame(0, $model->searchActive('1%', 1, 20)['total']);

        $model->block('195.178.110.199', BlockedIpModel::REASON_AUTO);
        $this->assertSame(1, $model->searchActive('195.178.110.*', 1, 20)['total']);
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
