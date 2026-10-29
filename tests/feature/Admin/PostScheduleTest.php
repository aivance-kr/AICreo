<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardModel;
use App\Models\PostModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class PostScheduleTest extends AdminTestCase
{
    private function futureInput(): string
    {
        return date('Y-m-d\TH:i', strtotime('+1 day'));
    }

    private function storeUrl(): string
    {
        return 'board/notice/write';
    }

    public function testAdminSchedulesNewPostAsHidden(): void
    {
        $this->withSession($this->adminSession)->post($this->storeUrl(), [
            'title'      => '예약 글',
            'content'    => '<p>본문</p>',
            'publish_at' => $this->futureInput(),
        ]);

        $post = (new PostModel())->where('title', '예약 글')->first();
        $this->assertNotNull($post);
        $this->assertSame(0, (int) $post['is_active']);
        $this->assertSame(date('Y-m-d H:i:00', strtotime($this->futureInput())), $post['publish_at']);
    }

    public function testPastScheduleIsIgnoredAndPublishedImmediately(): void
    {
        $this->withSession($this->adminSession)->post($this->storeUrl(), [
            'title'      => '과거 예약',
            'content'    => '<p>본문</p>',
            'publish_at' => date('Y-m-d\TH:i', strtotime('-1 day')),
        ]);

        $post = (new PostModel())->where('title', '과거 예약')->first();
        $this->assertSame(1, (int) $post['is_active']);
        $this->assertNull($post['publish_at']);
    }

    public function testClearingScheduleOnUpdatePublishesPost(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $id      = (int) (new PostModel())->insert([
            'board_id'  => $boardId, 'title' => '예약', 'content' => '본문', 'author_name' => '관리자',
            'is_active' => 0, 'publish_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $this->withSession($this->adminSession)->post("board/notice/{$id}/edit", [
            'title' => '예약', 'content' => '<p>본문</p>', 'publish_at' => '',
        ]);

        $post = (new PostModel())->find($id);
        $this->assertSame(1, (int) $post['is_active']);
        $this->assertNull($post['publish_at']);
    }

    public function testAdminListShowsScheduledBadge(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        (new PostModel())->insert([
            'board_id'  => $boardId, 'title' => '예약표시', 'content' => '본문', 'author_name' => '관리자',
            'is_active' => 0, 'publish_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $result = $this->withSession($this->adminSession)->get('admin/posts');

        $result->assertSee('예약 ' . date('Y-m-d H:i', strtotime('+1 day')));
    }
}
