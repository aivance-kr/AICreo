<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RedirectModel extends Model
{
    protected $table         = 'redirects';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['old_path', 'new_path', 'status_code'];

    /**
     * @return array<string, mixed>|null
     */
    public function findByOldPath(string $oldPath): ?array
    {
        return $this->where('old_path', $oldPath)->first();
    }

    /**
     * old_path 기준 upsert — 이관 재실행 시 매핑 갱신.
     */
    public function upsert(string $oldPath, string $newPath, int $statusCode = 301): void
    {
        $existing = $this->findByOldPath($oldPath);

        if ($existing) {
            $this->update($existing['id'], ['new_path' => $newPath, 'status_code' => $statusCode]);

            return;
        }

        $this->insert(['old_path' => $oldPath, 'new_path' => $newPath, 'status_code' => $statusCode]);
    }
}
