<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * 에러 페이지 안전성 테스트.
 *
 * CodeIgniter4 는 errors/html/error_{상태코드}.php 가 존재하면 운영 환경에서도
 * production.php 대신 그 파일을 쓴다(ExceptionHandler::determineView). 그리고
 * 뷰에 넘어오는 $message 는 BaseExceptionHandler::collectVars() 가 만든
 * $exception->getMessage() 원문이다 — 환경에 따라 걸러지지 않는다.
 *
 * 즉 error_500.php 를 만드는 행위 자체가, 아무 조치 없이 두면 CI4 기본
 * production.php 가 지키던 «내부 정보 비노출» 선을 무너뜨린다. 예외 메시지에는
 * SQL 조각·파일 경로·클래스명이 흔히 섞인다.
 *
 * 또한 이 저장소는 재판매되는 템플릿이라, 에러 페이지에 제작 도구의 이름이나
 * 로고가 남으면 납품되는 모든 클라이언트 사이트에 그대로 노출된다.
 *
 * @internal
 */
final class ErrorViewSafetyTest extends CIUnitTestCase
{
    /**
     * 우리가 소유한 상태코드별 에러 뷰
     */
    private const ERROR_VIEWS = [400, 403, 404, 500, 503];

    private function sourceOf(int $code): string
    {
        $path = APPPATH . "Views/errors/html/error_{$code}.php";
        $this->assertFileExists($path, "error_{$code}.php 가 없습니다.");

        return (string) file_get_contents($path);
    }

    /**
     * 운영 환경에서는 예외 메시지를 화면에 내보내지 않는다.
     */
    public function testErrorViewsGuardExceptionMessageInProduction(): void
    {
        foreach (self::ERROR_VIEWS as $code) {
            $source = $this->sourceOf($code);

            if (! str_contains($source, '$message')) {
                continue; // 애초에 쓰지 않으면 안전하다
            }

            $this->assertMatchesRegularExpression(
                "/ENVIRONMENT\\s*!==\\s*'production'/",
                $source,
                "error_{$code}.php 가 \$message 를 쓰면서 운영 환경 가드를 두지 않았습니다.\n"
                . '$message 는 $exception->getMessage() 원문이라 내부 정보가 새어 나갑니다.',
            );
        }
    }

    /**
     * 재판매 템플릿이므로 에러 페이지는 제작 도구의 정체성을 드러내지 않는다.
     */
    public function testErrorViewsCarryNoToolBranding(): void
    {
        $banned = ['AIvance', 'AICopia', 'AICura', 'AIFid', 'AILicet', 'AITessera'];
        $files  = glob(APPPATH . 'Views/errors/html/{,_partials/}*.php', GLOB_BRACE) ?: [];

        $this->assertNotEmpty($files, '에러 뷰를 찾지 못했습니다.');

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $name   = basename(dirname($file)) . '/' . basename($file);

            foreach ($banned as $brand) {
                $this->assertStringNotContainsString(
                    $brand,
                    $source,
                    "{$name} 에 다른 제품 이름 '{$brand}' 이 남아 있습니다.",
                );
            }
        }
    }

    /**
     * 에러 페이지는 색을 자체 정의하지 않고 전역 토큰을 따른다.
     * 같은 변수명을 다른 값으로 다시 선언하면 디자인 시스템이 갈라진다.
     */
    public function testErrorStyleDefersToGlobalTokens(): void
    {
        $style = (string) file_get_contents(APPPATH . 'Views/errors/html/_partials/style.php');

        $this->assertMatchesRegularExpression(
            '/var\(--(surface|text|primary|font|border|focus)/',
            $style,
            '에러 페이지 스타일이 전역 토큰을 참조하지 않습니다.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*--(text-strong|text-body|text-muted|primary|font-sans)\s*:/m',
            $style,
            '에러 페이지가 전역 토큰과 같은 이름의 변수를 다시 선언했습니다. '
            . '/css/tokens.css 의 값을 그대로 쓰세요.',
        );
    }

    /**
     * 에러 페이지는 설정·DB 를 읽지 않는다.
     * DB 장애로 난 500 에서 에러 페이지까지 함께 무너지면 안 된다.
     */
    public function testErrorViewsDoNotTouchDatabaseOrSettings(): void
    {
        $files = glob(APPPATH . 'Views/errors/html/{,_partials/}*.php', GLOB_BRACE) ?: [];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $name   = basename(dirname($file)) . '/' . basename($file);

            foreach (['model(', 'setting(', '\\Config\\Database', 'db_connect('] as $call) {
                $this->assertStringNotContainsString(
                    $call,
                    $source,
                    "{$name} 이 «{$call}» 를 호출합니다. 에러 페이지는 외부 의존 없이 떠야 합니다.",
                );
            }
        }
    }
}
