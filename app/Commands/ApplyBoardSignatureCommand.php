<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\BoardModel;
use App\Models\SignatureModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 게시판의 서명 없는 기존 글 전체에 운영자의 첫 번째 서명을 일괄 삽입한다.
 * 서명을 먼저 등록(/admin/settings/signatures)한 뒤 실행해야 한다.
 */
final class ApplyBoardSignatureCommand extends BaseCommand
{
    protected $group       = 'Board';
    protected $name        = 'signature:apply-board';
    protected $description = '게시판의 서명 없는 모든 글에 운영자의 첫 번째 서명을 넣습니다.';
    protected $usage       = 'signature:apply-board <게시판 slug> [운영자 username]';
    protected $arguments   = [
        '게시판 slug'     => '대상 게시판 slug (예: up0)',
        '운영자 username' => '서명 주인. 생략하면 서명을 가진 가장 오래된 운영자',
    ];

    public function run(array $params): void
    {
        $slug = $params[0] ?? null;
        if ($slug === null) {
            CLI::error('게시판 slug 를 지정하세요. 예: php spark signature:apply-board up0');

            return;
        }

        $board = (new BoardModel())->getBySlug($slug);
        if ($board === null) {
            CLI::error("게시판 '{$slug}' 를 찾을 수 없습니다.");

            return;
        }

        $signature = $this->findSignature($params[1] ?? null);
        if ($signature === null) {
            CLI::error('적용할 서명이 없습니다. 운영자 서명을 먼저 등록하세요.');

            return;
        }

        $affected = $this->apply((int) $board['id'], (string) $signature['content']);
        CLI::write("게시판 {$slug} 의 글 {$affected}건에 서명 '{$signature['name']}' 을 넣었습니다.", 'green');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSignature(?string $username): ?array
    {
        $model = new SignatureModel();

        if ($username !== null) {
            $user = (new UserModel())->where(['username' => $username, 'role' => 'admin'])->first();

            return $user === null ? null : $model->firstByUser((int) $user['id']);
        }

        return $model->select('signatures.*')
            ->join('users', 'users.id = signatures.user_id')
            ->where('users.role', 'admin')
            ->orderBy('signatures.id')
            ->first();
    }

    /**
     * 서명이 비어 있는 글(소프트삭제 포함)에만 넣어 재실행해도 중복되지 않는다.
     */
    private function apply(int $boardId, string $content): int
    {
        $db = db_connect();
        $db->table('posts')
            ->where('board_id', $boardId)
            ->groupStart()->where('signature', null)->orWhere('signature', '')->groupEnd()
            ->update(['signature' => $content]);

        return $db->affectedRows();
    }
}
