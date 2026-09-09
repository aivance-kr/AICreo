<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdModel;
use CodeIgniter\HTTP\ResponseInterface;

class AdController extends BaseController
{
    private readonly AdModel $adModel;

    public function __construct()
    {
        $this->adModel = new AdModel();
    }

    public function index(): string
    {
        return $this->render('admin/ads/list', [
            'ads'       => $this->adModel->orderBy('position')->orderBy('priority')->findAll(),
            'positions' => AdModel::POSITIONS,
        ]);
    }

    public function create(): string
    {
        return $this->render('admin/ads/form', [
            'ad'        => null,
            'positions' => AdModel::POSITIONS,
        ]);
    }

    public function store(): ResponseInterface|string
    {
        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->adModel->insert($this->collectData());

        return redirect()->to('/admin/ads')->with('success', '광고가 등록되었습니다.');
    }

    public function edit(int $id): ResponseInterface|string
    {
        $ad = $this->adModel->find($id);
        if (! $ad) {
            return redirect()->to('/admin/ads')->with('error', '광고를 찾을 수 없습니다.');
        }

        return $this->render('admin/ads/form', [
            'ad'        => $ad,
            'positions' => AdModel::POSITIONS,
        ]);
    }

    public function update(int $id): ResponseInterface|string
    {
        $ad = $this->adModel->find($id);
        if (! $ad) {
            return redirect()->to('/admin/ads')->with('error', '광고를 찾을 수 없습니다.');
        }

        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->adModel->update($id, $this->collectData());

        return redirect()->to('/admin/ads')->with('success', '저장되었습니다.');
    }

    public function delete(int $id): ResponseInterface|string
    {
        $this->adModel->delete($id);

        return redirect()->to('/admin/ads')->with('success', '삭제되었습니다.');
    }

    /**
     * @return array<string, string>
     */
    private function validationRules(): array
    {
        return [
            'name'     => 'required|max_length[100]',
            'position' => 'required|in_list[' . implode(',', array_keys(AdModel::POSITIONS)) . ']',
            'code'     => 'required',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectData(): array
    {
        return [
            'name'      => $this->request->getPost('name'),
            'position'  => $this->request->getPost('position'),
            'code'      => $this->request->getPost('code'),
            'priority'  => (int) $this->request->getPost('priority'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
