<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport;

use App\Libraries\WordpressImport\Dto\ImportedAttachment;
use App\Libraries\WordpressImport\Dto\ImportedCategory;
use App\Libraries\WordpressImport\Dto\ImportedComment;
use App\Libraries\WordpressImport\Dto\ImportedContent;
use App\Libraries\WordpressImport\Dto\ImportedContentType;
use App\Libraries\WordpressImport\Dto\ImportedMenuItem;
use RuntimeException;
use SimpleXMLElement;

/**
 * 워드프레스 WXR(export XML) 파서.
 *
 * 이관 대상 아닌 post_type(우커머스 상품·플러그인 잔재 등)은 posts()/pages()/attachments()
 * 에서 처음부터 걸러낸다 — 호출부가 따로 필터링할 필요 없음. nav_menu_item만 예외로
 * navMenuItems()가 별도 취급 — 게시판/카테고리 구조를 결정하는 데 쓰인다.
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
     * wp:term(taxonomy=nav_menu)에 등록된 워드프레스 메뉴 목록 — 게시판 이관 대상 메뉴 선택용.
     *
     * @return list<array{nicename: string, name: string}>
     */
    public function navMenus(): array
    {
        $result = [];

        foreach ($this->channel()->children(self::NS_WP)->term as $term) {
            $wp = $term->children(self::NS_WP);
            if ((string) $wp->term_taxonomy !== 'nav_menu') {
                continue;
            }

            $result[] = [
                'nicename' => (string) $wp->term_slug,
                'name'     => (string) $wp->term_name,
            ];
        }

        return $result;
    }

    /**
     * 지정한 메뉴(nicename)에 속한 항목 전부 — wp:menu_order 오름차순.
     *
     * @return list<ImportedMenuItem>
     */
    public function navMenuItems(string $menuNicename): array
    {
        $result = [];

        foreach ($this->channel()->item as $item) {
            $wp = $item->children(self::NS_WP);
            if ((string) $wp->post_type !== 'nav_menu_item') {
                continue;
            }

            $belongsToMenu = false;

            foreach ($item->category as $category) {
                $attrs = $category->attributes();
                if ((string) $attrs['domain'] === 'nav_menu' && (string) $attrs['nicename'] === $menuNicename) {
                    $belongsToMenu = true;

                    break;
                }
            }

            if (! $belongsToMenu) {
                continue;
            }

            $meta = [];

            foreach ($wp->postmeta as $postmeta) {
                $meta[(string) $postmeta->meta_key] = (string) $postmeta->meta_value;
            }

            $result[] = new ImportedMenuItem(
                wpItemId: (int) $wp->post_id,
                parentWpItemId: (int) ($meta['_menu_item_menu_item_parent'] ?? 0),
                menuOrder: (int) $wp->menu_order,
                title: (string) $item->title,
                objectType: $meta['_menu_item_object'] ?? '',
                objectId: (int) ($meta['_menu_item_object_id'] ?? 0),
            );
        }

        usort($result, static fn (ImportedMenuItem $a, ImportedMenuItem $b): int => $a->menuOrder <=> $b->menuOrder);

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
