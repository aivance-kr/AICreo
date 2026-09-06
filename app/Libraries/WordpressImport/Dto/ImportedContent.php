<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport\Dto;

final readonly class ImportedContent
{
    /**
     * @param list<string> $categoryNicenames
     */
    public function __construct(
        public ImportedContentType $type,
        public int $wpPostId,
        public string $title,
        public string $slug,
        public string $content,
        public string $status,
        public string $postDate,
        public string $authorLogin,
        public array $categoryNicenames,
        public ?string $metaDescription,
        public int $views,
        public string $link,
    ) {
    }

    public function isPrivate(): bool
    {
        return $this->status === 'private';
    }
}
