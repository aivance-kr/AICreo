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
        $result  = $this->commentModel->getAdminList($page, $perPage);

        return $this->render('admin/comments/list', [
            'comments'    => $result['comments'],
            'total'       => $result['total'],
            'currentPage' => $page,
            'totalPages'  => (int) ceil($result['total'] / $perPage),
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
}
