<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

/**
 * 위젯 뷰에 넘길 데이터를 준비하는 클래스의 규약.
 *
 * 위젯은 컨트롤러 없이 렌더되므로 데이터 조회는 여기서 한다(모델 호출은 이 클래스까지만).
 * 뷰에서는 모델을 직접 부르지 않는다.
 */
interface WidgetInterface
{
    /**
     * @param array<string, mixed> $context 슬롯을 부른 뷰가 넘긴 값 (예: 글 상세의 `post_id`). 없으면 빈 배열
     *
     * @return array<string, mixed> 위젯 뷰로 넘길 변수
     */
    public function data(array $context = []): array;
}
