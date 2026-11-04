<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * plugin.json 매니페스트가 스키마를 위반할 때 던진다.
 */
final class InvalidManifestException extends RuntimeException
{
    public static function forField(string $field, string $reason): self
    {
        return new self("plugin.json 의 '{$field}' 항목이 올바르지 않습니다: {$reason}");
    }
}
