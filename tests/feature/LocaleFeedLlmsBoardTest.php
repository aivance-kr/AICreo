<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Seo\LocaleCache;
use App\Models\BoardModel;
use App\Models\BoardTranslationModel;
use App\Models\PageModel;
use App\Models\PostModel;
use Tests\Support\EnablesLocales;
use Tests\Support\FeatureTestCase;

/**
 * 언어별 RSS 피드·llms.txt·게시판 이름 번역 (#584).
 *
 * @internal
 */
final class LocaleFeedLlmsBoardTest extends FeatureTestCase
{
    use EnablesLocales;

    private int $boardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLocales();

        $this->boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        (new BoardTranslationModel())->saveForBoard($this->boardId, ['en' => ['name' => 'Announcements', 'description' => 'Site news']]);

        $posts = new PostModel();
        $posts->insert(['board_id' => $this->boardId, 'title' => '한글 소식', 'content' => '본문', 'locale' => 'ko']);
        $posts->insert(['board_id' => $this->boardId, 'title' => 'English news', 'content' => 'body', 'locale' => 'en']);

        (new PageModel())->insert(['slug' => 'about-en', 'title' => 'About us', 'content' => 'x', 'locale' => 'en', 'is_active' => 1]);

        LocaleCache::clearFeed();
        LocaleCache::clearLlms();
    }

    protected function tearDown(): void
    {
        $this->restoreLocales();

        parent::tearDown();
    }

    public function testDefaultFeedHasOnlyDefaultLanguagePosts(): void
    {
        $body = (string) $this->get('feed')->response()->getBody();

        $this->assertStringContainsString('한글 소식', $body);
        $this->assertStringNotContainsString('English news', $body);
        $this->assertStringContainsString('<language>ko</language>', $body);
    }

    public function testEnglishFeedHasOnlyEnglishPostsWithPrefixedLinks(): void
    {
        $result = $this->get('en/feed');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('English news', $body);
        $this->assertStringNotContainsString('한글 소식', $body);
        $this->assertStringContainsString('<language>en</language>', $body);
        $this->assertStringContainsString(base_url('en/board/notice/'), $body);
    }

    public function testFeedsAreCachedPerLocale(): void
    {
        $this->get('feed');
        $this->get('en/feed');

        $this->assertNotNull(cache()->get(LocaleCache::feedKey('ko')));
        $this->assertNotNull(cache()->get(LocaleCache::feedKey('en')));
        $this->assertNotSame(cache()->get(LocaleCache::feedKey('ko')), cache()->get(LocaleCache::feedKey('en')));
    }

    public function testNewPostClearsEveryLocaleFeedCache(): void
    {
        $this->get('en/feed');
        (new PostModel())->insert(['board_id' => $this->boardId, 'title' => 'Fresh', 'content' => 'b', 'locale' => 'en']);

        $this->assertNull(cache()->get(LocaleCache::feedKey('en')));
    }

    public function testEnglishLlmsHasEnglishPagesAndTranslatedBoardName(): void
    {
        $result = $this->get('en/llms.txt');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('[About us](' . base_url('en/about-en') . ')', $body);
        $this->assertStringContainsString('[Announcements](' . base_url('en/board/notice') . '): Site news', $body);
        $this->assertStringNotContainsString('자주 묻는 질문', $body);
    }

    public function testDefaultLlmsKeepsDefaultBoardName(): void
    {
        $body = (string) $this->get('llms.txt')->response()->getBody();

        $this->assertStringNotContainsString('Announcements', $body);
        $this->assertStringNotContainsString('About us', $body);
        $this->assertStringContainsString(base_url('board/notice'), $body);
    }

    public function testBoardPageShowsTranslatedNameInRequestLanguage(): void
    {
        $this->assertStringContainsString('Announcements', $this->get('en/board/notice')->getBody());
        $this->assertStringNotContainsString('Announcements', $this->get('board/notice')->getBody());
    }

    public function testBoardNameWithoutTranslationFallsBackToDefault(): void
    {
        $fallback = (new BoardModel())->getBySlug('notice', 'ja');

        $this->assertSame((new BoardModel())->getBySlug('notice')['name'], $fallback['name']);
    }

    public function testPostListUsesBoardNameOfPostLanguage(): void
    {
        $posts   = (new PostModel())->getLatestPublic(10);
        $byTitle = array_column($posts, 'board_name', 'title');

        $this->assertSame('Announcements', $byTitle['English news']);
        $this->assertNotSame('Announcements', $byTitle['한글 소식']);
    }

    public function testBlankNameDeletesTranslation(): void
    {
        $translations = new BoardTranslationModel();
        $translations->saveForBoard($this->boardId, ['en' => ['name' => '  ', 'description' => 'x']]);

        $this->assertSame([], $translations->forBoard($this->boardId));
    }

    public function testUnusedLocaleIsIgnored(): void
    {
        $translations = new BoardTranslationModel();
        $translations->saveForBoard($this->boardId, ['en' => ['name' => 'A'], 'fr' => ['name' => 'B']]);

        $this->assertSame(['en'], array_keys($translations->forBoard($this->boardId)));
    }

    public function testDeletingBoardRemovesItsTranslations(): void
    {
        $boards = new BoardModel();
        $id     = (int) $boards->insert(['slug' => 'tmp', 'name' => '임시', 'read_permission' => 'guest', 'is_active' => 1]);
        (new BoardTranslationModel())->saveForBoard($id, ['en' => ['name' => 'Temp']]);

        $boards->delete($id);

        $this->assertSame([], (new BoardTranslationModel())->forBoard($id));
    }
}
