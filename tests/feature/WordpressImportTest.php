<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\WordpressImport\WordpressImporter;
use App\Libraries\WordpressImport\WxrParser;
use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use Tests\Support\FeatureTestCase;

/**
 * (#286) 워드프레스 마이그레이션은 워드프레스 메뉴(외모 > 메뉴) 구조에서 게시판을 뽑는다 —
 * 메뉴 최상위 항목 1개가 게시판 1개, 그 하위 항목(카테고리 대상만)이 게시판 카테고리다.
 *
 * @internal
 */
final class WordpressImportTest extends FeatureTestCase
{
    private WxrParser $parser;
    private WordpressImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser   = new WxrParser(SUPPORTPATH . 'wxr/sample.xml');
        $this->importer = new WordpressImporter();
    }

    public function testImportMenuAsBoardsCreatesBoardFromTopLevelItemAndCategoriesFromChildren(): void
    {
        $routing = $this->importer->importMenuAsBoards($this->parser, 'main-menu');

        $this->assertArrayHasKey('gallery', $routing);
        $this->assertArrayHasKey('notice', $routing);
        $this->assertSame($routing['gallery']['boardId'], $routing['notice']['boardId']);
        $this->assertNotSame($routing['gallery']['categoryId'], $routing['notice']['categoryId']);

        $board = (new BoardModel())->find($routing['gallery']['boardId']);
        $this->assertNotNull($board);
        $this->assertSame('커뮤니티', $board['name']);
        $this->assertSame(400, (int) $board['wp_term_id']);

        $category = (new BoardCategoryModel())->find($routing['gallery']['categoryId']);
        $this->assertNotNull($category);
        $this->assertSame($routing['gallery']['boardId'], (int) $category['board_id']);
        $this->assertSame('gallery', $category['slug']);
        $this->assertSame('갤러리', $category['name']);
    }

    public function testImportMenuAsBoardsIsIdempotent(): void
    {
        $first  = $this->importer->importMenuAsBoards($this->parser, 'main-menu');
        $second = $this->importer->importMenuAsBoards($this->parser, 'main-menu');

        $this->assertSame($first, $second);
        $this->assertSame(1, (new BoardModel())->where('wp_term_id', 400)->countAllResults());
    }

    public function testImportMenuAsBoardsWarnsAndReturnsEmptyForUnknownMenu(): void
    {
        $routing = $this->importer->importMenuAsBoards($this->parser, 'no-such-menu');

        $this->assertSame([], $routing);
        $this->assertNotEmpty($this->importer->warnings());
    }

    public function testImportPagesAndPostsAssignsMappedBoardAndCategoryToPublishedPost(): void
    {
        $routing = $this->importer->importMenuAsBoards($this->parser, 'main-menu');

        $this->importer->importPagesAndPosts($this->parser, $routing);

        $post = (new PostModel())->where('wp_post_id', 100)->first();
        $this->assertNotNull($post);
        $this->assertSame($routing['gallery']['boardId'], (int) $post['board_id']);
        $this->assertSame($routing['gallery']['categoryId'], (int) $post['category_id']);
    }

    public function testImportCategoryHierarchyAsBoardsCreatesBoardPerRootAndFlattensDescendants(): void
    {
        $routing = $this->importer->importCategoryHierarchyAsBoards($this->parser);

        // gallery(루트) 아래 sub-gallery -> deep-gallery 2단 중첩도 같은 게시판의 형제 카테고리로 평탄화
        $this->assertSame($routing['gallery']['boardId'], $routing['sub-gallery']['boardId']);
        $this->assertSame($routing['gallery']['boardId'], $routing['deep-gallery']['boardId']);
        $this->assertNull($routing['gallery']['categoryId']);
        $this->assertNotNull($routing['sub-gallery']['categoryId']);
        $this->assertNotNull($routing['deep-gallery']['categoryId']);

        // notice는 자식 없는 루트 카테고리 — 게시판만 생기고 카테고리는 없다
        $this->assertNotSame($routing['gallery']['boardId'], $routing['notice']['boardId']);
        $this->assertNull($routing['notice']['categoryId']);

        $board = (new BoardModel())->find($routing['gallery']['boardId']);
        $this->assertNotNull($board);
        $this->assertSame('gallery', $board['slug']);
        $this->assertSame(1, (int) $board['wp_term_id']);
    }

    public function testImportCategoryHierarchyAsBoardsIsIdempotent(): void
    {
        $first  = $this->importer->importCategoryHierarchyAsBoards($this->parser);
        $second = $this->importer->importCategoryHierarchyAsBoards($this->parser);

        $this->assertSame($first, $second);
        $this->assertSame(1, (new BoardModel())->where('wp_term_id', 1)->countAllResults());
    }

    public function testImportPagesAndPostsSkipsPostsWithNoMatchingBoardAndWarns(): void
    {
        $result = $this->importer->importPagesAndPosts($this->parser, []);

        $post = (new PostModel())->where('wp_post_id', 100)->first();
        $this->assertNull($post);
        $this->assertSame(3, $result['skipped']);
        $this->assertNotEmpty($this->importer->warnings());

        // 페이지는 게시판과 무관하므로 매핑 없이도 이관된다.
        $this->assertSame(1, $result['pages']);
    }

    public function testRewriteContentImagesFallsBackToBasenameWhenFolderPathDiffers(): void
    {
        // 실제 첨부파일은 2011/06/x.jpg인데 본문엔 예전 플랫폼에서 이관하며 남은
        // /wp-content/uploads/1/x.jpg처럼 다른 폴더로 적혀 있는 경우 — 정확한
        // 상대경로 매칭은 실패하지만 파일명 기준 폴백으로 잡아낸다.
        $boardId = (int) (new BoardModel())->first()['id'];
        $postId  = (new PostModel())->insert([
            'wp_post_id'  => 999,
            'board_id'    => $boardId,
            'title'       => '옛 경로 글',
            'content'     => '<img src="/wp-content/uploads/1/1388310556.jpg">',
            'author_name' => 'tester',
            'created_at'  => '2011-06-01 00:00:00',
        ], true);

        $updated = $this->importer->rewriteContentImages([
            '2011/06/1388310556.jpg' => 'uploads/imported/2011/06/1388310556.jpg',
        ]);

        $this->assertSame(1, $updated);
        $post = (new PostModel())->find($postId);
        $this->assertStringContainsString('uploads/imported/2011/06/1388310556.jpg', $post['content']);
    }

    public function testRewriteContentImagesFallsBackToOriginalFilenameForWordpressThumbnailSizes(): void
    {
        // 본문 <img>가 워드프레스 자동 생성 리사이즈본(photo-300x225.jpg)을 가리키는데
        // 이 파일은 WXR에 별도 첨부파일로 없다(원본 첨부파일 메타데이터의 사이즈
        // 정보로만 존재) — 원본 파일명으로 정규화해서 찾아야 한다.
        $boardId = (int) (new BoardModel())->first()['id'];
        $postId  = (new PostModel())->insert([
            'wp_post_id'  => 997,
            'board_id'    => $boardId,
            'title'       => '썸네일 크기 글',
            'content'     => '<img src="/wp-content/uploads/2016/11/동아리수업-300x225.jpg">',
            'author_name' => 'tester',
            'created_at'  => '2016-11-01 00:00:00',
        ], true);

        $updated = $this->importer->rewriteContentImages([
            '2016/11/동아리수업.jpg' => 'uploads/imported/2016/11/동아리수업.jpg',
        ]);

        $this->assertSame(1, $updated);
        $post = (new PostModel())->find($postId);
        $this->assertStringContainsString('uploads/imported/2016/11/동아리수업.jpg', $post['content']);
    }

    public function testRewriteContentImagesResolvesThumbnailByFolderEvenWhenBasenameCollidesSitewide(): void
    {
        // "8.jpg"처럼 흔한 파일명은 사이트 전체 여러 글에 걸쳐 겹칠 수 있다 — 폴더까지
        // 포함해 사이즈 접미사만 제거한 상대경로로 먼저 찾으면, 같은 파일명이 다른
        // 폴더에도 있어도(모호한 basename 폴백까지 갈 필요 없이) 정확히 매칭된다.
        $boardId = (int) (new BoardModel())->first()['id'];
        $postId  = (new PostModel())->insert([
            'wp_post_id'  => 996,
            'board_id'    => $boardId,
            'title'       => '흔한 파일명 글',
            'content'     => '<img src="/wp-content/uploads/2016/11/8-225x300.jpg">',
            'author_name' => 'tester',
            'created_at'  => '2016-11-01 00:00:00',
        ], true);

        $updated = $this->importer->rewriteContentImages([
            '2016/11/8.jpg' => 'uploads/imported/2016/11/8.jpg',
            '2013/05/8.jpg' => 'uploads/imported/2013/05/8.jpg',
        ]);

        $this->assertSame(1, $updated);
        $post = (new PostModel())->find($postId);
        $this->assertStringContainsString('uploads/imported/2016/11/8.jpg', $post['content']);
        $this->assertStringNotContainsString('2013/05', $post['content']);
    }

    public function testRewriteContentImagesSkipsAmbiguousBasenameAcrossMultipleAttachments(): void
    {
        $boardId = (int) (new BoardModel())->first()['id'];
        $postId  = (new PostModel())->insert([
            'wp_post_id'  => 998,
            'board_id'    => $boardId,
            'title'       => '중복 파일명 글',
            'content'     => '<img src="/wp-content/uploads/1/dup.jpg">',
            'author_name' => 'tester',
            'created_at'  => '2011-06-01 00:00:00',
        ], true);

        $updated = $this->importer->rewriteContentImages([
            '2011/dup.jpg' => 'uploads/imported/2011/dup.jpg',
            '2012/dup.jpg' => 'uploads/imported/2012/dup.jpg',
        ]);

        $this->assertSame(0, $updated);
        $post = (new PostModel())->find($postId);
        $this->assertStringContainsString('/wp-content/uploads/1/dup.jpg', $post['content']);
    }
}
