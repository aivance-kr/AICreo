<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use App\Models\Traits\UserScopedModel;
use CodeIgniter\Model;

/**
 * UserScopedModel 트레이트 검증용 테스트 전용 모델.
 *
 * @internal
 */
final class ScopedNoteModel extends Model
{
    use UserScopedModel;

    protected $table         = 'scoped_notes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    /**
     * @var list<string>
     */
    protected $allowedFields = ['user_id', 'body'];
}
