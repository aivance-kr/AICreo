<?php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseController
{
    private readonly UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login(): ResponseInterface|string
    {
        if (session()->get('user_id')) {
            return redirect()->to('/');
        }

        return $this->render('auth/login', ['page' => ['title' => '로그인', 'noindex' => true]]);
    }

    public function loginProcess(): ResponseInterface|string
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = $this->userModel->findByEmail($this->request->getPost('email'));

        if (! $user || ! password_verify($this->request->getPost('password'), (string) $user['password'])) {
            return redirect()->back()->withInput()->with('error', '이메일 또는 비밀번호가 올바르지 않습니다.');
        }

        session()->set([
            'user_id'       => $user['id'],
            'user_nickname' => $user['nickname'],
            'user_role'     => $user['role'],
        ]);

        $this->userModel->updateLastLogin($user['id']);

        return redirect()->to(session()->getTempdata('redirect_url') ?? '/');
    }

    public function logout(): ResponseInterface|string
    {
        session()->destroy();

        return redirect()->to('/auth/login');
    }

    public function register(): ResponseInterface|string
    {
        if (($this->viewData['settings']['signup_enabled'] ?? '0') !== '1') {
            return redirect()->to('/auth/login')->with('error', '현재 회원가입이 비활성화되어 있습니다.');
        }

        return $this->render('auth/register', ['page' => ['title' => '회원가입', 'noindex' => true]]);
    }

    public function registerProcess(): ResponseInterface|string
    {
        if (($this->viewData['settings']['signup_enabled'] ?? '0') !== '1') {
            return redirect()->to('/auth/login')->with('error', '현재 회원가입이 비활성화되어 있습니다.');
        }

        $rules = [
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'nickname'         => 'required|min_length[2]|max_length[20]|is_unique[users.nickname]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->userModel->insert([
            'email'    => $this->request->getPost('email'),
            'username' => $this->request->getPost('email'),
            'password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            'nickname' => $this->request->getPost('nickname'),
            'role'     => 'member',
        ]);

        return redirect()->to('/auth/login')->with('success', '회원가입이 완료되었습니다. 로그인해주세요.');
    }

    // ─── 내 정보 수정 ────────────────────────────────────────────────────────

    public function profile(): ResponseInterface|string
    {
        if (! session()->get('user_id')) {
            return redirect()->to('/auth/login')->with('error', '로그인이 필요합니다.');
        }

        $user = $this->userModel->find(session()->get('user_id'));

        return $this->render('auth/profile', ['user' => $user, 'page' => ['title' => '내 정보', 'noindex' => true]]);
    }

    public function profileUpdate(): ResponseInterface|string
    {
        $userId = (int) session()->get('user_id');
        if ($userId === 0) {
            return redirect()->to('/auth/login');
        }

        $user = $this->userModel->find($userId);
        $tab  = $this->request->getPost('tab') ?? 'info';

        // ── 기본정보 탭 ──
        if ($tab === 'info') {
            $rules = ['nickname' => 'required|min_length[2]|max_length[20]'];

            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $this->userModel->update($userId, [
                'nickname' => $this->request->getPost('nickname'),
            ]);

            // 세션 닉네임 갱신
            session()->set('user_nickname', $this->request->getPost('nickname'));

            return redirect()->to('/auth/profile')->with('success', '정보가 수정되었습니다.');
        }

        // ── 비밀번호 탭 ──
        if ($tab === 'password') {
            // 소셜 로그인 계정은 비밀번호 변경 불가
            if ($user['social_provider']) {
                return redirect()->back()->with('error', '소셜 로그인 계정은 비밀번호를 변경할 수 없습니다.');
            }

            $rules = [
                'current_password' => 'required',
                'new_password'     => 'required|min_length[8]',
                'confirm_password' => 'required|matches[new_password]',
            ];

            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            if (! password_verify($this->request->getPost('current_password'), (string) $user['password'])) {
                return redirect()->back()->withInput()->with('error', '현재 비밀번호가 올바르지 않습니다.');
            }

            $this->userModel->update($userId, [
                'password' => password_hash($this->request->getPost('new_password'), PASSWORD_DEFAULT),
            ]);

            return redirect()->to('/auth/profile?tab=password')->with('success', '비밀번호가 변경되었습니다.');
        }

        return redirect()->to('/auth/profile');
    }

    public function withdraw(): ResponseInterface|string
    {
        $userId = (int) session()->get('user_id');
        if ($userId === 0) {
            return redirect()->to('/auth/login')->with('error', '로그인이 필요합니다.');
        }

        $user = $this->userModel->find($userId);
        if (! $user || $user['role'] === 'admin') {
            return redirect()->to('/auth/profile')->with('error', '관리자 계정은 탈퇴할 수 없습니다.');
        }

        if ($this->request->getPost('confirmation') !== '탈퇴') {
            return redirect()->to('/auth/profile?tab=withdraw')->withInput()->with('error', '탈퇴 확인 문구를 정확히 입력해주세요.');
        }

        if (! $user['social_provider'] && ! password_verify((string) $this->request->getPost('password'), (string) $user['password'])) {
            return redirect()->to('/auth/profile?tab=withdraw')->withInput()->with('error', '비밀번호가 올바르지 않습니다.');
        }

        $this->userModel->withdraw($userId);
        session()->destroy();

        return redirect()->to('/')->with('success', '회원 탈퇴가 완료되었습니다.');
    }
}
