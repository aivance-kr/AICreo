<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class BoardControllerTest extends FeatureTestCase
{
    private function boardId(string $slug): int
    {
        return (int) (new BoardModel())->getBySlug($slug)['id'];
    }

    public function testListPageLoads(): void
    {
        $this->get('board/notice')->assertStatus(200);
    }

    public function testUnknownBoardThrowsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->get('board/does-not-exist');
    }

    public function testGuestCannotOpenAdminOnlyWriteForm(): void
    {
        // notice 게시판은 write_permission = admin
        $result = $this->get('board/notice/write');

        $result->assertRedirectTo('/auth/login');
    }

    public function testGuestCanOpenGuestWriteForm(): void
    {
        // qna 게시판은 write_permission = guest
        $this->get('board/qna/write')->assertStatus(200);
    }

    public function testGuestCanCreatePostOnGuestBoard(): void
    {
        $result = $this->post('board/qna/write', [
            'title'           => '게스트 문의',
            'content'         => '문의 내용입니다.',
            'author_name'     => '비회원',
            'author_password' => '1234',
        ]);

        $result->assertRedirect();
        $this->assertSame(1, (new PostModel())
            ->where('board_id', $this->boardId('qna'))
            ->where('title', '게스트 문의')
            ->countAllResults());
    }

    public function testGuestPostRequiresTitle(): void
    {
        $result = $this->post('board/qna/write', [
            'title'           => '',
            'content'         => '제목 없는 글',
            'author_name'     => '비회원',
            'author_password' => '1234',
        ]);

        $result->assertRedirect();
        $this->assertSame(0, (new PostModel())
            ->where('content', '제목 없는 글')
            ->countAllResults());
    }

    public function testMemberCanCreatePostOnMemberBoard(): void
    {
        // free 게시판은 write_permission = member
        $result = $this->withSession([
            'user_id'       => 1,
            'user_nickname' => '관리자',
            'user_role'     => 'admin',
        ])->post('board/free/write', [
            'title'   => '회원 글',
            'content' => '회원만 쓸 수 있는 글',
        ]);

        $result->assertRedirect();
        $this->assertSame(1, (new PostModel())
            ->where('board_id', $this->boardId('free'))
            ->where('title', '회원 글')
            ->countAllResults());
    }

    public function testViewIncrementsViewCount(): void
    {
        $postModel = new PostModel();
        $postId    = (int) $postModel->insert([
            'board_id'    => $this->boardId('qna'),
            'title'       => '조회수 글',
            'content'     => '본문',
            'author_name' => '작성자',
            'is_notice'   => 0,
        ]);

        $this->get("board/qna/{$postId}")->assertStatus(200);

        $this->assertSame(1, (int) $postModel->find($postId)['views']);
    }

    public function testViewUsesDedicatedContentLayoutClass(): void
    {
        $postModel = new PostModel();
        $postId    = (int) $postModel->insert([
            'board_id'    => $this->boardId('qna'),
            'title'       => '본문 표시 글',
            'content'     => "첫 번째 줄\n<img src=\"/uploads/example.jpg\" alt=\"예시\">\n마지막 줄",
            'author_name' => '작성자',
            'is_notice'   => 0,
        ]);

        $body = $this->get("board/qna/{$postId}")->getBody();

        $this->assertStringContainsString('class="post-content board-post-content"', $body);
        $this->assertStringContainsString('<img src="/uploads/example.jpg"', $body);
    }

    public function testViewShowsPreviousAndNextPostLinks(): void
    {
        $postModel = new PostModel();
        $boardId   = $this->boardId('qna');

        $prevId = (int) $postModel->insert(['board_id' => $boardId, 'title' => '이전 글입니다', 'content' => '본문', 'is_notice' => 0]);
        $postId = (int) $postModel->insert(['board_id' => $boardId, 'title' => '현재 글입니다', 'content' => '본문', 'is_notice' => 0]);
        $nextId = (int) $postModel->insert(['board_id' => $boardId, 'title' => '다음 글입니다', 'content' => '본문', 'is_notice' => 0]);

        $body = html_entity_decode((string) $this->get("board/qna/{$postId}")->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString("/board/qna/{$prevId}", $body);
        $this->assertStringContainsString('이전 글입니다', $body);
        $this->assertStringContainsString("/board/qna/{$nextId}", $body);
        $this->assertStringContainsString('다음 글입니다', $body);
    }

    public function testViewHidesPrevNextBlockWhenNoAdjacentPosts(): void
    {
        $postModel = new PostModel();
        $postId    = (int) $postModel->insert([
            'board_id' => $this->boardId('qna'),
            'title'    => '유일한 글',
            'content'  => '본문',
        ]);

        $body = html_entity_decode((string) $this->get("board/qna/{$postId}")->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringNotContainsString('이전글', $body);
        $this->assertStringNotContainsString('다음글', $body);
    }

    public function testListShowsCategorySidebarWhenBoardHasCategories(): void
    {
        $boardId    = $this->boardId('free');
        $categoryId = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'daily', 'name' => '일상'], true);

        $body = $this->get('board/free')->getBody();

        $this->assertStringContainsString("board/free?category={$categoryId}", $body);
    }

    public function testCategoryFilterNarrowsPostList(): void
    {
        $boardId    = $this->boardId('free');
        $categoryId = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'daily', 'name' => '일상'], true);

        $postModel = new PostModel();
        $matchId   = (int) $postModel->insert(['board_id' => $boardId, 'category_id' => $categoryId, 'title' => '일상 글', 'content' => '내용', 'author_name' => '작성자'], true);
        $otherId   = (int) $postModel->insert(['board_id' => $boardId, 'category_id' => null, 'title' => '기타 글', 'content' => '내용', 'author_name' => '작성자'], true);

        $body = $this->get("board/free?category={$categoryId}")->getBody();

        $this->assertStringContainsString("/board/free/{$matchId}\"", $body);
        $this->assertStringNotContainsString("/board/free/{$otherId}\"", $body);
    }
}
