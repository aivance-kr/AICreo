<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * user_id 스코프를 결정할 수 없는 상태에서 스코프 모델을 사용했을 때 던진다.
 *
 * 조용히 전역 조회로 흘러가는 것이 이 예외가 막으려는 사고 그 자체다 —
 * 다른 공방의 견적서가 노출되는 경로가 정확히 그것이다.
 */
final class UserScopeMissingException extends RuntimeException
{
    public static function create(string $table): self
    {
        return new self(
            "'{$table}' 는 사용자 스코프 테이블입니다. 로그인 세션이 없다면 "
            . 'forUser(int $userId) 로 스코프를 명시하거나 withoutUserScope() 를 사용하세요.',
        );
    }
}
