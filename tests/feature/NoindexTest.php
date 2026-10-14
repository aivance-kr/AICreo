<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use Tests\Support\FeatureTestCase;

/**
 * 색인 제어(noindex)·canonical·pagination 규칙 (#201).
 *
 * @internal
 */
final class NoindexTest extends FeatureTestCase
{
    private array $adminSession = [
        'user_id'       => 1,
        'user_nickname' => '관리자',
        'user_role'     => 'admin',
    ];

    public function testLoginPageIsNoindex(): void
    {
        $body = $this->get('auth/login')->getBody();

        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $body);
    }

    public function testRegisterPageIsNoindex(): void
    {
        (new SettingModel())->saveSettings(['signup_enabled' => '1']);

        $body = $this->get('auth/register')->getBody();

        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $body);
    }

    public function testAdminPageIsNoindex(): void
    {
        $body = $this->withSession($this->adminSession)->get('admin/dashboard')->getBody();

        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $body);
    }

    public function testPublicBoardListIsIndexable(): void
    {
        $result = $this->get('board/notice');

        $result->assertStatus(200);
        $this->assertStringContainsString('name="robots" content="index, follow"', $result->getBody());
    }

    public function testBoardListHasSelfCanonical(): void
    {
        $body = $this->get('board/notice')->getBody();

        $this->assertStringContainsString('rel="canonical" href="' . base_url('board/notice'), $body);
    }

    public function testSearchResultsAreNoindex(): void
    {
        $body = $this->get('board/notice?keyword=test')->getBody();

        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $body);
    }

    public function testSecretPostIsNoindex(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $postId  = (new PostModel())->insert([
            'board_id'    => $boardId,
            'user_id'     => 1,
            'title'       => '비밀글',
            'content'     => '비밀 내용',
            'author_name' => '관리자',
            'is_secret'   => 1,
        ], true);

        $body = $this->withSession($this->adminSession)->get('board/notice/' . $postId)->getBody();

        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $body);
    }

    public function testPublicPostViewIsIndexableWithCanonical(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $postId  = (new PostModel())->insert([
            'board_id'    => $boardId,
            'user_id'     => 1,
            'title'       => '공개글',
            'content'     => '공개 내용',
            'author_name' => '관리자',
            'is_secret'   => 0,
        ], true);

        $body = $this->get('board/notice/' . $postId)->getBody();

        $this->assertStringContainsString('name="robots" content="index, follow"', $body);
        $this->assertStringContainsString('rel="canonical" href="' . base_url('board/notice/' . $postId), $body);
    }

    public function testPaginationEmitsRelNext(): void
    {
        $board   = (new BoardModel())->getBySlug('free');
        $perPage = (int) $board['posts_per_page'];
        $model   = new PostModel();

        for ($i = 0; $i <= $perPage; $i++) {
            $model->insert([
                'board_id'    => $board['id'],
                'title'       => 'p' . $i,
                'content'     => 'c',
                'author_name' => '글쓴이' . $i,
            ]);
        }

        $body = $this->get('board/free')->getBody();

        $this->assertStringContainsString('rel="next" href="' . base_url('board/free') . '?page=2', $body);
    }
}
