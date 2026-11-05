<?php

declare(strict_types=1);

namespace Plugins\Sample\Models;

use CodeIgniter\Events\Events;
use CodeIgniter\Model;

class SampleNoteModel extends Model
{
    protected $table         = 'sample_notes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['body'];
    protected $useTimestamps = false;

    /**
     * 메모를 저장하고 `sample.note_added` 이벤트를 발행한다.
     *
     * 이벤트는 과거형 사실 통지다 — 페이로드는 snake_case 식별자만 담는다.
     */
    public function add(string $body): int
    {
        $this->insert(['body' => $body]);
        $id = (int) $this->getInsertID();

        Events::trigger('sample.note_added', ['note_id' => $id]);

        return $id;
    }

    /**
     * @return list<array{id: int|string, body: string, created_at: string}>
     */
    public function latest(int $limit = 20): array
    {
        /** @var list<array{id: int|string, body: string, created_at: string}> */
        return $this->select('id, body, created_at')->orderBy('id', 'DESC')->findAll($limit);
    }
}
