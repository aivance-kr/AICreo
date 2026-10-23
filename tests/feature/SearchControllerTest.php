<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardModel;
use App\Models\PostModel;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class SearchControllerTest extends FeatureTestCase
{
    public function testSearchShowsMatchingPostsOnly(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $model   = new PostModel();
        $model->insert(['board_id' => $boardId, 'title' => '검색대상 글', 'content' => '본문', 'author_name' => '관리자']);
        $model->insert(['board_id' => $boardId, 'title' => '다른 글', 'content' => '본문', 'author_name' => '관리자']);

        $body = html_entity_decode($this->get('/search?q=검색대상')->getBody(), ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('검색대상 글', $body);
        $this->assertStringNotContainsString('다른 글', $body);
        $this->assertStringContainsString('noindex', $body);
    }

    public function testSearchWithoutKeywordShowsNoResults(): void
    {
        $body = html_entity_decode($this->get('/search')->getBody(), ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('검색어를 입력', $body);
    }

    public function testHomeHasSearchForm(): void
    {
        $this->assertStringContainsString('action="/search"', $this->get('/')->getBody());
    }
}
