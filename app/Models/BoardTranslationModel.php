<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Seo\LocaleCache;
use CodeIgniter\Model;
use Config\I18n;

/**
 * 게시판 이름·설명 번역 (기본 언어 제외). 읽기는 BoardModel::getBySlug($slug, $locale) 이 덮어쓴다.
 */
class BoardTranslationModel extends Model
{
    protected $table         = 'board_translations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['board_id', 'locale', 'name', 'description'];

    /**
     * @return array<string, array{name: string, description: string|null}> 언어 => 번역
     */
    public function forBoard(int $boardId): array
    {
        $map = [];

        foreach ($this->where('board_id', $boardId)->findAll() as $row) {
            $map[(string) $row['locale']] = ['name' => (string) $row['name'], 'description' => $row['description']];
        }

        return $map;
    }

    /**
     * 사용 언어(기본 언어 제외)별 번역을 저장한다. 이름이 빈 언어는 번역을 지운다.
     * 사용하지 않는 언어 코드는 무시한다.
     *
     * @param array<string, mixed> $input 언어 => ['name' => ..., 'description' => ...]
     */
    public function saveForBoard(int $boardId, array $input): void
    {
        foreach (config(I18n::class)->prefixedLocales() as $locale) {
            $row  = $input[$locale] ?? null;
            $name = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
            $desc = is_array($row) ? trim((string) ($row['description'] ?? '')) : '';

            $this->where('board_id', $boardId)->where('locale', $locale)->delete();

            if ($name !== '') {
                $this->insert([
                    'board_id'    => $boardId,
                    'locale'      => $locale,
                    'name'        => mb_substr($name, 0, 100),
                    'description' => $desc !== '' ? mb_substr($desc, 0, 255) : null,
                ]);
            }
        }

        LocaleCache::clearLlms();
    }
}
