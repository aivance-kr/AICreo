<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\TranslationManager;
use App\Models\AdminLogModel;
use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\I18n;

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
        $locale     = $this->localeFilter();

        $result = $this->postModel->getAdminList($page, $perPage, $keyword, $boardId, $categoryId, $locale);
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
            'locales'           => config(I18n::class)->locales(),
            'currentLocale'     => $locale,
        ]);
    }

    /**
     * 글의 번역본(숨김 복사본)을 만들고 그 수정 화면으로 보낸다.
     */
    public function translate(int $id, string $locale): ResponseInterface
    {
        $manager = new TranslationManager();
        $newId   = $manager->createPostTranslation($id, $locale);

        if ($newId === null) {
            return redirect()->back()->with('error', '번역본을 만들 수 없습니다. (이미 있거나 사용하지 않는 언어)');
        }

        model(AdminLogModel::class)->record('create', 'post', $newId, "번역({$locale}) 원본 #{$id}");
        $rows = array_column($manager->postRows($newId), 'edit', 'locale');

        return redirect()->to($rows[$locale] ?? '/admin/posts')->with('success', '번역본을 만들었습니다. 숨김 상태이니 번역 후 공개하세요.');
    }

    private function localeFilter(): ?string
    {
        $locale = (string) $this->request->getGet('locale');

        return in_array($locale, config(I18n::class)->locales(), true) ? $locale : null;
    }

    public function delete(int $id): ResponseInterface|string
    {
        $post = $this->postModel->find($id);
        if (! $post) {
            return redirect()->back()->with('error', '게시글을 찾을 수 없습니다.');
        }

        $this->postModel->delete($id);
        model(AdminLogModel::class)->record('delete', 'post', $id, (string) $post['title']);

        return redirect()->back()->with('success', '휴지통으로 이동했습니다.');
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
        model(AdminLogModel::class)->record('update', 'post', null, count($ids) . '개 글을 게시판 #' . $targetId . '(으)로 이동: #' . implode(', #', $ids));

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
        model(AdminLogModel::class)->record('update', 'post', null, $changed . '개 글의 카테고리를 "' . $category['name'] . '"(으)로 변경: #' . implode(', #', $ids));
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
        model(AdminLogModel::class)->record('toggle', 'post', $id, (string) $post['title']);

        return redirect()->back()->with('success', '노출 상태가 변경되었습니다.');
    }
}
