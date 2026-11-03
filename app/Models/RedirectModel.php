<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RedirectModel extends Model
{
    public const STATUS_CODES = [301 => '301 영구 이동', 302 => '302 임시 이동'];
    private const CHAIN_LIMIT = 20;

    protected $table         = 'redirects';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['old_path', 'new_path', 'status_code', 'is_active', 'hit_count'];

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

    /**
     * 사용 중인 매핑만. 적중 시 RedirectFilter 가 쓴다.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveByOldPath(string $oldPath): ?array
    {
        return $this->where('old_path', $oldPath)->where('is_active', 1)->first();
    }

    public function recordHit(int $id): void
    {
        $this->builder()->where('id', $id)->set('hit_count', 'hit_count + 1', false)->update();
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(string $keyword, int $page, int $perPage): array
    {
        if ($keyword !== '') {
            $this->groupStart()->like('old_path', $keyword)->orLike('new_path', $keyword)->groupEnd();
        }

        $total = $this->countAllResults(false);
        $items = $this->orderBy('id', 'DESC')->findAll($perPage, ($page - 1) * $perPage);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * 저장 전 검증. 문제가 있으면 사용자에게 보일 메시지, 없으면 null.
     * 같은 old_path 중복과 순환(A→B→…→A, 자기 자신)을 막는다. 사용 안 함 상태인 매핑도 순환 계산에 넣는다
     * (다시 켰을 때 순환이 생기지 않게).
     */
    public function validationError(string $oldPath, string $newPath, ?int $exceptId = null): ?string
    {
        $dup = $this->where('old_path', $oldPath)->first();
        if ($dup !== null && (int) $dup['id'] !== $exceptId) {
            return '이미 등록된 원 주소입니다.';
        }

        $current = $newPath;

        for ($i = 0; $i < self::CHAIN_LIMIT; $i++) {
            if ($current === $oldPath) {
                return '순환 리다이렉트가 됩니다. 대상이 다시 원 주소로 돌아옵니다.';
            }

            $next = $this->where('old_path', $current)->first();
            if ($next === null || (int) $next['id'] === $exceptId) {
                return null;
            }

            $current = (string) $next['new_path'];
        }

        return '리다이렉트 연결이 너무 깁니다.';
    }
}
