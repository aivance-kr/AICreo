<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LineDiff;
use App\Models\AdminLogModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\PostRevisionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 글 수정 이력 목록·비교·복구.
 */
class PostRevisionController extends BaseController
{
    private readonly PostModel $postModel;
    private readonly PostRevisionModel $revisionModel;

    public function __construct()
    {
        $this->postModel     = new PostModel();
        $this->revisionModel = new PostRevisionModel();
    }

    public function index(int $postId): string
    {
        $post = $this->postOrFail($postId);

        return $this->render('admin/posts/revisions', [
            'post'      => $post,
            'revisions' => $this->revisionModel->listByPost($postId),
            'boardSlug' => $this->boardSlug($post),
        ]);
    }

    /**
     * 이력과 현재 글의 제목·메타 설명·본문 차이를 보여 준다.
     */
    public function show(int $postId, int $revisionId): string
    {
        $post     = $this->postOrFail($postId);
        $revision = $this->revisionModel->findForPost($postId, $revisionId) ?? throw PageNotFoundException::forPageNotFound();

        return $this->render('admin/posts/revision', [
            'post'        => $post,
            'revision'    => $revision,
            'boardSlug'   => $this->boardSlug($post),
            'titleDiff'   => LineDiff::diff((string) $revision['title'], (string) $post['title']),
            'metaDiff'    => LineDiff::diff((string) $revision['meta_desc'], (string) $post['meta_desc']),
            'contentDiff' => LineDiff::diff((string) $revision['content'], (string) $post['content']),
        ]);
    }

    public function restore(int $postId, int $revisionId): ResponseInterface
    {
        $this->postOrFail($postId);

        $restored = $this->revisionModel->restore($postId, $revisionId, (int) session()->get('user_id'));

        if ($restored) {
            model(AdminLogModel::class)->record('restore', 'post', $postId, '수정 이력 #' . $revisionId . '(으)로 복구');
        }

        return redirect()->to("/admin/posts/{$postId}/revisions")->with(
            $restored ? 'success' : 'error',
            $restored ? '선택한 버전으로 복구했습니다. 복구 직전 글은 이력에 남았습니다.' : '복구하지 못했습니다.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function postOrFail(int $postId): array
    {
        return $this->postModel->find($postId) ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function boardSlug(array $post): string
    {
        return (string) ((new BoardModel())->find((int) $post['board_id'])['slug'] ?? '');
    }
}
