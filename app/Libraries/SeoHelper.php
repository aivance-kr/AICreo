<?php

namespace App\Libraries;

/**
 * 페이지별 메타태그 / OG·Twitter 태그 / canonical / robots 생성.
 *
 * $page 배열로 페이지 단위 값을 주입한다(모두 선택):
 *   - meta_title / title       : <title> 및 og/twitter title
 *   - meta_desc                : description
 *   - og_image                 : OG·Twitter 이미지 (없으면 settings.og_default_image → site_logo 폴백)
 *   - og_type                  : og:type (기본 website)
 *   - canonical                : canonical URL 강제 지정 (없으면 current_url())
 *   - robots                   : robots 지시자 문자열 (예: 'noindex, nofollow')
 *   - noindex                  : true 이면 robots 를 'noindex, nofollow' 로 (robots 미지정 시)
 */
class SeoHelper
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(private array $settings)
    {
    }

    /**
     * @param array<string, mixed>|null $page
     */
    public function render(?array $page = null): string
    {
        $siteName  = $this->settings['site_name'] ?? '';
        $title     = $page['meta_title'] ?? ($page['title'] ?? $siteName);
        $desc      = $page['meta_desc'] ?? ($this->settings['site_desc'] ?? '');
        $ogType    = $page['og_type'] ?? 'website';
        $locale    = $this->settings['locale'] ?? 'ko_KR';
        $canonical = $page['canonical'] ?? current_url();
        $robots    = $this->resolveRobots($page);

        // og_image → settings.og_default_image → site_logo 순 폴백
        $ogImageRaw = $page['og_image']
            ?? ($this->settings['og_default_image'] ?? ($this->settings['site_logo'] ?? ''));
        $ogImage = $ogImageRaw ? base_url($ogImageRaw) : '';

        $html = '<title>' . esc($title) . "</title>\n";
        $html .= '<meta name="description" content="' . esc($desc) . "\">\n";
        $html .= '<link rel="canonical" href="' . esc($canonical) . "\">\n";
        $html .= '<meta name="robots" content="' . esc($robots) . "\">\n";

        // Open Graph
        $html .= '<meta property="og:title" content="' . esc($title) . "\">\n";
        $html .= '<meta property="og:description" content="' . esc($desc) . "\">\n";
        $html .= '<meta property="og:url" content="' . esc($canonical) . "\">\n";
        $html .= '<meta property="og:site_name" content="' . esc($siteName) . "\">\n";
        $html .= '<meta property="og:type" content="' . esc($ogType) . "\">\n";
        $html .= '<meta property="og:locale" content="' . esc($locale) . "\">\n";

        // Twitter Card
        $html .= "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        $html .= '<meta name="twitter:title" content="' . esc($title) . "\">\n";
        $html .= '<meta name="twitter:description" content="' . esc($desc) . "\">\n";

        if ($ogImage) {
            $html .= '<meta property="og:image" content="' . esc($ogImage) . "\">\n";
            $html .= '<meta property="og:image:alt" content="' . esc($title) . "\">\n";

            // 소셜 카드 치수는 알 때만 출력(잘못된 값보다 생략이 안전). page > settings 순.
            $width  = $page['og_image_width'] ?? ($this->settings['og_image_width'] ?? null);
            $height = $page['og_image_height'] ?? ($this->settings['og_image_height'] ?? null);
            if ($width && $height) {
                $html .= '<meta property="og:image:width" content="' . esc((string) $width) . "\">\n";
                $html .= '<meta property="og:image:height" content="' . esc((string) $height) . "\">\n";
            }

            $html .= '<meta name="twitter:image" content="' . esc($ogImage) . "\">\n";
        }

        // 검색엔진 웹마스터 인증 (Naver·Google·Bing·Daum)
        foreach ([
            'naver_verify'  => 'naver-site-verification',
            'google_verify' => 'google-site-verification',
            'bing_verify'   => 'msvalidate.01',
            'daum_verify'   => 'DaumVerification',
        ] as $key => $metaName) {
            if (! empty($this->settings[$key])) {
                $html .= '<meta name="' . $metaName . '" content="' . esc($this->settings[$key]) . "\">\n";
            }
        }

        return $html;
    }

    /**
     * robots 지시자 결정. 명시 값 > noindex 플래그 > 기본 index,follow.
     *
     * @param array<string, mixed>|null $page
     */
    private function resolveRobots(?array $page): string
    {
        if (! empty($page['robots'])) {
            return $page['robots'];
        }

        if (! empty($page['noindex'])) {
            return 'noindex, nofollow';
        }

        return 'index, follow';
    }

    /**
     * Google Analytics 스크립트
     */
    public function gaScript(): string
    {
        $gaId = $this->settings['ga_id'] ?? '';
        if (! $gaId) {
            return '';
        }

        return <<<HTML
            <script async src="https://www.googletagmanager.com/gtag/js?id={$gaId}"></script>
            <script>
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('config', '{$gaId}');
            </script>
            HTML;
    }
}
