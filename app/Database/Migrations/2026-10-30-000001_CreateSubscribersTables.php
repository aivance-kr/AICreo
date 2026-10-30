<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubscribersTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 190],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'confirm_token'     => ['type' => 'CHAR', 'constraint' => 64],
            'unsubscribe_token' => ['type' => 'CHAR', 'constraint' => 64],
            'confirmed_at'      => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'unsubscribed_at'   => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'created_at'        => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at'        => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email', 'uniq_subscribers_email');
        $this->forge->addUniqueKey('confirm_token', 'uniq_subscribers_confirm_token');
        $this->forge->addUniqueKey('unsubscribe_token', 'uniq_subscribers_unsubscribe_token');
        $this->forge->createTable('subscribers');

        // 발송 큐 — 글이 공개될 때 확인된 구독자마다 한 줄씩 쌓고, newsletter:send 가 비운다
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'post_id'       => ['type' => 'INT', 'unsigned' => true],
            'subscriber_id' => ['type' => 'INT', 'unsigned' => true],
            'sent_at'       => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['post_id', 'subscriber_id'], 'uniq_newsletter_deliveries_post_subscriber');
        $this->forge->addKey(['sent_at', 'id'], false, false, 'idx_newsletter_deliveries_sent_at');
        $this->forge->createTable('newsletter_deliveries');

        // 글당 한 번만 큐에 올리기 위한 표식. 기존 글은 구독 기능 이전에 공개된 것이라 지금 시각으로 채워 대량 발송을 막는다
        $this->forge->addColumn('posts', [
            'newsletter_enqueued_at' => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null, 'after' => 'publish_at'],
        ]);
        $this->db->query('UPDATE `posts` SET `newsletter_enqueued_at` = CURRENT_TIMESTAMP, `updated_at` = `updated_at`');
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'newsletter_enqueued_at');
        $this->forge->dropTable('newsletter_deliveries', true);
        $this->forge->dropTable('subscribers', true);
    }
}
