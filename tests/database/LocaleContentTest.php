<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardModel;
use App\Models\MenuModel;
use App\Models\PageModel;
use App\Models\PostModel;
use Tests\Support\DatabaseTestCase;
use Tests\Support\EnablesLocales;

/**
 * 글·페이지·메뉴의 언어별 조회와 번역 묶음 (#566 2단계).
 *
 * @internal
 */
final class LocaleContentTest extends DatabaseTestCase
{
    use EnablesLocales;

    private int $boardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLocales();
        $this->boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $this->db->table('menus')->where('id >', 0)->delete();
        (new MenuModel())->clearCache();
    }

    protected function tearDown(): void
    {
        $this->restoreLocales();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makePost(string $locale, ?int $group = null, array $overrides = []): int
    {
        $id = (int) (new PostModel())->insert(array_merge([
            'board_id'             => $this->boardId,
            'title'                => 'post-' . $locale,
            'content'              => 'body',
            'locale'               => $locale,
            'translation_group_id' => $group,
        ], $overrides));

        if ($group === null) {
            (new PostModel())->update($id, ['translation_group_id' => $id]);
        }

        return $id;
    }

    public function testPostListsAreFilteredByLocale(): void
    {
        $ko    = $this->makePost('ko');
        $en    = $this->makePost('en');
        $model = new PostModel();

        $koIds = array_map('intval', array_column($model->getList($this->boardId, 1, 50, null, 'ko')['posts'], 'id'));
        $enIds = array_map('intval', array_column($model->getList($this->boardId, 1, 50, null, 'en')['posts'], 'id'));

        $this->assertContains($ko, $koIds);
        $this->assertNotContains($en, $koIds);
        $this->assertSame([$en], $enIds);
        $this->assertSame(1, $model->getTotalCount($this->boardId, null, 'en'));
    }

    public function testLatestAndSearchAreFilteredByLocale(): void
    {
        $this->makePost('ko', null, ['title' => 'shared-word']);
        $en    = $this->makePost('en', null, ['title' => 'shared-word']);
        $model = new PostModel();

        $this->assertSame([$en], array_map('intval', array_column($model->getLatestPublic(10, 'en'), 'id')));
        $result = $model->searchPublic('shared-word', 1, 10, 'en');
        $this->assertSame(1, $result['total']);
        $this->assertSame($en, (int) $result['posts'][0]['id']);
        $this->assertSame(2, $model->searchPublic('shared-word', 1, 10)['total']);
    }

    public function testTagsAreFilteredAndCachedPerLocale(): void
    {
        $this->makePost('ko', null, ['tags' => 'alpha,common']);
        $this->makePost('en', null, ['tags' => 'beta,common']);
        $model = new PostModel();

        $this->assertSame(['alpha' => 1, 'common' => 1], $model->getPublicTagCounts('ko'));
        $this->assertSame(['beta' => 1, 'common' => 1], $model->getPublicTagCounts('en'));
        $this->assertSame(['common' => 2, 'alpha' => 1, 'beta' => 1], $model->getPublicTagCounts());
        $this->assertSame(['beta' => 1], $model->getTagCloud(1, 'en'));
        $this->assertSame(1, $model->searchPublicByTag('beta', 1, 10, 'en')['total']);
        $this->assertSame(0, $model->searchPublicByTag('beta', 1, 10, 'ko')['total']);

        // 글 변경 시 언어별 캐시가 모두 무효화된다
        $this->makePost('en', null, ['tags' => 'gamma']);
        $this->assertArrayHasKey('gamma', $model->getPublicTagCounts('en'));
        $this->assertArrayNotHasKey('gamma', $model->getPublicTagCounts('ko'));
    }

    public function testArchiveIsFilteredAndCachedPerLocale(): void
    {
        $this->makePost('ko', null, ['created_at' => '2026-03-10 10:00:00']);
        $this->makePost('en', null, ['created_at' => '2026-04-10 10:00:00']);
        $model = new PostModel();

        $this->assertSame([['year' => 2026, 'month' => 4, 'count' => 1]], $model->getArchiveCounts('en'));
        $this->assertSame(1, $model->getArchivePosts(2026, 4, 1, 10, 'en')['total']);
        $this->assertSame(0, $model->getArchivePosts(2026, 4, 1, 10, 'ko')['total']);

        $this->makePost('en', null, ['created_at' => '2026-04-11 10:00:00']);
        $this->assertSame(2, $model->getArchiveCounts('en')[0]['count']);
    }

    public function testPopularIsFilteredByLocale(): void
    {
        $this->makePost('ko', null, ['views' => 9]);
        $en    = $this->makePost('en', null, ['views' => 1]);
        $model = new PostModel();

        $this->assertSame([$en], array_map('intval', array_column($model->getPopular('all', 10, 'en'), 'id')));
        $this->assertSame([$en], array_map('intval', array_column($model->getPopularCached('all', 10, 'en'), 'id')));
    }

    public function testRelatedStaysInSameLocale(): void
    {
        $ko1 = $this->makePost('ko', null, ['tags' => 'shared']);
        $this->makePost('en', null, ['tags' => 'shared']);
        $ko2 = $this->makePost('ko', null, ['tags' => 'shared']);

        $this->assertSame([$ko2], array_column((new PostModel())->getRelated($ko1), 'id'));
    }

    public function testPrevNextStayInSameLocale(): void
    {
        $en1 = $this->makePost('en');
        $this->makePost('ko');
        $en2   = $this->makePost('en');
        $model = new PostModel();

        $this->assertSame($en1, (int) $model->getPrevious($this->boardId, $en2, null, 'en')['id']);
        $this->assertNull($model->getNext($this->boardId, $en2, null, 'en'));
    }

    public function testPostTranslationsMapLocaleToId(): void
    {
        $ko = $this->makePost('ko');
        $en = $this->makePost('en', $ko);
        $this->makePost('fr', $ko, ['is_active' => 0]);

        $this->assertSame(['ko' => $ko, 'en' => $en], (new PostModel())->publicTranslationsOf($en));
    }

    public function testPostWithoutGroupHasNoTranslations(): void
    {
        $id = (int) (new PostModel())->insert(['board_id' => $this->boardId, 'title' => 't', 'content' => 'c']);

        $this->assertSame([], (new PostModel())->publicTranslationsOf($id));
    }

    public function testSamePageSlugAllowedInDifferentLocales(): void
    {
        $model = new PageModel();
        $ko    = (int) $model->insert(['slug' => 'locale-page', 'title' => '소개', 'status' => 'published']);
        $model->update($ko, ['translation_group_id' => $ko]);
        $en = (int) $model->insert(['slug' => 'locale-page', 'title' => 'About', 'status' => 'published', 'locale' => 'en', 'translation_group_id' => $ko]);

        $this->assertSame('소개', $model->getBySlug('locale-page', 'ko')['title']);
        $this->assertSame('About', $model->getBySlug('locale-page', 'en')['title']);
        $this->assertSame('소개', $model->getBySlug('locale-page')['title']);
        $this->assertSame(['ko' => $ko, 'en' => $en], $model->publishedTranslationsOf($en));
        $this->assertCount(1, $model->getPublished('en'));
    }

    public function testMenuTreeIsPerLocaleAndCacheClearsAll(): void
    {
        $model = new MenuModel();
        $model->insert(['title' => '소개', 'url' => '/about', 'sort_order' => 1, 'is_active' => 1]);
        $model->insert(['title' => 'About', 'url' => '/en/about', 'sort_order' => 1, 'is_active' => 1, 'locale' => 'en']);

        $this->assertSame('소개', $model->getTree()[0]['title']);
        $this->assertSame('About', $model->getTree('en')[0]['title']);
        $this->assertCount(1, $model->getTreeAll('en'));

        $model->update($model->where('locale', 'en')->first()['id'], ['title' => 'About us']);
        $model->clearCache();

        $this->assertSame('About us', $model->getTree('en')[0]['title']);
    }
}
