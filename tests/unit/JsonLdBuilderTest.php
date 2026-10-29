<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Seo\JsonLdBuilder;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class JsonLdBuilderTest extends CIUnitTestCase
{
    private JsonLdBuilder $ld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ld = new JsonLdBuilder();
    }

    public function testOrganizationMapsSettings(): void
    {
        $node = $this->ld->organization([
            'org_type'     => 'LocalBusiness',
            'site_name'    => '내 회사',
            'site_desc'    => '설명',
            'site_logo'    => 'uploads/logo.png',
            'email'        => 'a@b.com',
            'phone'        => '02-1',
            'address'      => '서울',
            'business_num' => '000-00-00000',
            'instagram'    => 'https://instagram.com/x',
            'youtube'      => '',
        ]);

        $this->assertSame('LocalBusiness', $node['@type']);
        $this->assertSame('내 회사', $node['name']);
        $this->assertSame('a@b.com', $node['email']);
        $this->assertSame('PostalAddress', $node['address']['@type']);
        $this->assertSame('000-00-00000', $node['identifier']);
        // sameAs 는 http 로 시작하는 것만 (youtube 빈값 제외)
        $this->assertSame(['https://instagram.com/x'], $node['sameAs']);
    }

    public function testOrganizationDefaultsType(): void
    {
        $node = $this->ld->organization(['site_name' => 'x']);
        $this->assertSame('Organization', $node['@type']);
        $this->assertArrayNotHasKey('sameAs', $node);
    }

    public function testWebsiteReferencesOrganization(): void
    {
        $node = $this->ld->website(['site_name' => 'x']);
        $this->assertSame('WebSite', $node['@type']);
        $this->assertStringContainsString('#organization', $node['publisher']['@id']);
    }

    public function testWebPageMapsPage(): void
    {
        $node = $this->ld->webPage([
            'meta_title' => '제목',
            'meta_desc'  => '요약',
            'og_image'   => 'uploads/p.png',
            'updated_at' => '2026-06-01 10:00:00',
        ], 'https://x.test/about');

        $this->assertSame('WebPage', $node['@type']);
        $this->assertSame('제목', $node['name']);
        $this->assertSame('https://x.test/about', $node['url']);
        $this->assertStringStartsWith('2026-06-01', $node['dateModified']);
    }

    public function testBlogPostingMapsPost(): void
    {
        $node = $this->ld->blogPosting([
            'title'      => '글제목',
            'content'    => '<p>본문 <b>내용</b></p>',
            'created_at' => '2026-05-01 09:00:00',
        ], 'https://x.test/board/free/9', '홍*동');

        $this->assertSame('BlogPosting', $node['@type']);
        $this->assertSame('글제목', $node['headline']);
        $this->assertSame('본문 내용', $node['articleBody']);
        $this->assertSame('홍*동', $node['author']['name']);
    }

    public function testBlogPostingIncludesSeriesAsIsPartOf(): void
    {
        $node = $this->ld->blogPosting(['title' => '글제목', 'content' => '본문'], 'https://x.test/board/free/9', '홍*동', [
            'title' => '블로그 기능 연재',
            'slug'  => 'blog-features',
        ]);

        $this->assertSame('CreativeWorkSeries', $node['isPartOf']['@type']);
        $this->assertSame('블로그 기능 연재', $node['isPartOf']['name']);
        $this->assertStringEndsWith('/series/blog-features', $node['isPartOf']['url']);
    }

    public function testBlogPostingOmitsIsPartOfWithoutSeries(): void
    {
        $node = $this->ld->blogPosting(['title' => '글제목', 'content' => '본문'], 'https://x.test/board/free/9', '홍*동');

        $this->assertArrayNotHasKey('isPartOf', $node);
    }

    public function testBreadcrumbNumbersPositions(): void
    {
        $node = $this->ld->breadcrumb([
            ['name' => '홈', 'url' => 'https://x.test/'],
            ['name' => '자유', 'url' => 'https://x.test/board/free'],
        ]);

        $this->assertSame('BreadcrumbList', $node['@type']);
        $this->assertSame(1, $node['itemListElement'][0]['position']);
        $this->assertSame(2, $node['itemListElement'][1]['position']);
    }

    public function testRenderEscapesTagsAndUnicode(): void
    {
        $html = $this->ld->render([['@type' => 'Thing', 'name' => '한글</script>']]);

        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        // JSON_HEX_TAG: 값 안의 </script> 가 이스케이프되어 닫는 태그는 딱 1개만 존재
        $this->assertSame(1, substr_count($html, '</script>'));
        // 한글은 \uXXXX(ASCII) 로 인코딩 → 리터럴 한글 없음
        $this->assertStringNotContainsString('한글', $html);
    }

    public function testRenderEmptyReturnsEmptyString(): void
    {
        $this->assertSame('', $this->ld->render([]));
    }
}
