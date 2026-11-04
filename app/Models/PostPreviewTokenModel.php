<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * 비공개·예약 글 미리보기 공유 링크 토큰. 토큰을 아는 사람은 로그인 없이 글을 읽기 전용으로 볼 수 있다.
 */
class PostPreviewTokenModel extends Model
{
    /**
     * 만료 기간 상한(일)
     */
    public const MAX_DAYS = 90;

    protected $table         = 'post_preview_tokens';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['post_id', 'token', 'expires_at', 'revoked_at'];

    /**
     * 토큰을 발급하고 값을 돌려준다.
     *
     * @throws InvalidArgumentException 기간이 1~MAX_DAYS 일을 벗어나면
     */
    public function issue(int $postId, int $days): string
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw new InvalidArgumentException('만료 기간은 1~' . self::MAX_DAYS . '일이어야 합니다.');
        }

        $token = bin2hex(random_bytes(32));
        $this->insert([
            'post_id'    => $postId,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400),
        ]);

        return $token;
    }

    /**
     * 만료·폐기되지 않은 토큰이 가리키는 글(삭제된 글은 제외). 유효하지 않으면 null.
     *
     * @return array<string, mixed>|null
     */
    public function findValid(string $token): ?array
    {
        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return null;
        }

        $row = $this->where('token', $token)
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();

        return $row === null ? null : model(PostModel::class)->getDetail((int) $row['post_id']);
    }

    /**
     * 최신순 발급 목록.
     *
     * @return list<array<string, mixed>>
     */
    public function listByPost(int $postId): array
    {
        return $this->where('post_id', $postId)->orderBy('id', 'DESC')->findAll();
    }

    /**
     * 글에 속한 토큰을 폐기한다. 다른 글의 토큰이거나 이미 폐기됐으면 false.
     */
    public function revoke(int $postId, int $id): bool
    {
        $this->where('post_id', $postId)->where('revoked_at', null)->where('id', $id)
            ->set('revoked_at', date('Y-m-d H:i:s'))->update();

        return $this->db->affectedRows() > 0;
    }
}
