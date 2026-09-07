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
 * #282(게시판 카테고리) 이후 워드프레스 마이그레이션은 카테고리마다 별도 게시판을
 * 만들지 않고, 사전에 존재하는 대상 게시판 아래 board_categories로 매핑한다.
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

    private function boardId(string $slug): int
    {
        return (int) (new BoardModel())->getBySlug($slug)['id'];
    }

    public function testImportCategoriesCreatesBoardCategoryUnderTargetBoard(): void
    {
        $boardId = $this->boardId('free');

        $map = $this->importer->importCategories($this->parser, $boardId);

        $this->assertSame(['gallery' => $map['gallery']], $map);

        $category = (new BoardCategoryModel())->find($map['gallery']);
        $this->assertNotNull($category);
        $this->assertSame($boardId, (int) $category['board_id']);
        $this->assertSame(1, (int) $category['wp_term_id']);
        $this->assertSame('gallery', $category['slug']);
        $this->assertSame('갤러리', $category['name']);
    }

    public function testImportCategoriesIsIdempotentPerBoard(): void
    {
        $boardId = $this->boardId('free');

        $first  = $this->importer->importCategories($this->parser, $boardId);
        $second = $this->importer->importCategories($this->parser, $boardId);

        $this->assertSame($first, $second);
        $this->assertSame(1, (new BoardCategoryModel())
            ->where('board_id', $boardId)
            ->where('wp_term_id', 1)
            ->countAllResults());
    }

    public function testDifferentBoardsGetIndependentCategoryRows(): void
    {
        $freeId = $this->boardId('free');
        $qnaId  = $this->boardId('qna');

        $freeMap = $this->importer->importCategories($this->parser, $freeId);
        $qnaMap  = $this->importer->importCategories($this->parser, $qnaId);

        $this->assertNotSame($freeMap['gallery'], $qnaMap['gallery']);
    }

    public function testImportPagesAndPostsAssignsMappedCategoryToPublishedPost(): void
    {
        $boardId     = $this->boardId('free');
        $categoryMap = $this->importer->importCategories($this->parser, $boardId);

        $this->importer->importPagesAndPosts($this->parser, $boardId, $categoryMap);

        $post = (new PostModel())->where('wp_post_id', 100)->first();
        $this->assertNotNull($post);
        $this->assertSame($boardId, (int) $post['board_id']);
        $this->assertSame($categoryMap['gallery'], (int) $post['category_id']);
    }

    public function testImportPagesAndPostsLeavesCategoryNullAndWarnsWhenUnmapped(): void
    {
        $boardId = $this->boardId('free');

        $this->importer->importPagesAndPosts($this->parser, $boardId, []);

        $post = (new PostModel())->where('wp_post_id', 100)->first();
        $this->assertNotNull($post);
        $this->assertSame($boardId, (int) $post['board_id']);
        $this->assertNull($post['category_id']);
        $this->assertNotEmpty($this->importer->warnings());
    }
}
