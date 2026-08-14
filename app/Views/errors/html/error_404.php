<?php
/**
 * 404 Not Found
 *
 * CodeIgniter4 는 errors/html/error_{상태코드}.php 가 있으면 자동으로 이 파일을
 * 쓴다(ExceptionHandler::determineView). 별도 매핑 설정은 필요하지 않다.
 *
 * $code, $message 는 프레임워크가 넘겨준다. $message 는 $exception->getMessage()
 * 원문이라 SQL 조각·파일 경로·클래스명이 섞일 수 있으므로 운영에서는 내보내지
 * 않는다 — CI4 기본 production.php 가 지키던 선을 그대로 유지한다.
 */
$errCode    = $code ?? 404;
$errHeading = '페이지를 찾을 수 없습니다';
$errMessage = (ENVIRONMENT !== 'production' && ! empty($message))
    ? $message
    : '주소를 다시 확인하시거나 홈으로 이동해 주세요.';

include __DIR__ . '/_partials/page.php';
