<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport\Dto;

final readonly class ImportedComment
{
    public function __construct(
        public int $wpCommentId,
        public int $wpPostId,
        public string $authorName,
        public string $content,
        public string $ipAddress,
        public string $date,
    ) {
    }
}
