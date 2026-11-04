<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;
use App\Models\InquiryFieldModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 문의 폼 빌더. 기본 항목(이름·이메일·연락처·제목·내용) 뒤에 붙는 추가 항목을 관리한다.
 */
class InquiryFieldController extends BaseController
{
    private const LIST_URL = '/admin/inquiry-fields';

    private readonly InquiryFieldModel $fieldModel;

    public function __construct()
    {
        $this->fieldModel = new InquiryFieldModel();
    }

    public function index(): string
    {
        $editId = (int) $this->request->getGet('edit');

        return $this->render('admin/inquiry_fields/index', [
            'fields'  => $this->fieldModel->allOrdered(),
            'editing' => $editId > 0 ? $this->fieldModel->find($editId) : null,
        ]);
    }

    public function store(): ResponseInterface
    {
        $input = $this->validatedInput();
        if ($input === null) {
            return redirect()->back()->withInput();
        }

        $id = (int) $this->fieldModel->insert($input + ['sort_order' => $this->fieldModel->nextSortOrder()]);
        model(AdminLogModel::class)->record('create', 'inquiry_field', $id, $input['label']);

        return redirect()->to(self::LIST_URL)->with('success', '항목을 추가했습니다.');
    }

    public function update(int $id): ResponseInterface
    {
        if ($this->fieldModel->find($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '항목을 찾을 수 없습니다.');
        }

        $input = $this->validatedInput();
        if ($input === null) {
            return redirect()->back()->withInput();
        }

        $this->fieldModel->update($id, $input);
        model(AdminLogModel::class)->record('update', 'inquiry_field', $id, $input['label']);

        return redirect()->to(self::LIST_URL)->with('success', '항목을 수정했습니다.');
    }

    /**
     * 순서 이동. 바로 위·아래 항목과 순서 값을 맞바꾼다.
     */
    public function move(int $id, string $direction): ResponseInterface
    {
        $fields = $this->fieldModel->allOrdered();
        $index  = array_search($id, array_map(static fn (array $f): int => (int) $f['id'], $fields), true);
        $swap   = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($fields[$swap])) {
            // 순서 값이 같은 행이 있어도 위치가 바뀌도록 목록 순서대로 다시 번호를 매긴다
            [$fields[$index], $fields[$swap]] = [$fields[$swap], $fields[$index]];

            foreach ($fields as $position => $field) {
                $this->fieldModel->update((int) $field['id'], ['sort_order' => $position + 1]);
            }
        }

        return redirect()->to(self::LIST_URL);
    }

    public function toggle(int $id): ResponseInterface
    {
        $row = $this->fieldModel->find($id);
        if ($row === null) {
            return redirect()->to(self::LIST_URL)->with('error', '항목을 찾을 수 없습니다.');
        }

        $active = (int) $row['is_active'] === 1 ? 0 : 1;
        $this->fieldModel->update($id, ['is_active' => $active]);
        model(AdminLogModel::class)->record('toggle', 'inquiry_field', $id, (string) $row['label']);

        return redirect()->to(self::LIST_URL)->with('success', $active === 1 ? '폼에 표시합니다.' : '폼에서 숨겼습니다.');
    }

    public function delete(int $id): ResponseInterface
    {
        $row = $this->fieldModel->find($id);
        if ($row === null) {
            return redirect()->to(self::LIST_URL)->with('error', '항목을 찾을 수 없습니다.');
        }

        // 기존 문의에는 제출 당시 라벨이 함께 저장돼 있어 항목을 지워도 문의는 그대로 보인다
        $this->fieldModel->delete($id);
        model(AdminLogModel::class)->record('delete', 'inquiry_field', $id, (string) $row['label']);

        return redirect()->to(self::LIST_URL)->with('success', '항목을 삭제했습니다.');
    }

    /**
     * @return array{label: string, field_type: string, options: ?string, is_required: int, is_active: int}|null
     */
    private function validatedInput(): ?array
    {
        if (! $this->validate([
            'label'      => 'required|max_length[100]',
            'field_type' => 'required|in_list[' . implode(',', array_keys(InquiryFieldModel::TYPES)) . ']',
            'options'    => 'permit_empty|max_length[2000]',
        ])) {
            session()->setFlashdata('errors', $this->validator->getErrors());

            return null;
        }

        $type    = (string) $this->request->getPost('field_type');
        $options = in_array($type, ['select', 'checkbox'], true) ? trim((string) $this->request->getPost('options')) : '';

        if ($type === 'select' && $options === '') {
            session()->setFlashdata('errors', ['options' => '선택 항목에는 선택지를 한 줄에 하나씩 입력해 주세요.']);

            return null;
        }

        return [
            'label'       => trim((string) $this->request->getPost('label')),
            'field_type'  => $type,
            'options'     => $options === '' ? null : $options,
            'is_required' => $this->request->getPost('is_required') === '1' ? 1 : 0,
            'is_active'   => $this->request->getPost('is_active') === '0' ? 0 : 1,
        ];
    }
}
