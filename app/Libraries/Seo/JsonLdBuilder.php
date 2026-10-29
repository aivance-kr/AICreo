<?php

declare(strict_types=1);

namespace App\Libraries\Seo;

/**
 * schema.org JSON-LD 그래프 빌더 (GEO).
 *
 * - Organization / WebSite 는 전 페이지 공통(사이트 엔티티 뿌리).
 * - WebPage·Article / BlogPosting + BreadcrumbList 는 페이지별.
 * - 출력은 `@graph` 하나로 묶어 `<script type="application/ld+json">` 렌더.
 *
 * 인코딩 주의:
 *  - JSON_HEX_TAG : `<`,`>` 이스케이프 → `</script>` 조기 종료 방지
 *  - JSON_UNESCAPED_UNICODE 미사용 : 한글을 `\uXXXX`(ASCII) 로 → 이식성·테스트 하네스 우회
 */
class JsonLdBuilder
{
    private const ORG_ID  = '#organization';
    private const SITE_ID = '#website';

    /**
     * 사이트 공통 Organization 노드.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function organization(array $settings): array
    {
        $node = [
            '@type' => ($settings['org_type'] ?? '') ?: 'Organization',
            '@id'   => base_url(self::ORG_ID),
            'name'  => $settings['site_name'] ?? '',
            'url'   => base_url('/'),
        ];

        if (! empty($settings['site_desc'])) {
            $node['description'] = $settings['site_desc'];
        }
        if (! empty($settings['site_logo'])) {
            $node['logo'] = base_url($settings['site_logo']);
        }
        if (! empty($settings['email'])) {
            $node['email'] = $settings['email'];
        }
        if (! empty($settings['phone'])) {
            $node['telephone'] = $settings['phone'];
        }
        if (! empty($settings['address'])) {
            $node['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $settings['address']];
        }
        if (! empty($settings['business_num'])) {
            $node['identifier'] = $settings['business_num'];
        }

        $sameAs = array_values(array_filter([
            $settings['instagram'] ?? '',
            $settings['youtube'] ?? '',
            $settings['blog'] ?? '',
            $settings['kakao'] ?? '',
        ], static fn ($v): bool => is_string($v) && str_starts_with($v, 'http')));
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /**
     * 사이트 공통 WebSite 노드.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function website(array $settings): array
    {
        return [
            '@type'     => 'WebSite',
            '@id'       => base_url(self::SITE_ID),
            'name'      => $settings['site_name'] ?? '',
            'url'       => base_url('/'),
            'publisher' => ['@id' => base_url(self::ORG_ID)],
        ];
    }

    /**
     * 동적 페이지(pages) WebPage 노드.
     *
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    public function webPage(array $page, string $url): array
    {
        $node = [
            '@type'     => 'WebPage',
            'name'      => $page['meta_title'] ?? ($page['title'] ?? ''),
            'url'       => $url,
            'isPartOf'  => ['@id' => base_url(self::SITE_ID)],
            'publisher' => ['@id' => base_url(self::ORG_ID)],
        ];

        if (! empty($page['meta_desc'])) {
            $node['description'] = $page['meta_desc'];
        }
        if (! empty($page['og_image'])) {
            $node['image'] = base_url($page['og_image']);
        }
        if (! empty($page['updated_at'])) {
            $node['dateModified'] = date('c', strtotime((string) $page['updated_at']));
        }

        return $node;
    }

    /**
     * 게시글(posts) BlogPosting 노드.
     *
     * @param array<string, mixed>      $post
     * @param array<string, mixed>|null $series 속한 시리즈(title·slug) — 있으면 isPartOf 로 연재임을 알린다
     *
     * @return array<string, mixed>
     */
    public function blogPosting(array $post, string $url, string $authorName, ?array $series = null): array
    {
        $node = [
            '@type'     => 'BlogPosting',
            'headline'  => $post['title'] ?? '',
            'url'       => $url,
            'author'    => ['@type' => 'Person', 'name' => $authorName],
            'publisher' => ['@id' => base_url(self::ORG_ID)],
        ];

        if ($series !== null && ! empty($series['slug'])) {
            $node['isPartOf'] = [
                '@type' => 'CreativeWorkSeries',
                'name'  => $series['title'] ?? '',
                'url'   => base_url('series/' . $series['slug']),
            ];
        }

        $body = trim(strip_tags((string) ($post['content'] ?? '')));
        if ($body !== '') {
            $node['articleBody'] = $body;
        }
        if (! empty($post['created_at'])) {
            $node['datePublished'] = date('c', strtotime((string) $post['created_at']));
        }
        if (! empty($post['updated_at'])) {
            $node['dateModified'] = date('c', strtotime((string) $post['updated_at']));
        }

        return $node;
    }

    /**
     * BreadcrumbList — [['name'=>..., 'url'=>...], ...] 순서대로.
     *
     * @param list<array{name:string,url:string}> $items
     *
     * @return array<string, mixed>
     */
    public function breadcrumb(array $items): array
    {
        $elements = [];

        foreach ($items as $i => $item) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $elements];
    }

    /**
     * @graph 배열을 <script type="application/ld+json"> 로 렌더.
     *
     * @param list<array<string, mixed>> $graphs
     */
    public function render(array $graphs): string
    {
        if ($graphs === []) {
            return '';
        }

        $json = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graphs],
            JSON_HEX_TAG | JSON_THROW_ON_ERROR,
        );

        return '<script type="application/ld+json">' . $json . "</script>\n";
    }
}
