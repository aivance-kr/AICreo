<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HtmlSanitizer;
use App\Models\SignatureModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 운영자별 글 서명 관리. 서명은 본인 것만 다룰 수 있다.
 */
class SignatureController extends BaseController
{
    private const LIST_URL = '/admin/settings/signatures';

    private readonly SignatureModel $signatureModel;

    public function __construct()
    {
        $this->signatureModel = new SignatureModel();
    }

    public function index(): string
    {
        $signatures = $this->signatureModel->listByUser($this->userId());
        $editId     = (int) $this->request->getGet('edit');
        $editing    = null;

        foreach ($signatures as $signature) {
            if ((int) $signature['id'] === $editId) {
                $editing = $signature;
            }
        }

        return $this->render('admin/signatures/index', [
            'group'      => 'signatures',
            'signatures' => $signatures,
            'editing'    => $editing,
        ]);
    }

    public function store(): ResponseInterface
    {
        $input = $this->validatedInput();
        if ($input === null) {
            return redirect()->back()->withInput()->with('error', '이름과 내용을 입력해 주세요.');
        }

        $userId = $this->userId();
        $id     = (int) $this->signatureModel->insert($input + ['user_id' => $userId, 'is_selected' => 0], true);

        // 첫 서명은 바로 선택 상태로 둔다
        if (count($this->signatureModel->listByUser($userId)) === 1) {
            $this->signatureModel->choose($userId, $id);
        }

        return redirect()->to(self::LIST_URL)->with('success', '서명이 추가되었습니다.');
    }

    public function update(int $id): ResponseInterface
    {
        if ($this->findOwned($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '서명을 찾을 수 없습니다.');
        }

        $input = $this->validatedInput();
        if ($input === null) {
            return redirect()->back()->withInput()->with('error', '이름과 내용을 입력해 주세요.');
        }

        $this->signatureModel->update($id, $input);

        return redirect()->to(self::LIST_URL)->with('success', '서명이 수정되었습니다. 이미 쓴 글에는 반영되지 않습니다.');
    }

    public function select(int $id): ResponseInterface
    {
        if ($this->findOwned($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '서명을 찾을 수 없습니다.');
        }

        $this->signatureModel->choose($this->userId(), $id);

        return redirect()->to(self::LIST_URL)->with('success', '선택한 서명은 앞으로 쓰는 글부터 적용됩니다.');
    }

    public function delete(int $id): ResponseInterface
    {
        if ($this->findOwned($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '서명을 찾을 수 없습니다.');
        }

        $this->signatureModel->delete($id);

        return redirect()->to(self::LIST_URL)->with('success', '서명이 삭제되었습니다.');
    }

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOwned(int $id): ?array
    {
        $signature = $this->signatureModel->find($id);

        return $signature !== null && (int) $signature['user_id'] === $this->userId() ? $signature : null;
    }

    /**
     * @return array{name: string, content: string}|null
     */
    private function validatedInput(): ?array
    {
        $name    = trim((string) $this->request->getPost('name'));
        $content = HtmlSanitizer::clean($this->request->getPost('content'));

        if ($name === '' || mb_strlen($name) > 100 || trim(strip_tags($content, '<img>')) === '') {
            return null;
        }

        return ['name' => $name, 'content' => $content];
    }
}
