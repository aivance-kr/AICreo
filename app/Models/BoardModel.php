<?php

namespace App\Models;

use App\Libraries\Seo\LocaleCache;
use App\Libraries\Seo\SitemapCache;
use CodeIgniter\Model;
use Config\I18n;

class BoardModel extends Model
{
    protected $table         = 'boards';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'slug', 'name', 'description',
        'read_permission', 'write_permission',
        'allow_file', 'allow_image',
        'posts_per_page', 'list_skin', 'sort_order', 'is_active',
        'wp_term_id',
    ];
    protected $afterInsert = ['clearSitemapCache'];
    protected $afterUpdate = ['clearSitemapCache'];
    protected $afterDelete = ['clearSitemapCache', 'deleteTranslations'];

    /**
     * $locale 이 기본 언어가 아니면 그 언어의 이름·설명 번역을 덮어쓴다(이름이 빈 번역은 무시).
     *
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $slug, ?string $locale = null): ?array
    {
        $board = $this->where('slug', $slug)->where('is_active', 1)->first();

        return $board === null ? null : $this->localize($board, $locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getActiveBoards(?string $locale = null): array
    {
        $boards = $this->where('is_active', 1)->orderBy('sort_order')->findAll();

        return array_map(fn (array $board): array => $this->localize($board, $locale), $boards);
    }

    /**
     * @param array<string, mixed> $board
     *
     * @return array<string, mixed>
     */
    private function localize(array $board, ?string $locale): array
    {
        if ($locale === null || $locale === config(I18n::class)->defaultLocale) {
            return $board;
        }

        $row = db_connect()->table('board_translations')
            ->select('name, description')
            ->where('board_id', $board['id'])
            ->where('locale', $locale)
            ->get()->getRowArray();

        if ($row === null || (string) $row['name'] === '') {
            return $board;
        }

        $board['name']        = (string) $row['name'];
        $board['description'] = $row['description'];

        return $board;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearSitemapCache(array $data): array
    {
        SitemapCache::clear();
        LocaleCache::clearLlms();
        cache()->delete('public_tag_counts');

        return $data;
    }

    /**
     * 게시판을 지우면 그 번역도 함께 지운다.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function deleteTranslations(array $data): array
    {
        foreach ((array) ($data['id'] ?? []) as $id) {
            $this->db->table('board_translations')->where('board_id', $id)->delete();
        }

        return $data;
    }
}
