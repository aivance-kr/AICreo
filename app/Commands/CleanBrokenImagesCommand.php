<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Model;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * 워드프레스 이관 당시 실제 이미지 파일은 옮겨지지 않아 깨진 채로 남은
 * `wp-content/uploads/...` <img> 태그를 posts.content / pages.content 에서 제거한다.
 * 이미지만 감싸던 <a>, 그 결과 텅 빈 <p> 도 함께 정리한다.
 */
class CleanBrokenImagesCommand extends BaseCommand
{
    protected $group       = 'Wordpress';
    protected $name        = 'content:clean-broken-images';
    protected $description = 'wp-content/uploads 를 가리키는 깨진 <img> 태그를 글/페이지 본문에서 제거합니다.';
    protected $usage       = 'content:clean-broken-images [--apply]';
    protected $options     = [
        '--apply' => '실제로 DB에 반영 (기본은 미리보기만 하는 dry-run)',
    ];

    public function run(array $params)
    {
        $apply = (bool) CLI::getOption('apply');

        $totalRows   = 0;
        $totalImages = 0;

        $totalRows += $this->cleanModel(new PostModel(), '글', $apply, $totalImages);
        $totalRows += $this->cleanModel(new PageModel(), '페이지', $apply, $totalImages);

        if (! $apply) {
            CLI::newLine();
            CLI::write("dry-run: {$totalRows}건 / 이미지 {$totalImages}개 제거 예정. 반영하려면 --apply 를 붙여 실행하세요.", 'yellow');

            return;
        }

        CLI::newLine();
        CLI::write("완료: {$totalRows}건 / 이미지 {$totalImages}개 제거함.", 'green');
    }

    private function cleanModel(Model $model, string $label, bool $apply, int &$totalImages): int
    {
        $rows        = $model->like('content', 'wp-content/uploads')->findAll();
        $changedRows = 0;

        foreach ($rows as $row) {
            [$cleaned, $removed] = $this->stripBrokenImages((string) $row['content']);

            if ($removed === 0) {
                continue;
            }

            $changedRows++;
            $totalImages += $removed;

            $title = mb_substr((string) ($row['title'] ?? ''), 0, 40);
            CLI::write("[{$label}] #{$row['id']} {$title} — 이미지 {$removed}개 제거");

            if ($apply) {
                $model->update($row['id'], ['content' => $cleaned]);
            }
        }

        return $changedRows;
    }

    /**
     * @return array{0: string, 1: int} [정리된 HTML, 제거한 img 개수]
     */
    private function stripBrokenImages(string $html): array
    {
        if (! str_contains($html, 'wp-content/uploads')) {
            return [$html, 0];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $xpath  = new DOMXPath($dom);
        $images = iterator_to_array($xpath->query('//img[contains(@src, "wp-content/uploads")]'));

        if ($images === []) {
            return [$html, 0];
        }

        foreach ($images as $img) {
            $parent = $img->parentNode;
            $parent?->removeChild($img);

            // 그 이미지 하나만 감싸고 있던 <a> 는 텅 비었으니 같이 제거
            if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'a' && trim($parent->textContent) === '') {
                $grandParent = $parent->parentNode;
                $grandParent?->removeChild($parent);
                $parent = $grandParent;
            }

            // 그 결과 텅 빈 <p> 도 함께 제거
            if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'p' && trim($parent->textContent) === '' && ! $this->hasElementChild($parent)) {
                $parent->parentNode?->removeChild($parent);
            }
        }

        $root  = $xpath->query('//*[@id="__root__"]')->item(0);
        $inner = '';

        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return [$inner, count($images)];
    }

    private function hasElementChild(DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }
}
