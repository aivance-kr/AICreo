<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\PostPreviewTokenModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

/**
 * 비공개·예약 글 미리보기 공유 링크 발급·폐기.
 */
class PostPreviewController extends BaseController
{
    private const DEFAULT_DAYS = 7;

    private readonly PostModel $postModel;
    private readonly PostPreviewTokenModel $tokenModel;

    public function __construct()
    {
        $this->postModel  = new PostModel();
        $this->tokenModel = new PostPreviewTokenModel();
    }

    public function index(int $postId): string
    {
        $post = $this->postOrFail($postId);

        return $this->render('admin/posts/preview_links', [
            'post'        => $post,
            'boardSlug'   => (string) ((new BoardModel())->find((int) $post['board_id'])['slug'] ?? ''),
            'links'       => $this->tokenModel->listByPost($postId),
            'defaultDays' => self::DEFAULT_DAYS,
            'maxDays'     => PostPreviewTokenModel::MAX_DAYS,
        ]);
    }

    public function store(int $postId): ResponseInterface
    {
        $this->postOrFail($postId);
        $days = (int) ($this->request->getPost('days') ?? self::DEFAULT_DAYS);

        try {
            $this->tokenModel->issue($postId, $days);
        } catch (InvalidArgumentException $e) {
            return redirect()->to("/admin/posts/{$postId}/preview-links")->with('error', $e->getMessage());
        }

        // 토큰 값은 로그에 남기지 않는다
        model(AdminLogModel::class)->record('create', 'post', $postId, '미리보기 링크 발급(' . $days . '일)');

        return redirect()->to("/admin/posts/{$postId}/preview-links")->with('success', '미리보기 링크를 발급했습니다.');
    }

    public function revoke(int $postId, int $id): ResponseInterface
    {
        $this->postOrFail($postId);
        $revoked = $this->tokenModel->revoke($postId, $id);

        if ($revoked) {
            model(AdminLogModel::class)->record('update', 'post', $postId, '미리보기 링크 폐기');
        }

        return redirect()->to("/admin/posts/{$postId}/preview-links")->with(
            $revoked ? 'success' : 'error',
            $revoked ? '링크를 폐기했습니다.' : '폐기하지 못했습니다.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function postOrFail(int $postId): array
    {
        return $this->postModel->find($postId) ?? throw PageNotFoundException::forPageNotFound();
    }
}
