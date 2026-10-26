<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

class ImageUploader
{
    private const ALLOWED       = ['jpg', 'jpeg', 'png', 'gif'];
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif'];
    private const MAX_SIZE      = 2 * 1024 * 1024; // 2MB

    public function __construct(private readonly string $folder)
    {
    }

    /**
     * @return array{success: bool, path?: string, error?: string}
     */
    public function upload(UploadedFile $file): array
    {
        $ext  = strtolower($file->getClientExtension());
        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_MIMES, true) || ! in_array($ext, self::ALLOWED, true)) {
            return ['success' => false, 'error' => 'jpg, jpeg, png, gif 파일만 업로드 가능합니다.'];
        }
        if ($file->getSize() > self::MAX_SIZE) {
            return ['success' => false, 'error' => '파일 크기는 2MB 이하여야 합니다.'];
        }

        $subDir     = date('Y/m');
        $uploadPath = FCPATH . "uploads/{$this->folder}/{$subDir}";

        if (! is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $storedName   = bin2hex(random_bytes(16)) . '.' . $ext;
        $relativePath = "uploads/{$this->folder}/{$subDir}/{$storedName}";

        $file->move($uploadPath, $storedName);
        Thumbnail::create($relativePath);

        return ['success' => true, 'path' => $relativePath];
    }
}
