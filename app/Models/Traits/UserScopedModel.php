<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Exceptions\UserScopeMissingException;

/**
 * 플러그인 테이블에 user_id 스코프를 강제하는 트레이트.
 *
 * CI4 Model 의 before* 콜백에 붙어 조회·삽입·수정·삭제 전부에 user_id 를
 * 자동 주입·필터한다. 쿼리에서 user_id 가 빠지는 것은 버그가 아니라
 * 보안 결함이므로, 스코프를 결정할 수 없으면 조용히 넘어가지 않고 예외를 던진다.
 *
 * 이 트레이트를 쓰는 모델은 before* 콜백 프로퍼티를 직접 선언하지 않는다 —
 * 트레이트가 점유한다.
 */
trait UserScopedModel
{
    /**
     * @var list<string>
     */
    protected $beforeFind = ['scopeFindToUser'];

    /**
     * @var list<string>
     */
    protected $beforeInsert = ['injectUserId'];

    /**
     * @var list<string>
     */
    protected $beforeUpdate = ['scopeUpdateToUser'];

    /**
     * @var list<string>
     */
    protected $beforeDelete = ['scopeDeleteToUser'];

    protected $allowCallbacks       = true;
    private ?int $scopedUserId      = null;
    private bool $userScopeDisabled = false;

    /**
     * 스코프를 명시한다. CLI·워커·테스트처럼 세션이 없는 컨텍스트에서 쓴다.
     */
    public function forUser(int $userId): static
    {
        $this->scopedUserId = $userId;

        return $this;
    }

    /**
     * 스코프를 끈 채로 콜백을 실행한다. 관리자 전역 조회처럼
     * 정당한 우회를 여기 한 곳으로 모아 추적 가능하게 한다.
     *
     * @template T
     *
     * @param callable(static): T $callback
     *
     * @return T
     */
    public function withoutUserScope(callable $callback): mixed
    {
        $previous                = $this->userScopeDisabled;
        $this->userScopeDisabled = true;

        try {
            return $callback($this);
        } finally {
            $this->userScopeDisabled = $previous;
        }
    }

    /**
     * @param array<string, mixed> $eventData
     *
     * @return array<string, mixed>
     */
    protected function scopeFindToUser(array $eventData): array
    {
        if (! $this->userScopeDisabled) {
            $this->where($this->table . '.user_id', $this->requireUserId());
        }

        return $eventData;
    }

    /**
     * @param array<string, mixed> $eventData
     *
     * @return array<string, mixed>
     */
    protected function injectUserId(array $eventData): array
    {
        if (! $this->userScopeDisabled && is_array($eventData['data'] ?? null)) {
            $eventData['data']['user_id'] = $this->requireUserId();
        }

        return $eventData;
    }

    /**
     * @param array<string, mixed> $eventData
     *
     * @return array<string, mixed>
     */
    protected function scopeUpdateToUser(array $eventData): array
    {
        if (! $this->userScopeDisabled) {
            $this->where($this->table . '.user_id', $this->requireUserId());
        }

        return $eventData;
    }

    /**
     * @param array<string, mixed> $eventData
     *
     * @return array<string, mixed>
     */
    protected function scopeDeleteToUser(array $eventData): array
    {
        if (! $this->userScopeDisabled) {
            $this->where($this->table . '.user_id', $this->requireUserId());
        }

        return $eventData;
    }

    protected function requireUserId(): int
    {
        if ($this->scopedUserId !== null) {
            return $this->scopedUserId;
        }

        $sessionUserId = session()->get('user_id');

        if (is_int($sessionUserId) || (is_string($sessionUserId) && ctype_digit($sessionUserId))) {
            return (int) $sessionUserId;
        }

        throw UserScopeMissingException::create($this->table);
    }
}
