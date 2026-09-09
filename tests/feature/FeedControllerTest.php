<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        cache()->delete('seo_feed');
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
        cache()->delete('seo_feed');

        $body = (string) $this->get('feed')->response()->getBody();

        $this->assertStringContainsString('피드 노출 글', $body);
        $this->assertStringContainsString(base_url('board/feed-board/'), $body);
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
        cache()->delete('seo_feed');

        $body = (string) $this->get('feed')->response()->getBody();

        $this->assertStringNotContainsString('피드 비밀 글', $body);
    }
}
