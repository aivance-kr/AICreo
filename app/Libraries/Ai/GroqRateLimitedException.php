<?php

declare(strict_types=1);

namespace App\Libraries\Ai;

use RuntimeException;

/**
 * Groq 가 429(호출 한도 초과)로 응답했을 때. Retry-After 가 있으면 초 단위로 담는다.
 */
class GroqRateLimitedException extends RuntimeException
{
    public function __construct(public readonly ?int $retryAfter = null)
    {
        parent::__construct('HTTP 429');
    }
}
