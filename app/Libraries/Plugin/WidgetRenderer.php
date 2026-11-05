<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Models\UserPluginModel;
use RuntimeException;
use Throwable;

/**
 * 슬롯에 등록된 위젯을 HTML 로 렌더한다.
 *
 * 위젯 하나가 던진 예외는 그 위젯만 비우고 로그로 남긴다 — 모든 공개 페이지에 들어가는
 * 자리이므로 한 위젯이 페이지 전체를 깨면 안 된다.
 */
final class WidgetRenderer
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    /**
     * @param array<string, mixed> $context 위젯 `data()` 로 전달할 화면별 값
     */
    public function render(string $slot, array $context = []): string
    {
        $html = '';

        foreach ($this->plugins->widgets($slot) as $widget) {
            try {
                $html .= $this->renderOne($widget, $context);
            } catch (Throwable $e) {
                log_message('error', '위젯 렌더 실패 [{key}/{view}]: {msg}', [
                    'key'  => $widget->pluginKey,
                    'view' => $widget->view,
                    'msg'  => $e->getMessage(),
                ]);
            }
        }

        return $html;
    }

    /**
     * 플러그인의 위젯 렌더 캐시를 모두 지운다 (슬롯·뷰·로케일 무관).
     * 설정이 바뀌면 캐시 TTL 을 기다리지 않고 바로 반영하려고 쓴다.
     */
    public static function clearCache(string $pluginKey): void
    {
        try {
            cache()->deleteMatching($pluginKey . '_widget_*');
        } catch (Throwable $e) {
            // 캐시 핸들러가 패턴 삭제를 지원하지 않으면 TTL 만료로 반영된다
            log_message('warning', '[Plugin] 위젯 캐시 삭제 실패: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderOne(WidgetDefinition $widget, array $context): string
    {
        // 권한은 캐시보다 먼저 본다 — 캐시된 HTML 이 비보유자에게 새지 않게
        if ($widget->entitled && ! $this->hasAccess($widget->pluginKey)) {
            return '';
        }

        $cacheKey = $widget->pluginKey . '_widget_' . md5($widget->slot . '|' . $widget->view . '|' . service('request')->getLocale() . '|' . json_encode($context));

        if ($widget->cache > 0) {
            $cached = cache($cacheKey);

            if (is_string($cached)) {
                return $cached;
            }
        }

        $html = trim(view($widget->viewName(), $this->dataFor($widget, $context), ['saveData' => false]));

        if ($widget->cache > 0) {
            cache()->save($cacheKey, $html, $widget->cache);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function dataFor(WidgetDefinition $widget, array $context): array
    {
        if ($widget->class === null) {
            return [];
        }

        $instance = class_exists($widget->class) ? new ($widget->class)() : null;

        if (! $instance instanceof WidgetInterface) {
            throw new RuntimeException("{$widget->class} 는 WidgetInterface 구현 클래스가 아닙니다");
        }

        return $instance->data($context);
    }

    private function hasAccess(string $pluginKey): bool
    {
        $userId = session()->get('user_id');

        if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
            return false;
        }

        $entitlement = (new UserPluginModel())->findEntitlement((int) $userId, $pluginKey);

        return ($entitlement?->accessLevel() ?? AccessLevel::None) !== AccessLevel::None;
    }
}
