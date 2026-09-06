<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport\Dto;

final readonly class ImportedAttachment
{
    public function __construct(
        public int $wpPostId,
        public string $title,
        public string $attachmentUrl,
        public string $attachedFile,
        public string $postDate,
    ) {
    }
}
