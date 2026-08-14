<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\MediaUploader;
use App\Models\MediaModel;
use CodeIgniter\HTTP\ResponseInterface;

class MediaController extends BaseController
{
    private readonly MediaModel $mediaModel;
    private readonly MediaUploader $uploader;

    public function __construct()
    {
        $this->mediaModel = new MediaModel();
        $this->uploader   = new MediaUploader();
    }

    public function index(): string
    {
        $page   = (int) ($this->request->getGet('page') ?? 1);
        $limit  = 24;
        $offset = ($page - 1) * $limit;
        $total  = $this->mediaModel->countAllResults();

        return $this->render('admin/media/index', [
            'mediaList'   => $this->mediaModel->getList($limit, $offset),
            'totalPages'  => (int) ceil($total / $limit),
            'currentPage' => $page,
        ]);
    }

    /**
     * 다른 관리자 화면(사이트 설정 등)에서 쓰는 미디어 선택 모달용 목록 API.
     */
    public function pickerList(): ResponseInterface
    {
        $page   = (int) ($this->request->getGet('page') ?? 1);
        $limit  = 24;
        $offset = ($page - 1) * $limit;
        $total  = $this->mediaModel->countAllResults();

        $items = array_map(static fn (array $m): array => [
            'id'   => $m['id'],
            'path' => $m['file_path'],
            'name' => $m['original_name'],
            'alt'  => $m['alt'],
        ], $this->mediaModel->getList($limit, $offset));

        return $this->response->setJSON([
            'items'       => $items,
            'currentPage' => $page,
            'totalPages'  => (int) ceil($total / $limit),
        ]);
    }

    public function upload(): ResponseInterface|string
    {
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return $this->response->setJSON(['success' => false, 'error' => '파일 없음']);
        }

        $alt    = $this->request->getPost('alt') ?? '';
        $result = $this->uploader->upload($file, $alt);

        return $this->response->setJSON($result);
    }

    public function updateAlt(int $id): ResponseInterface|string
    {
        $this->mediaModel->update($id, ['alt' => $this->request->getPost('alt')]);

        return $this->response->setJSON(['success' => true]);
    }

    public function delete(int $id): ResponseInterface|string
    {
        $this->mediaModel->deleteWithFile($id);

        return redirect()->to('/admin/media')->with('success', '삭제되었습니다.');
    }
}
