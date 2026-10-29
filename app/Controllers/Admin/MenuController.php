<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\MenuModel;
use CodeIgniter\HTTP\ResponseInterface;

class MenuController extends BaseController
{
    private readonly MenuModel $menuModel;

    public function __construct()
    {
        $this->menuModel = new MenuModel();
    }

    public function index(): string
    {
        return $this->render('admin/menus/index', [
            'menuTree' => $this->menuModel->getTreeAll(),
        ]);
    }

    public function reorder(): ResponseInterface
    {
        $rawIds = $this->request->getPost('ids');
        $ids    = is_array($rawIds) ? array_map('intval', $rawIds) : [];

        $this->menuModel->reorder($ids);

        return $this->response->setJSON([
            'success'   => true,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    public function store(): ResponseInterface|string
    {
        if (! $this->validate(['title' => 'required'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parentId = (int) $this->request->getPost('parent_id') ?: null;
        if (($error = $this->parentError($parentId)) !== null) {
            return redirect()->back()->withInput()->with('errors', [$error]);
        }

        $this->menuModel->insert([
            'parent_id'  => $parentId,
            'title'      => $this->request->getPost('title'),
            'url'        => $this->request->getPost('url') ?: null,
            'target'     => $this->request->getPost('target') ?: '_self',
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'is_active'  => 1,
        ]);
        $this->menuModel->clearCache();

        return redirect()->to('/admin/menus')->with('success', '메뉴가 추가되었습니다.');
    }

    public function update(int $id): ResponseInterface|string
    {
        if (! $this->validate(['title' => 'required'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parentId = (int) $this->request->getPost('parent_id') ?: null;
        if (($error = $this->parentError($parentId, $id)) !== null) {
            return redirect()->back()->withInput()->with('errors', [$error]);
        }

        $this->menuModel->update($id, [
            'parent_id'  => $parentId,
            'title'      => $this->request->getPost('title'),
            'url'        => $this->request->getPost('url') ?: null,
            'target'     => $this->request->getPost('target') ?: '_self',
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'is_active'  => (int) $this->request->getPost('is_active'),
        ]);
        $this->menuModel->clearCache();

        return redirect()->to('/admin/menus')->with('success', '수정되었습니다.');
    }

    public function delete(int $id): ResponseInterface|string
    {
        $this->menuModel->delete($id);
        $this->menuModel->clearCache();

        return redirect()->to('/admin/menus')->with('success', '삭제되었습니다.');
    }

    /**
     * 상위 메뉴 지정이 유효한지 검사한다. 메뉴는 2단까지만 허용한다.
     */
    private function parentError(?int $parentId, ?int $selfId = null): ?string
    {
        if ($parentId === null) {
            return null;
        }
        if ($parentId === $selfId) {
            return '자기 자신을 상위 메뉴로 지정할 수 없습니다.';
        }

        $parent = $this->menuModel->find($parentId);
        if ($parent === null || $parent['parent_id'] !== null) {
            return '상위 메뉴는 최상위 메뉴만 선택할 수 있습니다.';
        }
        if ($selfId !== null && $this->menuModel->where('parent_id', $selfId)->countAllResults() > 0) {
            return '하위 메뉴가 있는 메뉴는 다른 메뉴의 하위로 옮길 수 없습니다.';
        }

        return null;
    }
}
