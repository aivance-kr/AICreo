<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class UserController extends BaseController
{
    private readonly UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index(): string
    {
        $keyword = $this->request->getGet('q') ?? '';
        $role    = $this->request->getGet('role') ?? '';
        $status  = $this->request->getGet('status') ?? '';
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;

        $builder = $this->userModel->builder();

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('nickname', $keyword)
                ->orLike('email', $keyword)
                ->orLike('username', $keyword)
                ->groupEnd();
        }
        if ($role !== '') {
            $builder->where('role', $role);
        }
        if ($status !== '') {
            $builder->where('is_active', (int) $status);
        }

        $total = (clone $builder)->countAllResults(false);
        $users = $builder->orderBy('id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return $this->render('admin/users/list', [
            'users'       => $users,
            'total'       => $total,
            'currentPage' => $page,
            'totalPages'  => (int) ceil($total / $perPage),
            'keyword'     => $keyword,
            'role'        => $role,
            'status'      => $status,
        ]);
    }

    public function edit(int $id): ResponseInterface|string
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return redirect()->to('/admin/users')->with('error', '회원을 찾을 수 없습니다.');
        }

        return $this->render('admin/users/edit', ['member' => $user]);
    }

    public function update(int $id): ResponseInterface|string
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return redirect()->to('/admin/users')->with('error', '회원을 찾을 수 없습니다.');
        }

        // 본인 계정의 역할/상태는 변경 불가
        if ($id === (int) session()->get('user_id')) {
            return redirect()->back()->with('error', '본인 계정은 수정할 수 없습니다.');
        }

        $this->userModel->update($id, [
            'nickname'  => $this->request->getPost('nickname'),
            'role'      => $this->request->getPost('role'),
            'is_active' => (int) $this->request->getPost('is_active'),
        ]);

        model(AdminLogModel::class)->record('update', 'user', $id, (string) $user['email'] . ' — 닉네임·역할·상태 변경');

        return redirect()->to('/admin/users')->with('success', '회원 정보가 수정되었습니다.');
    }

    public function delete(int $id): ResponseInterface|string
    {
        if ($id === (int) session()->get('user_id')) {
            return redirect()->back()->with('error', '본인 계정은 삭제할 수 없습니다.');
        }

        $user = $this->userModel->find($id);
        $this->userModel->delete($id);
        model(AdminLogModel::class)->record('delete', 'user', $id, (string) ($user['email'] ?? ''));

        return redirect()->to('/admin/users')->with('success', '회원이 삭제되었습니다.');
    }
}
