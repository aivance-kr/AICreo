<?php

declare(strict_types=1);

namespace Plugins\Bookmark\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Plugins\Bookmark\Models\BookmarkItemModel;

class BookmarkController extends BaseController
{
    private const PER_PAGE = 20;

    public function index(): string
    {
        $model = model(BookmarkItemModel::class);
        $role  = $this->role();
        $total = $model->countVisible($role);
        $last  = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min(max(1, (int) $this->request->getGet('page')), $last);

        return $this->render('\Plugins\Bookmark\Views\index', [
            'page'     => ['title' => '내 북마크', 'meta_title' => '내 북마크', 'meta_desc' => '', 'noindex' => true],
            'items'    => $model->listVisible($role, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total'    => $total,
            'pageNo'   => $page,
            'lastPage' => $last,
        ]);
    }

    /**
     * 북마크 저장/해제. 이미 저장한 글의 해제는 글이 숨겨진 뒤에도 허용한다.
     */
    public function toggle(int $postId): ResponseInterface
    {
        $model = model(BookmarkItemModel::class);
        $post  = $model->findVisiblePost($postId, $this->role());

        if ($post === null && ! $model->isBookmarked($postId)) {
            return redirect()->to('/my/bookmarks')->with('error', '저장할 수 없는 글입니다.');
        }

        $saved = $model->toggle($postId);

        if ($this->request->getPost('return') === 'list' || $post === null) {
            return redirect()->to('/my/bookmarks')->with('success', $saved ? '북마크에 저장했습니다.' : '북마크에서 뺐습니다.');
        }

        return redirect()->to('/board/' . $post['board_slug'] . '/' . $post['id'] . '#post-bookmark');
    }

    private function role(): string
    {
        return (string) (session()->get('user_role') ?? 'member');
    }
}
