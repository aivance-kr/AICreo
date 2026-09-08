<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\BackupManager;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class BackupManagerJobTest extends CIUnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/aicreo-backup-job-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function testQueueWritesStatusAndPreventsConcurrentBackup(): void
    {
        $manager = new BackupManager([], $this->directory, $this->directory . '/uploads');

        $manager->queue();

        $this->assertSame('queued', $manager->jobStatus()['status']);
        $this->expectException(RuntimeException::class);
        $manager->queue();
    }

    public function testFailedQueuedJobReleasesLockForRetry(): void
    {
        $manager = new BackupManager([], $this->directory, $this->directory . '/uploads');
        $manager->queue();
        $manager->failQueuedJob();

        $this->assertSame('failed', $manager->jobStatus()['status']);
        $manager->queue();
        $this->assertSame('queued', $manager->jobStatus()['status']);
    }
}
