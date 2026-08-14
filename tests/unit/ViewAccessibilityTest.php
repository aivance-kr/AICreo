<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * 뷰 접근성 회귀 테스트.
 *
 * KWCAG 2.2 준수는 이 제품의 계약 조건인데(PRODUCT.md 참조),
 * composer ci 의 cs·analyse·test 는 PHP 코드만 볼 뿐 마크업은 보지 않는다.
 * 그래서 육안 검수로는 절대 걸리지 않는 기본기 — 레이블 연결, 아이콘 전용
 * 컨트롤의 이름, 건너뛰기 링크, 원시 슈퍼글로벌 — 를 여기서 강제한다.
 *
 * 새 화면을 추가할 때 이 테스트가 깨지면 마크업을 고쳐야 한다.
 * 예외가 정말 필요하면 해당 상수 목록에 사유와 함께 등록한다.
 *
 * @internal
 */
final class ViewAccessibilityTest extends CIUnitTestCase
{
    /**
     * 감사 대상에서 제외하는 경로 — CI4 가 제공하는 에러 페이지 템플릿
     */
    private const EXCLUDED_DIRS = ['errors'];

    /**
     * 레이블 없이 두어도 되는 컨트롤 타입
     */
    private const UNLABELABLE = ['submit', 'button', 'hidden', 'reset', 'image'];

    /**
     * 감사 대상 뷰 파일 전체.
     *
     * @return list<string>
     */
    private function viewFiles(): array
    {
        $root     = APPPATH . 'Views';
        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(
                [$root . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
                ['', '/'],
                $file->getPathname(),
            );

            foreach (self::EXCLUDED_DIRS as $skip) {
                if (str_starts_with($relative, $skip . '/')) {
                    continue 2;
                }
            }

            $files[] = $relative;
        }

        sort($files);

        return $files;
    }

    /**
     * 뷰 원본 그대로.
     *
     * @return array<string, string> 상대경로 => 내용
     */
    private function viewContents(): array
    {
        $root = APPPATH . 'Views';
        $out  = [];

        foreach ($this->viewFiles() as $relative) {
            $out[$relative] = (string) file_get_contents($root . DIRECTORY_SEPARATOR . $relative);
        }

        return $out;
    }

    /**
     * 마크업 구조 검사용 — PHP 블록을 걷어낸 뷰.
     *
     * `<?= esc($x) ?>` 같은 단축 태그에는 `>` 가 들어 있어서, 걷어내지 않으면
     * HTML 태그 정규식이 속성 도중에 잘려 id·rel 을 놓친다(거짓 양성).
     *
     * @return array<string, string> 상대경로 => PHP 를 제거한 내용
     */
    private function markupContents(): array
    {
        return array_map(
            static fn (string $source): string => (string) preg_replace('/<\?(?:php|=).*?\?>/s', '', $source),
            $this->viewContents(),
        );
    }

