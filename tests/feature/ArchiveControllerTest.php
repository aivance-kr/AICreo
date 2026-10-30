<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class ArchiveControllerTest extends FeatureTestCase
{
    private function makePost(string $title, string $createdAt, array $extra = []): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        (new PostModel())->insert(array_merge([
            'board_id'    => $boardId,
            'title'       => $title,
            'content'     => '본문',
            'author_name' => '관리자',
            'created_at'  => $createdAt,
        ], $extra));
    }

    public function testMonthPageListsOnlyThatMonthsPublicPosts(): void
    {
        $this->makePost('삼월 글', '2026-03-10 10:00:00');
        $this->makePost('사월 글', '2026-04-10 10:00:00');
        $this->makePost('삼월 비밀글', '2026-03-11 10:00:00', ['is_secret' => 1]);

        $body = html_entity_decode($this->get('/archive/2026/03')->getBody(), ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('2026년 3월', $body);
        $this->assertStringContainsString('삼월 글', $body);
        $this->assertStringNotContainsString('사월 글', $body);
        $this->assertStringNotContainsString('삼월 비밀글', $body);
    }

    #[DataProvider('provideInvalidYearOrMonthReturns404')]
    public function testInvalidYearOrMonthReturns404(string $uri): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get($uri);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidYearOrMonthReturns404(): iterable
    {
        yield '월 0' => ['/archive/2026/00'];

        yield '월 13' => ['/archive/2026/13'];

        yield '너무 이른 연도' => ['/archive/1999/05'];

        yield '미래 연도' => ['/archive/9999/01'];
    }

    public function testIndexShowsMonthlyCounts(): void
    {
        $this->makePost('일', '2026-03-10 10:00:00');
        $this->makePost('이', '2026-03-11 10:00:00');
        cache()->delete('archive_counts');

        $body = html_entity_decode($this->get('/archive')->getBody(), ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('/archive/2026/03', $body);
        $this->assertStringContainsString('2편', $body);
    }

    public function testBlogThemeSidebarShowsArchiveWidget(): void
    {
        $this->makePost('일', '2026-03-10 10:00:00');
        cache()->delete('archive_counts');
        $settings = new SettingModel();
        $previous = $settings->getAllAsMap()['active_theme'] ?? 'default';

        try {
            $settings->saveSettings(['active_theme' => 'devnotes']);
            Services::resetSingle('renderer'); // 공유 렌더러가 이전 테스트의 활성 테마를 기억하므로 초기화

            $body = html_entity_decode($this->get('/')->getBody(), ENT_QUOTES, 'UTF-8');
        } finally {
            $settings->saveSettings(['active_theme' => $previous]);
            Services::resetSingle('renderer');
        }

        $this->assertStringContainsString('side-archive', $body);
        $this->assertStringContainsString('/archive/2026/03', $body);
    }
}
