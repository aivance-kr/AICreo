<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * 뉴스레터 구독자. 이중 확인(pending → confirmed)을 거친 주소에만 발송하고,
 * 해지(unsubscribed)한 주소는 다시 확인하기 전까지 발송 대상에서 빠진다.
 */
class SubscriberModel extends Model
{
    public const STATUS_PENDING      = 'pending';
    public const STATUS_CONFIRMED    = 'confirmed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $table         = 'subscribers';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'email', 'status', 'confirm_token', 'unsubscribe_token', 'confirmed_at', 'unsubscribed_at',
    ];

    /**
     * 구독을 신청한다. 확인 메일을 보내야 하면 구독자 행을, 보낼 필요가 없으면(이미 확인됨) null 을 돌려준다.
     * 확인 대기·해지 상태 주소는 토큰을 새로 발급해 다시 확인받는다.
     *
     * @return array<string, mixed>|null
     */
    public function subscribe(string $email): ?array
    {
        $email    = mb_strtolower(trim($email));
        $existing = $this->where('email', $email)->first();

        if ($existing !== null && $existing['status'] === self::STATUS_CONFIRMED) {
            return null;
        }

        $fields = [
            'status'            => self::STATUS_PENDING,
            'confirm_token'     => self::newToken(),
            'unsubscribe_token' => $existing['unsubscribe_token'] ?? self::newToken(),
            'confirmed_at'      => null,
            'unsubscribed_at'   => null,
        ];

        if ($existing === null) {
            $id = (int) $this->insert($fields + ['email' => $email]);
        } else {
            $id = (int) $existing['id'];
            $this->update($id, $fields);
        }

        return $this->find($id);
    }

    /**
     * 확인 링크를 처리한다. 이미 확인된 주소의 재클릭은 성공으로 본다. 해지한 주소의 오래된 링크는 거절한다.
     */
    public function confirm(string $token): bool
    {
        $row = $this->where('confirm_token', $token)->first();
        if ($row === null || $row['status'] === self::STATUS_UNSUBSCRIBED) {
            return false;
        }

        if ($row['status'] === self::STATUS_PENDING) {
            $this->update((int) $row['id'], [
                'status'       => self::STATUS_CONFIRMED,
                'confirmed_at' => Time::now()->toDateTimeString(),
            ]);
        }

        return true;
    }

    /**
     * 해지한다. 아직 보내지 않은 큐도 비워 해지 후 재발송이 없게 한다.
     */
    public function unsubscribe(string $token): bool
    {
        $row = $this->where('unsubscribe_token', $token)->first();
        if ($row === null) {
            return false;
        }

        if ($row['status'] !== self::STATUS_UNSUBSCRIBED) {
            $this->update((int) $row['id'], [
                'status'          => self::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => Time::now()->toDateTimeString(),
            ]);
            $this->db->table('newsletter_deliveries')->where('subscriber_id', (int) $row['id'])->where('sent_at IS NULL')->delete();
        }

        return true;
    }

    /**
     * 구독자를 지운다(관리자). 큐 행도 함께 지운다.
     */
    public function remove(int $id): void
    {
        $this->db->table('newsletter_deliveries')->where('subscriber_id', $id)->delete();
        $this->delete($id);
    }

    /**
     * 글을 발송 큐에 올린다 — 글당 한 번만. 표식을 먼저 선점해 동시 저장에도 중복으로 쌓이지 않는다.
     * 큐에는 그 시점에 확인된 구독자만 들어가므로 나중에 구독한 사람은 옛 글을 받지 않는다.
     */
    public function enqueuePost(int $postId): void
    {
        $now = Time::now()->toDateTimeString();

        $this->db->transStart();
        $this->db->table('posts')->where('id', $postId)->where('newsletter_enqueued_at IS NULL')
            ->set('newsletter_enqueued_at', $now)->set('updated_at', 'updated_at', false)->update();

        if ($this->db->affectedRows() === 1) {
            $this->db->query(
                'INSERT IGNORE INTO `newsletter_deliveries` (`post_id`, `subscriber_id`, `created_at`) '
                . 'SELECT ?, `id`, ? FROM `subscribers` WHERE `status` = ?',
                [$postId, $now, self::STATUS_CONFIRMED],
            );
        }
        $this->db->transComplete();
    }

    /**
     * 보낼 차례인 발송 건. 확인된 구독자 + 지금도 공개 중인 글만 — 그 사이 해지·숨김된 건은 건너뛴다.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingDeliveries(int $limit): array
    {
        return $this->db->table('newsletter_deliveries d')
            ->select('d.id AS delivery_id, s.id AS subscriber_id, s.email, s.unsubscribe_token, '
                . 'p.id AS post_id, p.title, p.content, b.slug AS board_slug')
            ->join('subscribers s', 's.id = d.subscriber_id AND s.status = \'' . self::STATUS_CONFIRMED . '\'')
            ->join('posts p', 'p.id = d.post_id AND p.is_active = 1 AND p.is_secret = 0 AND p.deleted_at IS NULL')
            ->join('boards b', 'b.id = p.board_id AND b.is_active = 1 AND b.read_permission = \'guest\'')
            ->where('d.sent_at IS NULL')
            ->orderBy('d.id', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function markSent(int $deliveryId): void
    {
        $this->db->table('newsletter_deliveries')->where('id', $deliveryId)->update(['sent_at' => Time::now()->toDateTimeString()]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(string $keyword, string $status, int $page, int $perPage): array
    {
        $builder = $this->builderFor($keyword, $status);
        $total   = $builder->countAllResults(false);
        $items   = $builder->orderBy('id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * 내보내기용 전체 목록(검색 조건 동일).
     *
     * @return list<array<string, mixed>>
     */
    public function exportAll(string $keyword, string $status): array
    {
        return $this->builderFor($keyword, $status)->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /**
     * @return array<string, int> 상태별 인원
     */
    public function countByStatus(): array
    {
        $counts = [self::STATUS_PENDING => 0, self::STATUS_CONFIRMED => 0, self::STATUS_UNSUBSCRIBED => 0];

        foreach ($this->db->table($this->table)->select('status, COUNT(*) AS cnt')->groupBy('status')->get()->getResultArray() as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * @return BaseBuilder
     */
    private function builderFor(string $keyword, string $status)
    {
        $builder = $this->db->table($this->table)
            ->select('id, email, status, confirmed_at, unsubscribed_at, created_at');

        if ($keyword !== '') {
            $builder->like('email', $keyword);
        }
        if (in_array($status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_UNSUBSCRIBED], true)) {
            $builder->where('status', $status);
        }

        return $builder;
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
