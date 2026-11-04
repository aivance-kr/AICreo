<?php

namespace App\Models;

use CodeIgniter\Model;

class InquiryModel extends Model
{
    protected $table         = 'inquiries';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = [
        'name', 'email', 'phone', 'subject', 'message', 'extra_data', 'ip_address', 'is_read',
    ];

    /**
     * 저장된 추가 항목(JSON)을 목록으로 푼다. 기존 문의(NULL)나 깨진 값은 빈 목록이다.
     *
     * @param array<string, mixed> $inquiry
     *
     * @return list<array{label: string, type: string, value: string, file?: string}>
     */
    public static function extraOf(array $inquiry): array
    {
        $decoded = json_decode((string) ($inquiry['extra_data'] ?? ''), true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = [];

        foreach ($decoded as $item) {
            if (is_array($item) && isset($item['label'], $item['value'])) {
                $items[] = [
                    'label' => (string) $item['label'],
                    'type'  => (string) ($item['type'] ?? 'text'),
                    'value' => (string) $item['value'],
                ] + (isset($item['file']) ? ['file' => (string) $item['file']] : []);
            }
        }

        return $items;
    }

    public function getUnreadCount(): int
    {
        return $this->where('is_read', 0)->countAllResults();
    }

    public function markRead(int $id): void
    {
        $this->update($id, ['is_read' => 1]);
    }
}
