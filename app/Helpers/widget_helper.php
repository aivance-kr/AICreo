<?php

declare(strict_types=1);

use App\Libraries\Plugin\WidgetRenderer;

if (! function_exists('widget_slot')) {
    /**
     * 플러그인 위젯 슬롯 — 뷰의 원하는 자리에서 `<?= widget_slot('sidebar') ?>` 로 부른다.
     * 등록된 위젯이 없으면 빈 문자열이다. 출력은 각 위젯 뷰가 이스케이프한 HTML 이다.
     * 위젯이 화면별 값(글 번호 등)을 알아야 하면 `$context` 로 넘기고, 위젯 `data()` 가 받는다.
     *
     * @param array<string, mixed> $context
     */
    function widget_slot(string $slot, array $context = []): string
    {
        return (new WidgetRenderer(service('pluginManager')))->render($slot, $context);
    }
}
