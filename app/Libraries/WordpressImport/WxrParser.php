<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport;

use App\Libraries\WordpressImport\Dto\ImportedAttachment;
use App\Libraries\WordpressImport\Dto\ImportedCategory;
use App\Libraries\WordpressImport\Dto\ImportedComment;
use App\Libraries\WordpressImport\Dto\ImportedContent;
use App\Libraries\WordpressImport\Dto\ImportedContentType;
use RuntimeException;
use SimpleXMLElement;

/**
 * 워드프레스 WXR(export XML) 파서.
 *
 * 이관 대상 아닌 post_type(우커머스 상품·nav_menu_item·플러그인 잔재 등)은
 * 처음부터 결과에서 걸러낸다 — 호출부가 따로 필터링할 필요 없음.
 */
final class WxrParser
{
    private const NS_WP      = 'http://wordpress.org/export/1.2/';
    private const NS_CONTENT = 'http://purl.org/rss/1.0/modules/content/';
    private const NS_DC      = 'http://purl.org/dc/elements/1.1/';

    /**
     * 이관 대상 post_type만 허용 — 그 외(우커머스 상품, 플러그인 잔재 등)는 통째로 무시
     */
    private const ALLOWED_POST_TYPES = ['post', 'page', 'attachment'];

    private const CATEGORY_TAXONOMY = 'category';

    private ?SimpleXMLElement $xml = null;

    public function __construct(private readonly string $filePath)
    {
    }

    public function siteLink(): string
    {
        return rtrim((string) $this->channel()->link, '/');
    }

    /**
     * @return list<ImportedCategory>
     */
    public function categories(): array
    {
        $result = [];

        foreach ($this->channel()->children(self::NS_WP)->category as $category) {
            $wp = $category->children(self::NS_WP);

            $result[] = new ImportedCategory(
                wpTermId: (int) $wp->term_id,
                nicename: (string) $wp->category_nicename,
                name: (string) $wp->cat_name,
            );
        }

        return $result;
    }

    /**
     * @return list<ImportedContent>
     */
    public function posts(): array
    {
        return $this->contents(ImportedContentType::Post);
    }

    /**
     * @return list<ImportedContent>
     */
    public function pages(): array
    {
        return $this->contents(ImportedContentType::Page);
    }

    /**
     * @return list<ImportedAttachment>
     */
    public function attachments(): array
    {
        $result = [];

        foreach ($this->items() as $item) {
            $wp = $item->children(self::NS_WP);
            if ((string) $wp->post_type !== 'attachment') {
                continue;
            }

            $attachmentUrl = (string) $wp->attachment_url;
            if ($attachmentUrl === '') {
                continue;
            }

            $attachedFile = '';

            foreach ($wp->postmeta as $meta) {
                if ((string) $meta->meta_key === '_wp_attached_file') {
                    $attachedFile = (string) $meta->meta_value;

                    break;
                }
            }

            $result[] = new ImportedAttachment(
                wpPostId: (int) $wp->post_id,
                title: (string) $item->title,
                attachmentUrl: $attachmentUrl,
                attachedFile: $attachedFile,
                postDate: (string) $wp->post_date,
            );
        }

        return $result;
    }

    /**
     * post/page 본문에 달린 댓글 전체 — wp:comment_approved='1'만.
     *
     * @return list<ImportedComment>
     */
    public function comments(): array
    {
        $result = [];

        foreach ($this->items() as $item) {
            $wp       = $item->children(self::NS_WP);
            $postType = (string) $wp->post_type;
            if ($postType !== 'post' && $postType !== 'page') {
                continue;
            }

            $wpPostId = (int) $wp->post_id;

            foreach ($wp->comment as $comment) {
                if ((string) $comment->comment_approved !== '1') {
                    continue;
                }

                $result[] = new ImportedComment(
                    wpCommentId: (int) $comment->comment_id,
                    wpPostId: $wpPostId,
                    authorName: (string) $comment->comment_author,
                    content: (string) $comment->comment_content,
                    ipAddress: (string) $comment->comment_author_IP,
                    date: (string) $comment->comment_date,
                );
            }
        }

        return $result;
    }

    /**
     * @return list<ImportedContent>
     */
    private function contents(ImportedContentType $type): array
    {
        $result = [];

        foreach ($this->items() as $item) {
            $wp = $item->children(self::NS_WP);
            if ((string) $wp->post_type !== $type->value) {
                continue;
            }

            $categoryNicenames = [];

            foreach ($item->category as $category) {
                $attrs = $category->attributes();
                if ((string) $attrs['domain'] === self::CATEGORY_TAXONOMY) {
                    $categoryNicenames[] = (string) $attrs['nicename'];
                }
            }

            $views    = 0;
            $metaDesc = null;

            foreach ($wp->postmeta as $meta) {
                $key   = (string) $meta->meta_key;
                $value = (string) $meta->meta_value;
                if ($key === 'post_views' && $value !== '') {
                    $views = (int) $value;
                } elseif ($key === '_yoast_wpseo_metadesc' && $value !== '') {
                    $metaDesc = $value;
                }
            }

            $content = $item->children(self::NS_CONTENT);

            $result[] = new ImportedContent(
                type: $type,
                wpPostId: (int) $wp->post_id,
                title: (string) $item->title,
                slug: (string) $wp->post_name,
                content: (string) $content->encoded,
                status: (string) $wp->status,
                postDate: (string) $wp->post_date,
                authorLogin: (string) $item->children(self::NS_DC)->creator,
                categoryNicenames: $categoryNicenames,
                metaDescription: $metaDesc,
                views: $views,
                link: (string) $item->link,
            );
        }

        return $result;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function items(): array
    {
        $result = [];

        foreach ($this->channel()->item as $item) {
            $postType = (string) $item->children(self::NS_WP)->post_type;
            if (! in_array($postType, self::ALLOWED_POST_TYPES, true)) {
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    private function channel(): SimpleXMLElement
    {
        if ($this->xml === null) {
            if (! is_file($this->filePath)) {
                throw new RuntimeException("WXR 파일을 찾을 수 없습니다: {$this->filePath}");
            }

            $xml = simplexml_load_file($this->filePath, SimpleXMLElement::class, LIBXML_NOCDATA);
            if ($xml === false) {
                throw new RuntimeException("WXR 파일 파싱에 실패했습니다: {$this->filePath}");
            }

            $this->xml = $xml;
        }

        return $this->xml->channel;
    }
}
