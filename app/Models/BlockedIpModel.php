<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

class BlockedIpModel extends Model
{
    public const REASON_AUTO   = 'auto';
    public const REASON_MANUAL = 'manual';
    public const BLOCK_DAYS    = 30;

    protected $table         = 'blocked_ips';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['ip_address', 'reason', 'blocked_until'];

    /**
     * IP 를 BLOCK_DAYS 일간 차단한다. 이미 등록된 IP 는 차단 기간만 새로 늘린다.
     */
    public function block(string $ipAddress, string $reason): void
    {
        $until    = Time::now()->addDays(self::BLOCK_DAYS)->toDateTimeString();
        $existing = $this->where('ip_address', $ipAddress)->first();

        if ($existing === null) {
            $this->insert(['ip_address' => $ipAddress, 'reason' => $reason, 'blocked_until' => $until]);

            return;
        }

        $this->update($existing['id'], ['reason' => $reason, 'blocked_until' => $until]);
    }

    public function isBlocked(string $ipAddress): bool
    {
        return $this->where('ip_address', $ipAddress)
            ->where('blocked_until >', Time::now()->toDateTimeString())
            ->countAllResults() > 0;
    }

    /**
     * 차단 기간이 끝난 행을 지운다.
     */
    public function purgeExpired(): int
    {
        $this->where('blocked_until <=', Time::now()->toDateTimeString())->delete();

        return $this->db->affectedRows();
    }
}
