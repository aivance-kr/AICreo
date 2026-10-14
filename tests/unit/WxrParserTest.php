<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\WordpressImport\WxrParser;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class WxrParserTest extends CIUnitTestCase
{
    private WxrParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WxrParser(SUPPORTPATH . 'wxr/sample.xml');
    }

    public function testSiteLinkReadsChannelLink(): void
    {
        $this->assertSame('https://example.test', $this->parser->siteLink());
    }

    public function testCategoriesIgnoresNonCategoryTaxonomyTerms(): void
    {
        $categories = $this->parser->categories();

        $this->assertCount(4, $categories);
        $this->assertSame('gallery', $categories[0]->nicename);
        $this->assertSame('갤러리', $categories[0]->name);
        $this->assertSame(1, $categories[0]->wpTermId);
        $this->assertTrue($categories[0]->isRoot());
    }

    public function testCategoriesReadsParentNicenameForNestedCategories(): void
    {
        $categories = $this->parser->categories();

        $subGallery = $categories[2];
        $this->assertSame('sub-gallery', $subGallery->nicename);
        $this->assertSame('gallery', $subGallery->parentNicename);
        $this->assertFalse($subGallery->isRoot());

        $deepGallery = $categories[3];
        $this->assertSame('deep-gallery', $deepGallery->nicename);
        $this->assertSame('sub-gallery', $deepGallery->parentNicename);
    }

    public function testNavMenusReadsOnlyNavMenuTaxonomyTerms(): void
    {
        $menus = $this->parser->navMenus();

        $this->assertSame([['nicename' => 'main-menu', 'name' => '메인 메뉴']], $menus);
    }

    public function testNavMenuItemsBuildsTreeOrderedByMenuOrder(): void
    {
        $items = $this->parser->navMenuItems('main-menu');

        $this->assertCount(3, $items);

        $top = $items[0];
        $this->assertSame(400, $top->wpItemId);
        $this->assertSame(0, $top->parentWpItemId);
        $this->assertFalse($top->isCategoryTarget());

        $gallery = $items[1];
        $this->assertSame(401, $gallery->wpItemId);
        $this->assertSame(400, $gallery->parentWpItemId);
        $this->assertTrue($gallery->isCategoryTarget());
        $this->assertSame(1, $gallery->objectId);

        $notice = $items[2];
        $this->assertSame(402, $notice->wpItemId);
        $this->assertSame(2, $notice->objectId);
    }

    public function testNavMenuItemsReturnsEmptyForUnknownMenu(): void
    {
        $this->assertSame([], $this->parser->navMenuItems('no-such-menu'));
    }

    public function testPostsIncludesAllStatusesLeavingFilteringToCaller(): void
    {
        $posts = $this->parser->posts();

        $this->assertCount(3, $posts);

        $publicPost = $posts[0];
        $this->assertSame('publish', $publicPost->status);
        $this->assertSame(['gallery'], $publicPost->categoryNicenames);
        $this->assertSame(42, $publicPost->views);
        $this->assertStringContainsString('wp-content/uploads/2020/01/photo.jpg', $publicPost->content);

        $privatePost = $posts[1];
        $this->assertTrue($privatePost->isPrivate());

        $draftPost = $posts[2];
        $this->assertSame('draft', $draftPost->status);
    }

    public function testPagesReadsMetaDescription(): void
    {
        $pages = $this->parser->pages();

        $this->assertCount(1, $pages);
        $this->assertSame('about', $pages[0]->slug);
        $this->assertSame('소개 페이지 설명', $pages[0]->metaDescription);
    }

    public function testAttachmentsReadsAttachedFilePath(): void
    {
        $attachments = $this->parser->attachments();

        $this->assertCount(1, $attachments);
        $this->assertSame('2020/01/photo.jpg', $attachments[0]->attachedFile);
        $this->assertSame(200, $attachments[0]->wpPostId);
    }

    public function testCommentsExcludesUnapprovedEntries(): void
    {
        $comments = $this->parser->comments();

        $this->assertCount(1, $comments);
        $this->assertSame('방문자', $comments[0]->authorName);
        $this->assertSame(100, $comments[0]->wpPostId);
    }

    public function testIgnoresWoocommerceAndMenuItems(): void
    {
        $wpPostIds = array_map(
            static fn ($post) => $post->wpPostId,
            array_merge($this->parser->posts(), $this->parser->pages()),
        );

        $this->assertNotContains(300, $wpPostIds);
        $this->assertNotContains(400, $wpPostIds);
    }
}
