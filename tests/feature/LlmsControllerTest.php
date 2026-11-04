<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Seo\LocaleCache;
use App\Models\BoardModel;
use App\Models\PageModel;
use Tests\Support\FeatureTestCase;

/**
 * 동적 llms.txt (#204).
 *
 * @internal
 */
final class LlmsControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LocaleCache::clearLlms();
    }

    public function testLlmsReturnsPlainText(): void
    {
        $result = $this->get('llms.txt');

        $result->assertStatus(200);
        $this->assertStringContainsString('text/plain', $result->response()->getHeaderLine('Content-Type'));
    }

    public function testLlmsHasTitleAndSections(): void
    {
        $body = $this->get('llms.txt')->getBody();

        // (한글은 테스트 하네스가 엔티티로 변환 → ASCII 마커로 검증)
        $this->assertStringContainsString('# ', $body);
        $this->assertStringContainsString('http', $body);
    }

    public function testLlmsUsesAbsoluteUrls(): void
    {
        $body = $this->get('llms.txt')->getBody();

        $this->assertStringContainsString(base_url('board/notice'), $body);
        $this->assertStringNotContainsString('](/', $body);
    }

    public function testLlmsIncludesPublishedPage(): void
    {
        (new PageModel())->insert([
            'slug'   => 'llms-visible',
            'title'  => '노출 페이지',
            'status' => 'published',
        ]);
        LocaleCache::clearLlms();

        $body = $this->get('llms.txt')->getBody();

        $this->assertStringContainsString(base_url('llms-visible'), $body);
    }

    public function testLlmsExcludesRestrictedBoard(): void
    {
        (new BoardModel())->insert([
            'slug'            => 'members-only',
            'name'            => '회원전용',
            'read_permission' => 'member',
            'is_active'       => 1,
        ]);
        LocaleCache::clearLlms();

        $body = $this->get('llms.txt')->getBody();

        $this->assertStringNotContainsString('members-only', $body);
    }
}
