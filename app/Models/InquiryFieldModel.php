<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 문의 폼의 추가 항목 정의. 이름·이메일·내용 같은 기본 항목은 고정이고, 여기 등록한 항목이 뒤에 붙는다.
 */
class InquiryFieldModel extends Model
{
    public const TYPES = [
        'text'     => '한 줄 텍스트',
        'textarea' => '여러 줄 텍스트',
        'select'   => '선택(드롭다운)',
        'checkbox' => '체크(선택지 없으면 동의 체크 1개)',
        'file'     => '파일',
    ];

    protected $table         = 'inquiry_fields';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['label', 'field_type', 'options', 'is_required', 'is_active', 'sort_order'];

    /**
     * 공개 폼에 그릴 사용 중인 항목 (순서대로).
     *
     * @return list<array<string, mixed>>
     */
    public function activeFields(): array
    {
        return $this->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allOrdered(): array
    {
        return $this->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * 새 항목이 맨 뒤에 오도록 다음 순서 값을 돌려준다.
     */
    public function nextSortOrder(): int
    {
        $row = $this->selectMax('sort_order')->first();

        return (int) ($row['sort_order'] ?? 0) + 1;
    }
}
