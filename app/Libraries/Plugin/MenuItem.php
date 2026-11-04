<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Exceptions\InvalidManifestException;

/**
 * 매니페스트의 menu[] 항목 — 워크스페이스 GNB 에 노출될 링크 하나.
 */
final readonly class MenuItem
{
    public function __construct(
        public string $title,
        public string $route,
        public string $icon = '',
    ) {
        if ($title === '') {
            throw InvalidManifestException::forField('menu[].title', '비어 있습니다');
        }

        if ($route === '' || str_starts_with($route, '/')) {
            throw InvalidManifestException::forField('menu[].route', '상대 경로여야 합니다 (선행 / 금지)');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            is_string($data['title'] ?? null) ? $data['title'] : '',
            is_string($data['route'] ?? null) ? $data['route'] : '',
            is_string($data['icon'] ?? null) ? $data['icon'] : '',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'route' => $this->route, 'icon' => $this->icon];
    }
}
