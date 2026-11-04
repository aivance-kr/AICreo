<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\InquiryForm;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class InquiryFormTest extends CIUnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function field(int $id, string $type, string $label = '항목', bool $required = false, ?string $options = null): array
    {
        return ['id' => $id, 'label' => $label, 'field_type' => $type, 'options' => $options, 'is_required' => $required ? 1 : 0];
    }

    public function testParseOptionsTrimsAndDeduplicates(): void
    {
        $this->assertSame(['A', 'B'], InquiryForm::parseOptions(" A \r\n\nB\nA"));
        $this->assertSame([], InquiryForm::parseOptions(null));
    }

    public function testCollectKeepsLabelAndValue(): void
    {
        $result = (new InquiryForm())->collect(
            [$this->field(1, 'text', '회사명'), $this->field(2, 'select', '예산', false, "100\n200"), $this->field(3, 'checkbox', '관심', false, "웹\n앱"), $this->field(4, 'checkbox', '동의')],
            [1 => ' 에이비씨 ', 2 => '200', 3 => ['웹', '앱', '웹'], 4 => '1'],
            [],
            sys_get_temp_dir(),
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame(
            [
                ['label' => '회사명', 'type' => 'text', 'value' => '에이비씨'],
                ['label' => '예산', 'type' => 'select', 'value' => '200'],
                ['label' => '관심', 'type' => 'checkbox', 'value' => '웹, 앱'],
                ['label' => '동의', 'type' => 'checkbox', 'value' => '예'],
            ],
            $result['extra'],
        );
    }

    public function testRequiredFieldMissingIsError(): void
    {
        $result = (new InquiryForm())->collect([$this->field(7, 'text', '회사명', true)], [7 => '  '], [], sys_get_temp_dir());

        $this->assertArrayHasKey('cf_7', $result['errors']);
        $this->assertSame([], $result['extra']);
    }

    public function testRequiredFileMissingIsError(): void
    {
        $result = (new InquiryForm())->collect([$this->field(8, 'file', '첨부', true)], [], [], sys_get_temp_dir());

        $this->assertArrayHasKey('cf_8', $result['errors']);
    }

    public function testSelectValueOutsideOptionsIsRejected(): void
    {
        $result = (new InquiryForm())->collect([$this->field(2, 'select', '예산', false, "100\n200")], [2 => '999'], [], sys_get_temp_dir());

        $this->assertArrayHasKey('cf_2', $result['errors']);
    }

    public function testCheckboxValueOutsideOptionsIsRejected(): void
    {
        $result = (new InquiryForm())->collect([$this->field(3, 'checkbox', '관심', false, "웹\n앱")], [3 => ['웹', '해킹']], [], sys_get_temp_dir());

        $this->assertArrayHasKey('cf_3', $result['errors']);
    }

    public function testTextTooLongIsRejected(): void
    {
        $result = (new InquiryForm())->collect([$this->field(1, 'text')], [1 => str_repeat('가', 256)], [], sys_get_temp_dir());

        $this->assertArrayHasKey('cf_1', $result['errors']);
    }

    public function testArrayGivenForTextIsIgnoredNotCrash(): void
    {
        $result = (new InquiryForm())->collect([$this->field(1, 'text')], [1 => ['x']], [], sys_get_temp_dir());

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['extra']);
    }
}
