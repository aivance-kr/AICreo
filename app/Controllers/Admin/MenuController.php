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

        $this->menuModel->insert([
            'parent_id'  => $this->request->getPost('parent_id') ?: null,
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

        $this->menuModel->update($id, [
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
}
