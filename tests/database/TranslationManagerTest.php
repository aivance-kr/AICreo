<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\TranslationManager;
use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\Config\Factories;
use Config\I18n;
use Tests\Support\DatabaseTestCase;

/**
 * 번역본 만들기 (#566 4단계).
 *
 * @internal
 */
final class TranslationManagerTest extends DatabaseTestCase
{
    private TranslationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $config          = new I18n();
        $config->enabled = 'ko,en';
        Factories::injectMock('config', 'I18n', $config);
        $this->manager = new TranslationManager();
    }

    private function makePost(): int
    {
        return (int) (new PostModel())->insert([
            'board_id'  => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'     => '원본 제목',
            'content'   => '<p>원본</p>',
            'tags'      => 'a,b',
            'meta_desc' => '요약',
        ]);
    }

    public function testPostTranslationCopiesHiddenAndLinksGroup(): void
    {
        $src = $this->makePost();
        $new = $this->manager->createPostTranslation($src, 'en');

        $this->assertNotNull($new);
        $posts = new PostModel();
        $copy  = $posts->find($new);
        $this->assertSame('en', $copy['locale']);
        $this->assertSame('원본 제목', $copy['title']);
        $this->assertSame('0', (string) $copy['is_active']);
        $this->assertSame($src, (int) $copy['translation_group_id']);
        $this->assertSame($src, (int) $posts->find($src)['translation_group_id']);
        $this->assertSame(['ko' => $src, 'en' => $new], $this->manager->postSiblings($src));
        $this->assertSame(['ko' => $src, 'en' => $new], $this->manager->postSiblings($new));
    }

    public function testPostTranslationRefusedForExistingOrInvalidLocale(): void
    {
        $src = $this->makePost();
        $this->assertNotNull($this->manager->createPostTranslation($src, 'en'));

        $this->assertNull($this->manager->createPostTranslation($src, 'en'));
        $this->assertNull($this->manager->createPostTranslation($src, 'ko'));
        $this->assertNull($this->manager->createPostTranslation($src, 'fr'));
        $this->assertNull($this->manager->createPostTranslation(999999, 'en'));
    }

    public function testUnlinkedPostIsItsOwnSibling(): void
    {
        $src = $this->makePost();

        $this->assertSame(['ko' => $src], $this->manager->postSiblings($src));
        $this->assertSame(['en'], $this->manager->missingLocales($this->manager->postSiblings($src)));
    }

    public function testPageTranslationIsDraftWithSameSlug(): void
    {
        $pages = new PageModel();
        $src   = (int) $pages->insert(['slug' => 'tm-page', 'title' => '소개', 'content' => '내용', 'status' => 'published']);
        $new   = $this->manager->createPageTranslation($src, 'en');

        $this->assertNotNull($new);
        $copy = $pages->find($new);
        $this->assertSame('tm-page', $copy['slug']);
        $this->assertSame('en', $copy['locale']);
        $this->assertSame('draft', $copy['status']);
        $this->assertSame($src, (int) $copy['translation_group_id']);
        $this->assertNull($this->manager->createPageTranslation($src, 'en'));
        $this->assertSame(['ko' => $src, 'en' => $new], $this->manager->pageSiblings($new));
    }
}
