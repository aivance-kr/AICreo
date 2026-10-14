<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BoardModel;
use App\Models\PostFileModel;
use App\Models\PostModel;
use CodeIgniter\HTTP\ResponseInterface;

class PostController extends BaseController
{
    private readonly PostModel $postModel;
    private readonly BoardModel $boardModel;

    public function __construct()
    {
        $this->postModel  = new PostModel();
        $this->boardModel = new BoardModel();
    }

    public function index(): string
    {
        $keyword = $this->request->getGet('q') ?? '';
        $boardId = (int) ($this->request->getGet('board_id') ?? 0);
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;

        $result = $this->postModel->getAdminList($page, $perPage, $keyword, $boardId);
        $boards = $this->boardModel->orderBy('sort_order')->findAll();

        return $this->render('admin/posts/list', [
            'posts'       => $result['posts'],
            'total'       => $result['total'],
            'currentPage' => $page,
            'totalPages'  => (int) ceil($result['total'] / $perPage),
            'keyword'     => $keyword,
            'boardId'     => $boardId,
            'boards'      => $boards,
        ]);
    }

    public function delete(int $id): ResponseInterface|string
    {
        $post = $this->postModel->find($id);
        if (! $post) {
            return redirect()->back()->with('error', '게시글을 찾을 수 없습니다.');
        }

        (new PostFileModel())->deleteByPost($id);
        $this->postModel->delete($id);

        return redirect()->back()->with('success', '삭제되었습니다.');
    }

    public function bulkMove(): ResponseInterface|string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->request->getPost('post_ids')),
        )));
        $targetId = (int) $this->request->getPost('target_board_id');

        if ($ids === []) {
            return redirect()->back()->with('error', '이동할 게시물을 선택해 주세요.');
        }
        if ($this->boardModel->find($targetId) === null) {
            return redirect()->back()->with('error', '이동할 게시판을 선택해 주세요.');
        }

        $this->postModel->moveToBoard($ids, $targetId);

        return redirect()->back()->with('success', count($ids) . '개 게시물을 이동했습니다.');
    }

    public function toggle(int $id): ResponseInterface|string
    {
        $post = $this->postModel->find($id);
        if (! $post) {
            return redirect()->back()->with('error', '게시글을 찾을 수 없습니다.');
        }

        $this->postModel->toggleActive($id);

        return redirect()->back()->with('success', '노출 상태가 변경되었습니다.');
    }
}
