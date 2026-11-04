<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;
use App\Models\PageVisitModel;
use App\Models\RedirectModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 리다이렉트 관리. 옛 주소를 새 주소로 보낸다(RedirectFilter 가 요청마다 적용).
 */
class RedirectController extends BaseController
{
    private const LIST_URL       = '/admin/redirects';
    private const PER_PAGE       = 20;
    private const NOT_FOUND_DAYS = 30;

    private readonly RedirectModel $redirectModel;

    public function __construct()
    {
        $this->redirectModel = new RedirectModel();
    }

    public function index(): string
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $editId  = (int) $this->request->getGet('edit');
        $result  = $this->redirectModel->search($keyword, $page, self::PER_PAGE);

        return $this->render('admin/redirects/index', [
            'items'        => $result['items'],
            'total'        => $result['total'],
            'keyword'      => $keyword,
            'currentPage'  => $page,
            'totalPages'   => (int) ceil($result['total'] / self::PER_PAGE),
            'editing'      => $editId > 0 ? $this->redirectModel->find($editId) : null,
            'prefillOld'   => (string) $this->request->getGet('old'),
            'notFound'     => (new PageVisitModel())->topNotFound(self::NOT_FOUND_DAYS),
            'notFoundDays' => self::NOT_FOUND_DAYS,
        ]);
    }

    public function store(): ResponseInterface
    {
        $input = $this->validatedInput(null);
        if ($input === null) {
            return redirect()->back()->withInput();
        }

        $id = (int) $this->redirectModel->insert($input);
        model(AdminLogModel::class)->record('create', 'redirect', $id, $input['old_path'] . ' → ' . $input['new_path']);

        return redirect()->to(self::LIST_URL)->with('success', '리다이렉트를 등록했습니다.');
    }

    public function update(int $id): ResponseInterface
    {
        if ($this->redirectModel->find($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '리다이렉트를 찾을 수 없습니다.');
        }

        $input = $this->validatedInput($id);
        if ($input === null) {
            return redirect()->back()->withInput();
        }

        $this->redirectModel->update($id, $input);
        model(AdminLogModel::class)->record('update', 'redirect', $id, $input['old_path'] . ' → ' . $input['new_path']);

        return redirect()->to(self::LIST_URL)->with('success', '리다이렉트를 수정했습니다.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $row = $this->redirectModel->find($id);
        if ($row === null) {
            return redirect()->to(self::LIST_URL)->with('error', '리다이렉트를 찾을 수 없습니다.');
        }

        $active = (int) $row['is_active'] === 1 ? 0 : 1;
        $this->redirectModel->update($id, ['is_active' => $active]);
        model(AdminLogModel::class)->record('toggle', 'redirect', $id, (string) $row['old_path']);

        return redirect()->back()->with('success', $active === 1 ? '사용으로 바꿨습니다.' : '사용 안 함으로 바꿨습니다.');
    }

    public function delete(int $id): ResponseInterface
    {
        $row = $this->redirectModel->find($id);
        if ($row === null) {
            return redirect()->to(self::LIST_URL)->with('error', '리다이렉트를 찾을 수 없습니다.');
        }

        $this->redirectModel->delete($id);
        model(AdminLogModel::class)->record('delete', 'redirect', $id, (string) $row['old_path']);

        return redirect()->back()->with('success', '리다이렉트를 삭제했습니다.');
    }

    /**
     * @return array{old_path: string, new_path: string, status_code: int, is_active: int}|null
     */
    private function validatedInput(?int $exceptId): ?array
    {
        $valid = $this->validate([
            'old_path'    => 'required|max_length[500]|regex_match[/^\/\S*$/]',
            'new_path'    => 'required|max_length[500]|regex_match[/^(\/|https?:\/\/)\S*$/]',
            'status_code' => 'required|in_list[' . implode(',', array_keys(RedirectModel::STATUS_CODES)) . ']',
        ], [
            'old_path' => ['regex_match' => '원 주소는 /로 시작해야 하고 공백이 없어야 합니다.'],
            'new_path' => ['regex_match' => '대상은 /로 시작하는 경로이거나 http(s):// 주소여야 합니다.'],
        ]);

        if (! $valid) {
            session()->setFlashdata('errors', $this->validator->getErrors());

            return null;
        }

        $oldPath = trim((string) $this->request->getPost('old_path'));
        $newPath = trim((string) $this->request->getPost('new_path'));

        $error = $this->redirectModel->validationError($oldPath, $newPath, $exceptId);
        if ($error !== null) {
            session()->setFlashdata('errors', ['new_path' => $error]);

            return null;
        }

        return [
            'old_path'    => $oldPath,
            'new_path'    => $newPath,
            'status_code' => (int) $this->request->getPost('status_code'),
            'is_active'   => $this->request->getPost('is_active') === '1' ? 1 : 0,
        ];
    }
}
