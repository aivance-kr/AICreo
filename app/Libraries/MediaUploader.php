<?php

namespace App\Libraries;

use App\Models\MediaModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class MediaUploader
{
    private const ALLOWED  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    private readonly MediaModel $model;

    public function __construct()
    {
        $this->model = new MediaModel();
    }

    /**
     * @return array<string, mixed>
     */
    public function upload(UploadedFile $file, string $alt = ''): array
    {
        $ext = strtolower($file->getClientExtension());

        if (! in_array($ext, self::ALLOWED, true)) {
            return ['success' => false, 'error' => '이미지 파일만 업로드 가능합니다.'];
        }
        if ($file->getSize() > self::MAX_SIZE) {
            return ['success' => false, 'error' => '파일 크기는 5MB 이하여야 합니다.'];
        }

        $subDir     = date('Y/m');
        $uploadPath = FCPATH . "uploads/media/{$subDir}";

        if (! is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $storedName   = bin2hex(random_bytes(16)) . '.' . $ext;
        $relativePath = "uploads/media/{$subDir}/{$storedName}";

        $file->move($uploadPath, $storedName);

        $id = $this->model->insert([
            'original_name' => $file->getName(),
            'stored_name'   => $storedName,
            'file_path'     => $relativePath,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getClientMimeType(),
            'alt'           => $alt,
        ]);

        return [
            'success' => true,
            'id'      => $id,
            'path'    => '/' . $relativePath,
            'url'     => base_url($relativePath),
            // TinyMCE의 images_upload_url 응답 계약을 함께 지원한다.
            'location' => base_url($relativePath),
        ];
    }
}
