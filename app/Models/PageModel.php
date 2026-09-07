<?php

namespace App\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table         = 'pages';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'slug', 'title', 'content', 'layout',
        'meta_title', 'meta_desc', 'og_image', 'sort_order', 'status',
        'wp_post_id', 'created_at', 'updated_at',
    ];
    protected $afterInsert = ['clearSitemapCache'];
    protected $afterUpdate = ['clearSitemapCache'];
    protected $afterDelete = ['clearSitemapCache'];

    /**
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->where('status', 'published')->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPublished(): array
    {
        return $this->where('status', 'published')->orderBy('sort_order')->findAll();
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
