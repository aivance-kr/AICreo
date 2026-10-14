<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport\Dto;

final readonly class ImportedCategory
{
    public function __construct(
        public int $wpTermId,
        public string $nicename,
        public string $name,
        public string $parentNicename = '',
    ) {
    }

    public function isRoot(): bool
    {
        return $this->parentNicename === '';
    }
}
