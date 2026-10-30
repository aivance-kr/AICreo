<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BoardCategoryModel;
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
        $keyword    = $this->request->getGet('q') ?? '';
        $boardId    = (int) ($this->request->getGet('board_id') ?? 0);
        $categoryId = (int) ($this->request->getGet('category_id') ?? 0);
        $page       = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage    = 20;

        $result = $this->postModel->getAdminList($page, $perPage, $keyword, $boardId, $categoryId);
        $boards = $this->boardModel->orderBy('sort_order')->findAll();

        $categoriesByBoard = [];

        foreach ($boards as $board) {
            $categories = (new BoardCategoryModel())->getAllByBoard((int) $board['id']);
            if ($categories !== []) {
                $categoriesByBoard[(int) $board['id']] = $categories;
            }
        }

        return $this->render('admin/posts/list', [
            'posts'             => $result['posts'],
            'total'             => $result['total'],
            'currentPage'       => $page,
            'totalPages'        => (int) ceil($result['total'] / $perPage),
            'keyword'           => $keyword,
            'boardId'           => $boardId,
            'categoryId'        => $categoryId,
            'boards'            => $boards,
            'categoriesByBoard' => $categoriesByBoard,
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

    public function bulkCategory(): ResponseInterface|string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->request->getPost('post_ids')),
        )));
        $category = (new BoardCategoryModel())->find((int) $this->request->getPost('target_category_id'));

        if ($ids === []) {
            return redirect()->back()->with('error', '변경할 게시물을 선택해 주세요.');
        }
        if ($category === null) {
            return redirect()->back()->with('error', '변경할 카테고리를 선택해 주세요.');
        }

        $changed = $this->postModel->changeCategory($ids, (int) $category['id'], (int) $category['board_id']);
        $skipped = count($ids) - $changed;
        $message = $changed . '개 게시물의 카테고리를 "' . $category['name'] . '"(으)로 변경했습니다.';
        if ($skipped > 0) {
            $message .= ' 다른 게시판 글 등 ' . $skipped . '개는 건너뛰었습니다.';
        }

        return redirect()->back()->with('success', $message);
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
