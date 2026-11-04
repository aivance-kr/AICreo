<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\InquiryFieldModel;
use App\Models\InquiryModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class InquiryFieldTest extends AdminTestCase
{
    private function makeField(string $label, string $type = 'text', int $required = 0, ?string $options = null): int
    {
        $model = new InquiryFieldModel();

        return (int) $model->insert([
            'label'       => $label, 'field_type' => $type, 'options' => $options,
            'is_required' => $required, 'is_active' => 1, 'sort_order' => $model->nextSortOrder(),
        ]);
    }

    public function testAdminAddsField(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/inquiry-fields', [
            'label' => '회사명', 'field_type' => 'text', 'is_required' => '1', 'is_active' => '1',
        ]);

        $result->assertRedirectTo('/admin/inquiry-fields');
        $row = (new InquiryFieldModel())->where('label', '회사명')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['is_required']);
    }

    public function testSelectWithoutOptionsIsRejected(): void
    {
        $this->withSession($this->adminSession)->post('admin/inquiry-fields', ['label' => '예산', 'field_type' => 'select']);

        $this->assertNull((new InquiryFieldModel())->where('label', '예산')->first());
    }

    public function testMoveSwapsOrder(): void
    {
        $a = $this->makeField('A');
        $b = $this->makeField('B');

        $this->withSession($this->adminSession)->post("admin/inquiry-fields/{$b}/up");

        $labels = array_column((new InquiryFieldModel())->allOrdered(), 'label');
        $this->assertSame(['B', 'A'], array_slice($labels, 0, 2));
        $this->assertNotSame($a, $b);
    }

    public function testNonAdminCannotAccess(): void
    {
        $result = $this->withSession($this->memberSession)->get('admin/inquiry-fields');

        $this->assertNotSame(200, $result->response()->getStatusCode());
    }

    public function testPublicSubmitStoresExtraAndHoneypotSkips(): void
    {
        $fieldId = $this->makeField('회사명', 'text', 1);
        $base    = ['name' => '홍길동', 'email' => 'a@example.com', 'message' => '열 글자 이상 문의 내용입니다'];

        // 필수 항목 누락 → 저장 안 됨
        $this->post('inquiry/submit', $base);
        $this->assertSame(0, (new InquiryModel())->where('email', 'a@example.com')->countAllResults());

        // 정상 제출
        $this->post('inquiry/submit', $base + ['cf' => [$fieldId => '<b>에이비씨</b>']]);
        $saved = (new InquiryModel())->where('email', 'a@example.com')->first();
        $this->assertNotNull($saved);
        $this->assertSame([['label' => '회사명', 'type' => 'text', 'value' => '<b>에이비씨</b>']], InquiryModel::extraOf($saved));

        // 허니팟
        $this->post('inquiry/submit', ['email' => 'bot@example.com', 'website' => 'http://spam'] + $base);
        $this->assertSame(0, (new InquiryModel())->where('email', 'bot@example.com')->countAllResults());
    }

    public function testViewEscapesExtraAndLegacyInquiryStillRenders(): void
    {
        $model  = new InquiryModel();
        $legacy = (int) $model->insert(['name' => '옛', 'email' => 'old@example.com', 'message' => '내용', 'ip_address' => '127.0.0.1']);
        $new    = (int) $model->insert([
            'name'       => '새', 'email' => 'new@example.com', 'message' => '내용', 'ip_address' => '127.0.0.1',
            'extra_data' => json_encode([['label' => '회사', 'type' => 'text', 'value' => '<script>x</script>']], JSON_UNESCAPED_UNICODE),
        ]);

        $this->withSession($this->adminSession)->get("admin/inquiries/{$legacy}")->assertStatus(200);
        $result = $this->withSession($this->adminSession)->get("admin/inquiries/{$new}");
        $result->assertStatus(200);
        $body = $result->response()->getBody();
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>x</script>', $body);
    }

    public function testCsvExportIncludesExtraColumnAndNeutralizesFormula(): void
    {
        (new InquiryModel())->insert([
            'name'       => '=HYPERLINK("x")', 'email' => 'c@example.com', 'message' => '내용', 'ip_address' => '127.0.0.1',
            'extra_data' => json_encode([['label' => '회사', 'type' => 'text', 'value' => '에이비씨']], JSON_UNESCAPED_UNICODE),
        ]);

        $result = $this->withSession($this->adminSession)->get('admin/inquiries/export');

        $result->assertStatus(200);
        $body = $result->response()->getBody();
        $this->assertStringContainsString('회사', $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);
    }
}
