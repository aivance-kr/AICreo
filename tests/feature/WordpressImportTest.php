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
}
