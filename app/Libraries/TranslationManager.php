<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\PageModel;
use App\Models\PostModel;
use Config\I18n;

/**
 * 글·페이지의 번역본 만들기. 번역본도 독립된 행이고 `translation_group_id` 로 묶인다(묶음 값은 원본 행 id).
 * 새 번역본은 원본 내용을 복사해 숨김(글 is_active=0)·초안(페이지 draft) 상태로 시작해, 번역을 마친 뒤 공개한다.
 */
final class TranslationManager
{
    public function __construct(
        private readonly PostModel $posts = new PostModel(),
        private readonly PageModel $pages = new PageModel(),
    ) {
    }

    /**
     * 같은 묶음의 글(숨김 포함, 본인 포함). 언어 => 글 id.
     *
     * @return array<string, int>
     */
    public function postSiblings(int $postId): array
    {
        $group = $this->posts->find($postId)['translation_group_id'] ?? null;

        if ($group === null) {
            $locale = $this->posts->find($postId)['locale'] ?? null;

            return $locale === null ? [] : [(string) $locale => $postId];
        }

        return array_map('intval', array_column($this->posts->select('id, locale')->where('translation_group_id', $group)->orderBy('id')->findAll(), 'id', 'locale'));
    }

    /**
     * 같은 묶음의 페이지(초안 포함, 본인 포함). 언어 => 페이지 id.
     *
     * @return array<string, int>
     */
    public function pageSiblings(int $pageId): array
    {
        $group = $this->pages->find($pageId)['translation_group_id'] ?? null;

        if ($group === null) {
            $locale = $this->pages->find($pageId)['locale'] ?? null;

            return $locale === null ? [] : [(string) $locale => $pageId];
        }

        return array_map('intval', array_column($this->pages->select('id, locale')->where('translation_group_id', $group)->orderBy('id')->findAll(), 'id', 'locale'));
    }

    /**
     * 관리자 화면 "번역" 상자용 행 — 사용 언어마다 현재 여부·수정 주소(있으면)·번역본 만들기 주소.
     *
     * @return list<array{locale:string,current:bool,edit:string|null,translate:string}>
     */
    public function pageRows(int $pageId): array
    {
        $siblings = $this->pageSiblings($pageId);
        $rows     = [];

        foreach (config(I18n::class)->locales() as $locale) {
            $rows[] = [
                'locale'    => $locale,
                'current'   => ($siblings[$locale] ?? null) === $pageId,
                'edit'      => isset($siblings[$locale]) ? '/admin/pages/' . $siblings[$locale] . '/edit' : null,
                'translate' => '/admin/pages/' . $pageId . '/translate/' . $locale,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{locale:string,current:bool,edit:string|null,translate:string}>
     */
    public function postRows(int $postId): array
    {
        $siblings = $this->postSiblings($postId);
        $slugs    = $siblings === [] ? [] : array_column(
            $this->posts->select('posts.id, boards.slug AS board_slug')->join('boards', 'boards.id = posts.board_id', 'inner')->whereIn('posts.id', array_values($siblings))->findAll(),
            'board_slug',
            'id',
        );
        $rows = [];

        foreach (config(I18n::class)->locales() as $locale) {
            $id     = $siblings[$locale] ?? null;
            $rows[] = [
                'locale'    => $locale,
                'current'   => $id === $postId,
                'edit'      => $id !== null && isset($slugs[$id]) ? '/board/' . $slugs[$id] . '/' . $id . '/edit' : null,
                'translate' => '/admin/posts/' . $postId . '/translate/' . $locale,
            ];
        }

        return $rows;
    }

    /**
     * 번역본을 새로 만들 수 있는 언어 — 사용 언어 중 묶음에 아직 없는 것.
     *
     * @param array<string, int> $siblings
     *
     * @return list<string>
     */
    public function missingLocales(array $siblings): array
    {
        return array_values(array_filter(config(I18n::class)->locales(), static fn (string $l): bool => ! isset($siblings[$l])));
    }

    /**
     * @return int|null 새 글 id. 원본이 없거나 언어가 맞지 않거나 이미 그 언어 번역이 있으면 null
     */
    public function createPostTranslation(int $sourceId, string $locale): ?int
    {
        $source = $this->posts->find($sourceId);

        if ($source === null || ! in_array($locale, $this->missingLocales($this->postSiblings($sourceId)), true)) {
            return null;
        }

        $group = (int) ($source['translation_group_id'] ?? $sourceId);
        $db    = db_connect();
        $db->transStart();

        if ($source['translation_group_id'] === null) {
            $this->posts->update($sourceId, ['translation_group_id' => $group]);
        }

        $newId = (int) $this->posts->insert([
            'board_id'             => $source['board_id'],
            'category_id'          => $source['category_id'],
            'user_id'              => $source['user_id'],
            'title'                => $source['title'],
            'content'              => $source['content'],
            'signature'            => $source['signature'],
            'tags'                 => $source['tags'],
            'meta_desc'            => $source['meta_desc'],
            'is_notice'            => 0,
            'is_secret'            => 0,
            'is_active'            => 0,
            'locale'               => $locale,
            'translation_group_id' => $group,
        ]);
        $db->transComplete();

        return $db->transStatus() ? $newId : null;
    }

    /**
     * @return int|null 새 페이지 id. 조건이 맞지 않으면 null
     */
    public function createPageTranslation(int $sourceId, string $locale): ?int
    {
        $source = $this->pages->find($sourceId);

        if ($source === null || ! in_array($locale, $this->missingLocales($this->pageSiblings($sourceId)), true)) {
            return null;
        }

        $group = (int) ($source['translation_group_id'] ?? $sourceId);
        $db    = db_connect();
        $db->transStart();

        if ($source['translation_group_id'] === null) {
            $this->pages->update($sourceId, ['translation_group_id' => $group]);
        }

        $newId = (int) $this->pages->insert([
            'slug'                 => $source['slug'],
            'title'                => $source['title'],
            'content'              => $source['content'],
            'layout'               => $source['layout'],
            'meta_title'           => $source['meta_title'],
            'meta_desc'            => $source['meta_desc'],
            'sort_order'           => $source['sort_order'],
            'status'               => 'draft',
            'locale'               => $locale,
            'translation_group_id' => $group,
        ]);
        $db->transComplete();

        return $db->transStatus() ? $newId : null;
    }
}
