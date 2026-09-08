<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\BackupWorkerLauncher;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class BackupWorkerLauncherTest extends CIUnitTestCase
{
    public function testMissingCliBinaryIsRejectedBeforeStartingWorker(): void
    {
        $launcher = new BackupWorkerLauncher('/path/that/does/not/exist/php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('backup.phpBinary 설정을 확인하세요.');
        $launcher->launch();
    }
}
