<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * 관리자 활동 로그. 누가 언제 무엇을 바꿨는지 남긴다.
 * summary 에는 값이 아니라 무엇이 바뀌었는지만 적는다(비밀번호·토큰 같은 민감 값 금지).
 */
class AdminLogModel extends Model
{
    public const SUMMARY_MAX = 255;

    /**
     * 화면 필터·표시용 대상 종류 이름
     *
     * @var array<string, string>
     */
    public const TARGET_TYPES = [
        'post'       => '글',
        'page'       => '페이지',
        'setting'    => '설정',
        'user'       => '회원',
        'blocked_ip' => '차단 IP',
    ];

    /**
     * @var array<string, string>
     */
    public const ACTIONS = [
        'create'  => '등록',
        'update'  => '수정',
        'delete'  => '삭제',
        'toggle'  => '노출 변경',
        'block'   => '차단',
        'unblock' => '차단 해제',
        'restore' => '복원',
    ];

    protected $table         = 'admin_logs';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['user_id', 'action', 'target_type', 'target_id', 'summary', 'ip_address'];

    /**
     * 활동 한 건을 남긴다. 호출자가 사용자·IP 를 주지 않으면 현재 세션·요청에서 얻는다.
     */
    public function record(
        string $action,
        string $targetType,
        ?int $targetId = null,
        string $summary = '',
        ?int $userId = null,
        ?string $ipAddress = null,
    ): void {
        $userId ??= (int) session()->get('user_id') ?: null;

        if ($ipAddress === null && ! is_cli()) {
            $ipAddress = service('request')->getIPAddress();
        }

        $this->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'summary'     => $summary === '' ? null : mb_substr($summary, 0, self::SUMMARY_MAX),
            'ip_address'  => $ipAddress,
        ]);
    }

    /**
     * 변경한 항목 이름만 요약한다. 값은 넣지 않는다.
     *
     * @param list<string> $keys
     */
    public static function keysSummary(array $keys): string
    {
        return '변경 항목: ' . implode(', ', $keys);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(string $action, string $targetType, string $keyword, int $page, int $perPage): array
    {
        $builder = $this->db->table('admin_logs l')
            ->select('l.id, l.user_id, l.action, l.target_type, l.target_id, l.summary, l.ip_address, l.created_at, u.nickname')
            ->join('users u', 'u.id = l.user_id', 'left');

        if ($action !== '') {
            $builder->where('l.action', $action);
        }
        if ($targetType !== '') {
            $builder->where('l.target_type', $targetType);
        }
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('l.summary', $keyword)
                ->orLike('l.ip_address', $keyword)
                ->orLike('u.nickname', $keyword)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $items = $builder->orderBy('l.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * 보관 기간이 지난 로그를 지운다.
     */
    public function purgeOlderThan(int $days): int
    {
        $this->where('created_at <', Time::now()->subDays($days)->toDateTimeString())->delete();

        return $this->db->affectedRows();
    }
}
