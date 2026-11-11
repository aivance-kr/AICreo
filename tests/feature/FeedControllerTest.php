<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Seo\LocaleCache;
use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class FeedControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LocaleCache::clearFeed();
    }

    public function testFeedReturnsRssXml(): void
    {
        $result = $this->get('feed');

        $result->assertStatus(200);
        $this->assertStringContainsString('application/rss+xml', $result->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<rss', (string) $result->response()->getBody());
        $this->assertStringContainsString('<channel>', (string) $result->response()->getBody());
    }

    public function testRssAliasReturnsSameFeed(): void
    {
        $result = $this->get('rss');

        $result->assertStatus(200);
        $this->assertStringContainsString('<rss', (string) $result->response()->getBody());
    }

    public function testFeedIncludesPublicPost(): void
    {
        $boardId = (new BoardModel())->insert([
            'slug'             => 'feed-board',
            'name'             => '피드 게시판',
            'read_permission'  => 'guest',
            'write_permission' => 'guest',
            'is_active'        => 1,
        ], true);

        (new PostModel())->insert([
            'board_id'    => $boardId,
            'title'       => '피드 노출 글',
            'content'     => '<p>본문 내용입니다.</p>',
            'author_name' => '작성자',
            'is_secret'   => 0,
        ]);
        LocaleCache::clearFeed();

        $body = (string) $this->get('feed')->response()->getBody();

        $this->assertStringContainsString('피드 노출 글', $body);
        $this->assertStringContainsString(base_url('board/feed-board/'), $body);
    }

    public function testFeedItemHasBoardAndCategoryWithDomain(): void
    {
        $boardId = (new BoardModel())->insert([
            'slug'             => 'feed-cat-board',
            'name'             => '피드 분류 게시판',
            'read_permission'  => 'guest',
            'write_permission' => 'guest',
            'is_active'        => 1,
        ], true);
        $categoryId = (new BoardCategoryModel())->insert([
            'board_id' => $boardId,
            'slug'     => 'tips',
            'name'     => '팁&노하우',
        ], true);

        $postModel = new PostModel();
        $postModel->insert([
            'board_id'    => $boardId,
            'category_id' => $categoryId,
            'title'       => '분류 있는 글',
            'content'     => '<p>본문</p>',
            'author_name' => '작성자',
            'is_secret'   => 0,
        ]);
        $postModel->insert([
            'board_id'    => $boardId,
            'title'       => '분류 없는 글',
            'content'     => '<p>본문</p>',
            'author_name' => '작성자',
            'is_secret'   => 0,
        ]);
        LocaleCache::clearFeed();

        $body = (string) $this->get('feed')->response()->getBody();

        $this->assertStringContainsString('<category domain="board">피드 분류 게시판</category>', $body);
        $this->assertStringContainsString('<category domain="board-category">팁&amp;노하우</category>', $body);
        // 분류 없는 글은 게시판 태그만 — 카테고리 태그는 분류 있는 글 하나뿐
        $this->assertSame(1, substr_count($body, 'domain="board-category"'));
        $this->assertSame(2, substr_count($body, 'domain="board"'));
    }

    public function testFeedExcludesSecretPost(): void
    {
        $boardId = (new BoardModel())->insert([
            'slug'             => 'feed-board-secret',
            'name'             => '피드 비밀 게시판',
            'read_permission'  => 'guest',
            'write_permission' => 'guest',
            'is_active'        => 1,
        ], true);

        (new PostModel())->insert([
            'board_id'    => $boardId,
            'title'       => '피드 비밀 글',
            'content'     => '<p>비밀 내용</p>',
            'author_name' => '작성자',
            'is_secret'   => 1,
        ]);
        LocaleCache::clearFeed();

        $body = (string) $this->get('feed')->response()->getBody();

        $this->assertStringNotContainsString('피드 비밀 글', $body);
    }
}
