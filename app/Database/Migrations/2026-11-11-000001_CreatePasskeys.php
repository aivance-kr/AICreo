<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 관리자 패스키(WebAuthn) 로그인.
 *
 * credential 은 web-auth/webauthn-lib 가 직렬화한 CredentialRecord(JSON)이고, 서명 횟수(counter)가
 * 갱신될 때마다 같이 갱신한다. 조회용으로 자격 증명 ID 의 SHA-256 을 따로 둔다(ID 는 최대 1023바이트).
 */
class CreatePasskeys extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'         => ['type' => 'INT', 'unsigned' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'credential_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'credential'      => ['type' => 'TEXT'],
            'last_used_at'    => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'created_at'      => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at'      => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('credential_hash', 'uniq_passkeys_credential_hash');
        $this->forge->addKey('user_id', false, false, 'idx_passkeys_user_id');
        $this->forge->createTable('passkeys', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('passkeys', true);
    }
}
