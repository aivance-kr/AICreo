<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Filters\VisitLogFilter;
use App\Models\BlockedIpModel;
use App\Models\PageVisitModel;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * 404 응답을 방문 로그에 표시한 뒤 기본 예외 핸들러에 위임한다.
 *
 * 404 는 라우터가 던진 예외로 처리돼 일반 after 필터가 돌지 않으므로 예외 핸들러에서 표시한다.
 * 매칭되는 라우트가 없는 주소는 before 필터도 돌지 않아 여기서 방문 로그를 직접 남긴다.
 * 스캐너 전형 경로(`*.php`, `wp-admin`, `.env` 등)를 404 로 두드린 IP 는 일배치를 기다리지 않고 즉시 차단한다.
 */
final class NotFoundVisitExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * 이 사이트에 존재하지 않는 서버 스크립트·WordPress·비밀 파일 탐색 경로.
     * 이미지 등 정적 파일(wp-content/uploads/*.jpg)은 옛 글의 깨진 링크일 수 있어 포함하지 않는다.
     */
    private const SCANNER_PATH_PATTERN = '~(\.(php\d?|phtml|phar|asp|aspx|jsp|cgi)$|(^|/)(wp-admin|wp-login|xmlrpc|\.env|\.git)(/|\.|$))~i';

    public static function isScannerPath(string $path): bool
    {
        return preg_match(self::SCANNER_PATH_PATTERN, trim($path, '/')) === 1;
    }

    public function __construct(private readonly ExceptionHandlerInterface $inner)
    {
    }

    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if ($statusCode === 404 && $request instanceof IncomingRequest) {
            if (self::isScannerPath($request->getPath())) {
                (new BlockedIpModel())->block($request->getIPAddress(), BlockedIpModel::REASON_AUTO);
            }

            $visitId = VisitLogFilter::$currentVisitId ?? VisitLogFilter::record($request);

            if ($visitId !== null) {
                (new PageVisitModel())->markNotFound($visitId);
            }
        }

        $this->inner->handle($exception, $request, $response, $statusCode, $exitCode);
    }
}
