<?php

namespace App\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table          = 'pages';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'slug', 'title', 'content', 'layout',
        'meta_title', 'meta_desc', 'og_image', 'sort_order', 'status',
        'wp_post_id', 'created_at', 'updated_at', 'locale', 'translation_group_id',
    ];
    protected $afterInsert = ['clearSitemapCache'];
    protected $afterUpdate = ['clearSitemapCache'];
    protected $afterDelete = ['clearSitemapCache'];

    /**
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $slug, ?string $locale = null): ?array
    {
        $builder = $this->where('slug', $slug)->where('status', 'published');

        if ($locale !== null) {
            $builder->where('locale', $locale);
        }

        return $builder->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPublished(?string $locale = null): array
    {
        $builder = $this->where('status', 'published');

        if ($locale !== null) {
            $builder->where('locale', $locale);
        }

        return $builder->orderBy('sort_order')->findAll();
    }

    /**
     * 같은 번역 묶음의 발행 페이지. 언어 => 페이지 id. 묶음이 없으면 빈 배열.
     *
     * @return array<string, int>
     */
    public function publishedTranslationsOf(int $pageId): array
    {
        $group = $this->select('translation_group_id')->find($pageId)['translation_group_id'] ?? null;

        if ($group === null) {
            return [];
        }

        $rows = $this->select('id, locale')
            ->where('translation_group_id', $group)
            ->where('status', 'published')
            ->orderBy('id')
            ->findAll();

        return array_column(array_map(static fn (array $r): array => [(string) $r['locale'], (int) $r['id']], $rows), 1, 0);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearSitemapCache(array $data): array
    {
        cache()->delete('seo_sitemap');
        cache()->delete('seo_llms');

        return $data;
    }
}
