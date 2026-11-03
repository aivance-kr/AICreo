<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PostCommentModel;
use CodeIgniter\HTTP\ResponseInterface;

class CommentController extends BaseController
{
    private readonly PostCommentModel $commentModel;

    public function __construct()
    {
        $this->commentModel = new PostCommentModel();
    }

    public function index(): string
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;
        $pending = $this->request->getGet('status') === 'pending';
        $result  = $this->commentModel->getAdminList($page, $perPage, $pending);

        return $this->render('admin/comments/list', [
            'comments'     => $result['comments'],
            'total'        => $result['total'],
            'currentPage'  => $page,
            'totalPages'   => (int) ceil($result['total'] / $perPage),
            'pendingOnly'  => $pending,
            'pendingCount' => $this->commentModel->countPending(),
        ]);
    }

    public function toggle(int $id): ResponseInterface|string
    {
        $comment = $this->commentModel->find($id);
        if (! $comment) {
            return redirect()->back()->with('error', '댓글을 찾을 수 없습니다.');
        }

        $this->commentModel->toggleActive($id);

        return redirect()->back()->with('success', '노출 상태가 변경되었습니다.');
    }

    public function approve(int $id): ResponseInterface|string
    {
        if (! $this->commentModel->find($id)) {
            return redirect()->back()->with('error', '댓글을 찾을 수 없습니다.');
        }

        $this->commentModel->approve($id);

        return redirect()->back()->with('success', '댓글을 승인했습니다.');
    }

    public function delete(int $id): ResponseInterface|string
    {
        if (! $this->commentModel->find($id)) {
            return redirect()->back()->with('error', '댓글을 찾을 수 없습니다.');
        }

        $this->commentModel->delete($id);

        return redirect()->back()->with('success', '댓글을 휴지통으로 이동했습니다.');
    }
}
