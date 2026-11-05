<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Exceptions\UserScopeMissingException;
use Config\Database;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Models\ScopedNoteModel;

/**
 * @internal
 */
final class UserScopedModelTest extends DatabaseTestCase
{
    /**
     * 트레이트 검증용 테이블은 앱 스키마에 속하지 않는 테스트 스캐폴딩이라
     * 마이그레이션이 아니라 Forge 로 직접 만든다.
     *
     * 반드시 테스트별 트랜잭션이 시작되기 전에 만들어야 한다 — MySQL 은
     * CREATE TABLE 에서 암묵적 커밋을 일으켜 진행 중인 트랜잭션을 끝내버리고,
     * 그러면 그 테스트가 넣은 행이 롤백되지 않고 다음 테스트로 샌다.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = Database::connect('tests');

        if ($db->tableExists('scoped_notes')) {
            return;
        }

        $forge = Database::forge('tests');
        $forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('user_id', false, false, 'idx_scoped_notes_user_id');
        $forge->createTable('scoped_notes');
    }

    protected function setUp(): void
    {
        parent::setUp();

        session()->remove('user_id');
    }

    public function testInsertInjectsUserId(): void
    {
        $id = (new ScopedNoteModel())->forUser(7)->insert(['body' => '내 메모'], true);

        $row = (new ScopedNoteModel())->withoutUserScope(
            static fn (ScopedNoteModel $m): ?array => $m->find($id),
        );

        $this->assertSame(7, (int) $row['user_id']);
    }

    public function testFindAllReturnsOnlyOwnRows(): void
    {
        (new ScopedNoteModel())->forUser(7)->insert(['body' => '내 것']);
        (new ScopedNoteModel())->forUser(8)->insert(['body' => '남의 것']);

        $rows = (new ScopedNoteModel())->forUser(7)->findAll();

        $this->assertCount(1, $rows);
        $this->assertSame('내 것', $rows[0]['body']);
    }

    public function testFindCannotReachOtherUsersRow(): void
    {
        $otherId = (new ScopedNoteModel())->forUser(8)->insert(['body' => '남의 것'], true);

        $this->assertNull((new ScopedNoteModel())->forUser(7)->find($otherId));
    }

    public function testUpdateCannotTouchOtherUsersRow(): void
    {
        $otherId = (new ScopedNoteModel())->forUser(8)->insert(['body' => '남의 것'], true);

        (new ScopedNoteModel())->forUser(7)->update($otherId, ['body' => '탈취']);

        $row = (new ScopedNoteModel())->forUser(8)->find($otherId);
        $this->assertSame('남의 것', $row['body']);
    }

    public function testDeleteCannotRemoveOtherUsersRow(): void
    {
        $otherId = (new ScopedNoteModel())->forUser(8)->insert(['body' => '남의 것'], true);

        (new ScopedNoteModel())->forUser(7)->delete($otherId);

        $this->assertNotNull((new ScopedNoteModel())->forUser(8)->find($otherId));
    }

    public function testThrowsWhenNoUserInSessionOrScope(): void
    {
        $this->expectException(UserScopeMissingException::class);
        (new ScopedNoteModel())->findAll();
    }

    public function testFallsBackToSessionUserId(): void
    {
        session()->set('user_id', 9);

        (new ScopedNoteModel())->insert(['body' => '세션 사용자']);
        $rows = (new ScopedNoteModel())->findAll();

        $this->assertCount(1, $rows);
        $this->assertSame(9, (int) $rows[0]['user_id']);
    }

    public function testWithoutUserScopeSeesEveryRow(): void
    {
        (new ScopedNoteModel())->forUser(7)->insert(['body' => '내 것']);
        (new ScopedNoteModel())->forUser(8)->insert(['body' => '남의 것']);

        $rows = (new ScopedNoteModel())->withoutUserScope(
            static fn (ScopedNoteModel $m): array => $m->findAll(),
        );

        $this->assertCount(2, $rows);
    }
}
