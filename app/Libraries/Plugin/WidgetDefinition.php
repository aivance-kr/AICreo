<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Exceptions\InvalidManifestException;

/**
 * 매니페스트의 widgets[] 항목 — 슬롯 하나에 끼어드는 위젯 하나.
 *
 * 슬롯은 자유 문자열이다. 코어 뷰가 `widget_slot('이름')` 을 부르는 자리가 곧 슬롯이다.
 */
final readonly class WidgetDefinition
{
    public const SLOT_PATTERN      = '/^[a-z][a-z0-9_]*$/';
    public const VIEW_PATTERN      = '#^Views(/[A-Za-z0-9_]+)+$#';
    public const DEFAULT_CACHE_TTL = 300;

    /**
     * @param string      $view  플러그인 디렉터리 기준 상대 경로 (예: Views/widgets/recent)
     * @param string|null $class WidgetInterface 구현 클래스(FQCN). 없으면 뷰만 렌더한다
     * @param int         $cache 렌더 결과 캐시 초 단위. 0 이면 캐시하지 않는다
     */
    public function __construct(
        public string $pluginKey,
        public string $directoryName,
        public string $slot,
        public string $view,
        public int $order = 0,
        public ?string $class = null,
        public int $cache = self::DEFAULT_CACHE_TTL,
        public bool $entitled = false,
    ) {
        if (preg_match(self::SLOT_PATTERN, $slot) !== 1) {
            throw InvalidManifestException::forField('widgets[].slot', '소문자로 시작하는 [a-z0-9_] 여야 합니다');
        }

        if (preg_match(self::VIEW_PATTERN, $view) !== 1) {
            throw InvalidManifestException::forField('widgets[].view', 'Views/ 로 시작하는 상대 경로여야 합니다 (예: Views/widgets/recent)');
        }

        if ($class !== null && ! str_starts_with($class, 'Plugins\\' . $directoryName . '\\')) {
            throw InvalidManifestException::forField('widgets[].class', "Plugins\\{$directoryName}\\ 네임스페이스의 클래스여야 합니다");
        }

        if ($cache < 0) {
            throw InvalidManifestException::forField('widgets[].cache', '0 이상의 초 단위여야 합니다');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $pluginKey, string $directoryName): self
    {
        $class = $data['class'] ?? null;
        $cache = $data['cache'] ?? self::DEFAULT_CACHE_TTL;
        $order = $data['order'] ?? 0;

        if (! is_int($cache) || ! is_int($order)) {
            throw InvalidManifestException::forField('widgets[]', 'order, cache 는 정수여야 합니다');
        }

        if ($class !== null && ! is_string($class)) {
            throw InvalidManifestException::forField('widgets[].class', '문자열이어야 합니다');
        }

        return new self(
            $pluginKey,
            $directoryName,
            is_string($data['slot'] ?? null) ? $data['slot'] : '',
            is_string($data['view'] ?? null) ? $data['view'] : '',
            $order,
            $class,
            $cache,
            ($data['entitled'] ?? false) === true,
        );
    }

    /**
     * view() 에 넘길 네임스페이스 뷰 이름.
     */
    public function viewName(): string
    {
        return '\\Plugins\\' . $this->directoryName . '\\' . str_replace('/', '\\', $this->view);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slot'     => $this->slot,
            'view'     => $this->view,
            'order'    => $this->order,
            'class'    => $this->class,
            'cache'    => $this->cache,
            'entitled' => $this->entitled,
        ];
    }
}
