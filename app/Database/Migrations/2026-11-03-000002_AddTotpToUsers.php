<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 관리자 2단계 인증(TOTP) 컬럼 (#559).
 *
 * totp_secret 은 암호화한 값, totp_recovery_codes 는 해시 목록(JSON)만 저장한다.
 */
class AddTotpToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'totp_secret'         => ['type' => 'TEXT', 'null' => true],
            'totp_enabled_at'     => ['type' => 'TIMESTAMP', 'null' => true],
            'totp_recovery_codes' => ['type' => 'TEXT', 'null' => true],
            'totp_last_step'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'totp_failures'       => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'totp_locked_until'   => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', [
            'totp_secret', 'totp_enabled_at', 'totp_recovery_codes',
            'totp_last_step', 'totp_failures', 'totp_locked_until',
        ]);
    }
}
