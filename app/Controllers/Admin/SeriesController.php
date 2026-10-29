<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SeriesModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 시리즈(연재) 관리. 글에 시리즈를 붙이는 일은 글쓰기 화면에서 한다.
 */
class SeriesController extends BaseController
{
    private const LIST_URL = '/admin/series';

    private readonly SeriesModel $seriesModel;

    public function __construct()
    {
        $this->seriesModel = new SeriesModel();
    }

    public function index(): string
    {
        $editId  = (int) $this->request->getGet('edit');
        $editing = $editId > 0 ? $this->seriesModel->find($editId) : null;
        $keyword = trim((string) $this->request->getGet('q'));

        return $this->render('admin/series/index', [
            'seriesList' => $this->seriesModel->listWithCounts(),
            'editing'    => $editing,
            'members'    => $editing ? $this->seriesModel->memberPosts($editId) : [],
            'keyword'    => $keyword,
            'found'      => $editing ? $this->seriesModel->searchPosts($editId, $keyword) : [],
        ]);
    }

    public function store(): ResponseInterface
    {
        $input = $this->validatedInput(null);
        if ($input === null) {
            return redirect()->back()->withInput();
        }

        $this->seriesModel->insert($input);

        return redirect()->to(self::LIST_URL)->with('success', '시리즈가 추가되었습니다.');
    }

    public function update(int $id): ResponseInterface
    {
        if ($this->seriesModel->find($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '시리즈를 찾을 수 없습니다.');
        }

        $input = $this->validatedInput($id);
        if ($input === null) {
            return redirect()->back()->withInput();
        }

        $this->seriesModel->update($id, $input);

        return redirect()->to(self::LIST_URL)->with('success', '시리즈가 수정되었습니다.');
    }

    /**
     * 글 번호를 콤마로 구분해 한 번에 넣거나(검색 결과의 "추가" 버튼은 번호 1개), 시리즈에서 뺀다.
     */
    public function addPosts(int $id): ResponseInterface
    {
        if ($this->seriesModel->find($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '시리즈를 찾을 수 없습니다.');
        }

        $editUrl = self::LIST_URL . '?edit=' . $id;
        $ids     = SeriesModel::parsePostIds((string) $this->request->getPost('post_ids'));
        if ($ids === []) {
            return redirect()->to($editUrl)->with('error', '글 번호를 입력하세요. 여러 개는 콤마(,)로 구분합니다.');
        }

        $result  = $this->seriesModel->attachPosts($id, $ids);
        $message = count($result['added']) . '개 글을 시리즈에 넣었습니다.';
        if ($result['already'] !== []) {
            $message .= ' 이미 포함: ' . implode(', ', $result['already']) . '.';
        }
        if ($result['missing'] !== []) {
            $message .= ' 없는 글: ' . implode(', ', $result['missing']) . '.';
        }

        $flash = $result['added'] === [] ? 'error' : 'success';

        return redirect()->to($editUrl)->with($flash, $message);
    }

    public function reorderPosts(int $id): ResponseInterface
    {
        if ($this->seriesModel->find($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '시리즈를 찾을 수 없습니다.');
        }

        $ids = array_map('intval', (array) $this->request->getPost('post_ids'));
        $this->seriesModel->reorderPosts($id, $ids);

        return redirect()->to(self::LIST_URL . '?edit=' . $id)->with('success', '순서를 저장했습니다.');
    }

    public function removePost(int $id, int $postId): ResponseInterface
    {
        $this->seriesModel->detachPost($id, $postId);

        return redirect()->to(self::LIST_URL . '?edit=' . $id)->with('success', '시리즈에서 뺐습니다. 글은 그대로 남습니다.');
    }

    public function delete(int $id): ResponseInterface
    {
        if ($this->seriesModel->find($id) === null) {
            return redirect()->to(self::LIST_URL)->with('error', '시리즈를 찾을 수 없습니다.');
        }

        $this->seriesModel->delete($id);

        return redirect()->to(self::LIST_URL)->with('success', '시리즈를 삭제했습니다. 속했던 글은 그대로 남습니다.');
    }

    /**
     * @return array{slug: string, title: string, description: string|null, status: string}|null 검증 실패 시 null(오류는 플래시에 담는다)
     */
    private function validatedInput(?int $exceptId): ?array
    {
        $isUnique = $exceptId === null ? 'is_unique[series.slug]' : 'is_unique[series.slug,id,' . $exceptId . ']';
        $valid    = $this->validate([
            'slug'        => 'required|max_length[100]|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|' . $isUnique,
            'title'       => 'required|max_length[200]',
            'description' => 'permit_empty|max_length[2000]',
            'status'      => 'required|in_list[' . implode(',', array_keys(SeriesModel::STATUSES)) . ']',
        ], [
            'slug' => [
                'regex_match' => '주소는 영문 소문자·숫자·하이픈(-)만 쓸 수 있습니다.',
                'is_unique'   => '이미 사용 중인 주소입니다.',
            ],
        ]);

        if (! $valid) {
            session()->setFlashdata('errors', $this->validator->getErrors());

            return null;
        }

        $description = trim((string) $this->request->getPost('description'));

        return [
            'slug'        => (string) $this->request->getPost('slug'),
            'title'       => trim((string) $this->request->getPost('title')),
            'description' => $description === '' ? null : $description,
            'status'      => (string) $this->request->getPost('status'),
        ];
    }
}
