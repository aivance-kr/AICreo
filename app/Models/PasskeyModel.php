<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 관리자 패스키(WebAuthn 자격 증명).
 */
class PasskeyModel extends Model
{
    protected $table         = 'passkeys';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'name', 'credential_hash', 'credential', 'last_used_at'];

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return $this->where('user_id', $userId)->orderBy('id', 'ASC')->findAll();
    }

    /**
     * 자격 증명 ID(원본 바이트)로 찾는다.
     *
     * @return array<string, mixed>|null
     */
    public function findByCredentialId(string $rawId): ?array
    {
        return $this->where('credential_hash', self::hash($rawId))->first();
    }

    public static function hash(string $rawId): string
    {
        return hash('sha256', $rawId);
    }
}
