<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 웹훅·알림 연동(#568): 발송 큐 테이블과 설정(알림 탭) 항목.
 * 웹훅 URL 은 settings 에만 두고 큐 행에는 복사하지 않는다(토큰이 URL 에 들어 있는 경우가 많다).
 */
class CreateWebhookDeliveries extends Migration
{
    /**
     * @var array<string, array{0: string, 1: string, 2: string}> key => [값, 라벨, 타입]
     */
    private const SETTINGS = [
        'webhook_url'           => ['', '웹훅 URL (https://…)', 'text'],
        'webhook_on_inquiry'    => ['1', '새 문의 접수 시 알림', 'boolean'],
        'webhook_on_comment'    => ['1', '댓글 작성 시 알림', 'boolean'],
        'webhook_on_subscriber' => ['1', '뉴스레터 구독 신청 시 알림', 'boolean'],
    ];

    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'event'           => ['type' => 'VARCHAR', 'constraint' => 30],
            'payload'         => ['type' => 'TEXT'],
            'attempts'        => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'next_attempt_at' => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'sent_at'         => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'failed_at'       => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'last_error'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['sent_at', 'failed_at', 'next_attempt_at'], false, false, 'idx_webhook_deliveries_pending');
        $this->forge->createTable('webhook_deliveries', true);

        $now = date('Y-m-d H:i:s');

        foreach (self::SETTINGS as $key => [$value, $label, $type]) {
            if ($this->db->table('settings')->where('key', $key)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('settings')->insert([
                'group' => 'notify', 'key' => $key, 'value' => $value,
                'label' => $label, 'type' => $type, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('settings')->whereIn('key', array_keys(self::SETTINGS))->delete();
        $this->forge->dropTable('webhook_deliveries', true);
    }
}
