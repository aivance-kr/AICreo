<?php

declare(strict_types=1);

namespace Plugins\Sample\Widgets;

use App\Libraries\Plugin\WidgetInterface;
use Plugins\Sample\Models\SampleNoteModel;

/**
 * 사이드바 "최근 메모" 위젯의 데이터 준비.
 */
final class RecentNotesWidget implements WidgetInterface
{
    public function data(): array
    {
        return ['notes' => array_slice(model(SampleNoteModel::class)->latest(), 0, 5)];
    }
}
