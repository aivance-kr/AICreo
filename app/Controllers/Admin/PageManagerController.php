<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;
use App\Models\PageModel;
use CodeIgniter\HTTP\ResponseInterface;

class PageManagerController extends BaseController
{
    private readonly PageModel $pageModel;

    public function __construct()
    {
        $this->pageModel = new PageModel();
    }

    public function index(): string
    {
        return $this->render('admin/pages/list', [
            'pages' => $this->pageModel->orderBy('sort_order')->findAll(),
        ]);
    }

    public function create(): string
    {
        return $this->render('admin/pages/form', ['page' => null]);
    }

    public function store(): ResponseInterface|string
    {
        $rules = [
            'slug'  => 'required|alpha_dash|is_unique[pages.slug]',
            'title' => 'required',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data   = $this->collectData();
        $pageId = (int) $this->pageModel->insert($data);
        model(AdminLogModel::class)->record('create', 'page', $pageId, (string) $data['title']);

        if (($data['status'] ?? '') === 'published') {
            service('indexnow')->submit([base_url($data['slug'])]);
        }

        return redirect()->to('/admin/pages')->with('success', '페이지가 생성되었습니다.');
    }

    public function edit(int $id): string
    {
        return $this->render('admin/pages/form', ['page' => $this->pageModel->find($id)]);
    }

    public function update(int $id): ResponseInterface|string
    {
        $data = $this->collectData(isUpdate: true);
        $this->pageModel->update($id, $data);
        model(AdminLogModel::class)->record('update', 'page', $id, (string) $data['title']);

        $page = $this->pageModel->find($id);
        if ($page && ($data['status'] ?? '') === 'published') {
            service('indexnow')->submit([base_url($page['slug'])]);
        }

        return redirect()->to('/admin/pages')->with('success', '저장되었습니다.');
    }

    public function delete(int $id): ResponseInterface|string
    {
        $page = $this->pageModel->find($id);
        $this->pageModel->delete($id);
        model(AdminLogModel::class)->record('delete', 'page', $id, (string) ($page['title'] ?? ''));

        return redirect()->to('/admin/pages')->with('success', '삭제되었습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    private function collectData(bool $isUpdate = false): array
    {
        $data = [
            'title'      => $this->request->getPost('title'),
            'content'    => $this->request->getPost('content'),
            'layout'     => $this->request->getPost('layout') ?: 'default',
            'meta_title' => $this->request->getPost('meta_title'),
            'meta_desc'  => $this->request->getPost('meta_desc'),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'status'     => $this->request->getPost('status') ?: 'published',
        ];
        if (! $isUpdate) {
            $data['slug'] = $this->request->getPost('slug');
        }

        return $data;
    }
}