    /**
     * 모든 <label> 은 for 속성으로 컨트롤과 연결돼야 한다.
     * (WCAG 1.3.1 / 3.3.2 · KWCAG 「레이블 제공」)
     */
    public function testEveryLabelIsAssociatedWithAControl(): void
    {
        $offenders = [];

        foreach ($this->markupContents() as $relative => $source) {
            preg_match_all('/<label\b[^>]*>/s', $source, $matches);

            foreach ($matches[0] as $tag) {
                if (! str_contains($tag, 'for=')) {
                    $offenders[] = $relative . ' → ' . trim(preg_replace('/\s+/', ' ', $tag) ?? '');
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "for 속성이 없는 <label> 이 있습니다. 컨트롤의 id 와 연결하거나,\n"
            . "폼 컨트롤이 아닌 표시용이면 <span>·<div> 로 바꾸세요:\n- "
            . implode("\n- ", $offenders),
        );
    }

    /**
     * 레이블이 필요한 폼 컨트롤은 id 를 가져야 한다(레이블이 가리킬 대상).
     */
    public function testLabelableControlsHaveIds(): void
    {
        $offenders = [];
        $typePat   = implode('|', self::UNLABELABLE);

        foreach ($this->markupContents() as $relative => $source) {
            preg_match_all('/<(?:input|textarea|select)\b[^>]*?>/s', $source, $matches);

            foreach ($matches[0] as $tag) {
                if (preg_match('/type="(?:' . $typePat . ')"/', $tag) === 1) {
                    continue;
                }
                if (str_contains($tag, 'id=')) {
                    continue;
                }
                $offenders[] = $relative . ' → ' . trim(preg_replace('/\s+/', ' ', $tag) ?? '');
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "id 가 없는 폼 컨트롤이 있습니다. <label for=...> 가 가리킬 수 없습니다:\n- "
            . implode("\n- ", $offenders),
        );
    }

    /**
     * 아이콘만 들어 있는 버튼·링크는 접근 가능한 이름을 가져야 한다.
     * (WCAG 4.1.2 / 2.4.4)
     */
    public function testIconOnlyControlsHaveAccessibleNames(): void
    {
        $offenders = [];
        $pattern   = '/<(button|a)\b[^>]*>\s*(?:<i class="bi[^"]*"[^>]*>\s*<\/i>\s*)+<\/\1>/s';

        foreach ($this->markupContents() as $relative => $source) {
            preg_match_all($pattern, $source, $matches);

            foreach ($matches[0] as $fragment) {
                if (str_contains($fragment, 'aria-label') || str_contains($fragment, 'visually-hidden')) {
                    continue;
                }
                $offenders[] = $relative . ' → ' . trim(preg_replace('/\s+/', ' ', $fragment) ?? '');
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "아이콘만 있는 컨트롤에 이름이 없습니다. aria-label 을 주거나\n"
            . "<span class=\"visually-hidden\"> 으로 텍스트를 넣으세요:\n- "
            . implode("\n- ", $offenders),
        );
    }

    /**
     * 대화 상자는 이름을 가져야 한다.
     * 이름이 없으면 스크린리더가 «대화 상자» 라고만 읽어, 무엇을 묻는 창인지 알 수 없다.
     * (WCAG 4.1.2 · ARIA APG Dialog)
     */
    public function testModalsHaveAccessibleNames(): void
    {
        $offenders = [];

        foreach ($this->markupContents() as $relative => $source) {
            preg_match_all('/<div\b[^>]*class="[^"]*\bmodal\b[^"]*"[^>]*>/s', $source, $matches);

            foreach ($matches[0] as $tag) {
                // modal-dialog·modal-content·modal-body 등 내부 래퍼는 대상이 아니다
                if (preg_match('/class="[^"]*\bmodal-[a-z]/', $tag) === 1) {
                    continue;
                }
                if (str_contains($tag, 'aria-labelledby') || str_contains($tag, 'aria-label=')) {
                    continue;
                }
                $offenders[] = $relative . ' → ' . trim((string) preg_replace('/\s+/', ' ', $tag));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "이름 없는 대화 상자가 있습니다. 제목 요소에 id 를 주고\n"
            . "모달에 aria-labelledby 로 연결하세요:\n- "
            . implode("\n- ", $offenders),
        );
    }

    /**
     * 레이아웃에는 반복 영역 건너뛰기 링크와 본문 랜드마크가 있어야 한다.
     * (WCAG 2.4.1 / 1.3.1 · KWCAG 「반복 영역 건너뛰기」)
     */
    public function testLayoutsProvideSkipLinkAndMainLandmark(): void
    {
        $layouts = [
            'layouts/admin.php',
            'themes/default/layouts/main.php',
        ];

        foreach ($layouts as $layout) {
            $source = (string) file_get_contents(APPPATH . 'Views/' . $layout);

            $this->assertStringContainsString(
                'class="skip-link"',
                $source,
                "{$layout} 에 건너뛰기 링크가 없습니다.",
            );
            $this->assertStringContainsString(
                'id="main-content"',
                $source,
                "{$layout} 에 본문 랜드마크(id=\"main-content\")가 없습니다.",
            );
            $this->assertStringContainsString(
                '<main',
                $source,
                "{$layout} 에 <main> 요소가 없습니다.",
            );
        }
    }

    /**
     * 뷰에서 원시 슈퍼글로벌을 직접 읽지 않는다(보안 규칙).
     */
    public function testViewsDoNotTouchSuperglobals(): void
    {
        $offenders = [];

        foreach ($this->viewContents() as $relative => $source) {
            if (preg_match('/\$_(GET|POST|REQUEST)\b/', $source, $m) === 1) {
                $offenders[] = $relative . ' → $_' . $m[1];
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "뷰에서 슈퍼글로벌을 직접 사용했습니다. service('request') 를 경유하세요:\n- "
            . implode("\n- ", $offenders),
        );
    }

    /**
     * 새 창으로 열리는 링크에는 rel="noopener" 가 있어야 한다(탭내빙 방지).
     */
    public function testBlankTargetLinksAreProtected(): void
    {
        $offenders = [];

        foreach ($this->markupContents() as $relative => $source) {
            preg_match_all('/<a\b[^>]*target="_blank"[^>]*>/s', $source, $matches);

            foreach ($matches[0] as $tag) {
                if (! str_contains($tag, 'rel=')) {
                    $offenders[] = $relative . ' → ' . trim(preg_replace('/\s+/', ' ', $tag) ?? '');
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "target=\"_blank\" 링크에 rel=\"noopener\" 가 없습니다:\n- "
            . implode("\n- ", $offenders),
        );
    }
}
