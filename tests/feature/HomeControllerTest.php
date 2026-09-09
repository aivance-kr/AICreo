<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardModel;
use App\Models\PostModel;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class HomeControllerTest extends FeatureTestCase
{
    public function testHomeNoticeWidgetExcludesHiddenPosts(): void
    {
        $boardId   = (int) (new BoardModel())->getBySlug('notice')['id'];
        $postModel = new PostModel();

        $postModel->insert([
            'board_id'    => $boardId,
            'title'       => '노출 공지',
            'content'     => '본문',
            'author_name' => '관리자',
        ]);
        $postModel->insert([
            'board_id'    => $boardId,
            'title'       => '숨김 공지',
            'content'     => '본문',
            'author_name' => '관리자',
            'is_active'   => 0,
        ]);

        $body = html_entity_decode($this->get('/')->getBody(), ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('노출 공지', $body);
        $this->assertStringNotContainsString('숨김 공지', $body);
    }
}
