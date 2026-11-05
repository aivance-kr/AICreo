<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Exceptions\InvalidManifestException;

/**
 * 매니페스트의 settings[] 항목 — 관리자가 /admin/plugins 에서 입력하는 값 하나.
 *
 * 값은 모두 짧은 문자열(text)이다. 종류를 늘릴 필요가 생기면 그때 type 을 확장한다.
 */
final readonly class PluginSetting
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';
    public const MAX_LENGTH  = 100;

    public function __construct(
        public string $key,
        public string $label,
        public string $default = '',
        public string $help = '',
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw InvalidManifestException::forField('settings[].key', '소문자로 시작하는 [a-z0-9_] 이어야 합니다');
        }

        if ($label === '') {
            throw InvalidManifestException::forField('settings[].label', '비어 있습니다');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            is_string($data['key'] ?? null) ? $data['key'] : '',
            is_string($data['label'] ?? null) ? $data['label'] : '',
            is_string($data['default'] ?? null) ? $data['default'] : '',
            is_string($data['help'] ?? null) ? $data['help'] : '',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'default' => $this->default, 'help' => $this->help];
    }
}
