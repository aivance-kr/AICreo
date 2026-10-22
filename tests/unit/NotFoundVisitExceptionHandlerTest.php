<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\NotFoundVisitExceptionHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class NotFoundVisitExceptionHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function pathProvider(): iterable
    {
        yield 'wp 업로드 php' => ['/wp-content/uploads/pentest_proof_7aadef.php', true];
        yield '플러그인 php' => ['wp-content/plugins/formcraft/file-upload/server/content/files/x.php', true];
        yield 'wp-login' => ['/wp-login.php', true];
        yield 'wp-admin' => ['/wp-admin/install.php', true];
        yield 'xmlrpc' => ['/xmlrpc.php', true];
        yield '.env' => ['/.env', true];
        yield '.git' => ['/.git/config', true];
        yield '대문자 확장자' => ['/shell.PHP', true];
        yield '옛 글의 깨진 이미지' => ['/wp-content/uploads/2020/01/a.jpg', false];
        yield '일반 페이지' => ['/about', false];
        yield '비슷한 이름' => ['/environment', false];
    }

    #[DataProvider('pathProvider')]
    public function testIsScannerPath(string $path, bool $expected): void
    {
        $this->assertSame($expected, NotFoundVisitExceptionHandler::isScannerPath($path));
    }
}
