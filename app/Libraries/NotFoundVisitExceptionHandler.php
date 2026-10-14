<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Filters\VisitLogFilter;
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
 */
final class NotFoundVisitExceptionHandler implements ExceptionHandlerInterface
{
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
            $visitId = VisitLogFilter::$currentVisitId ?? VisitLogFilter::record($request);

            if ($visitId !== null) {
                (new PageVisitModel())->markNotFound($visitId);
            }
        }

        $this->inner->handle($exception, $request, $response, $statusCode, $exitCode);
    }
}
