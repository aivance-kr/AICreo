<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport\Dto;

final readonly class ImportedMenuItem
{
    public function __construct(
        public int $wpItemId,
        public int $parentWpItemId,
        public int $menuOrder,
        public string $title,
        public string $objectType,
        public int $objectId,
    ) {
    }

    public function isCategoryTarget(): bool
    {
        return $this->objectType === 'category';
    }
}
