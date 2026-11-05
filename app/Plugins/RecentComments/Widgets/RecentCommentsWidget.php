<?php

declare(strict_types=1);

namespace Plugins\RecentComments\Widgets;

use App\Libraries\Plugin\WidgetInterface;
use App\Models\PluginModel;
use App\Models\PostCommentModel;

/**
 * 사이드바 최신 댓글 위젯의 데이터 준비. 표시 개수는 플러그인 설정(limit)에서 읽는다.
 */
final class RecentCommentsWidget implements WidgetInterface
{
    public const DEFAULT_LIMIT = 5;
    public const MAX_LIMIT     = 10;
    private const EXCERPT      = 40;

    public function data(): array
    {
        $limit    = self::parseLimit(model(PluginModel::class)->settingsFor('recent_comments')['limit'] ?? '');
        $comments = model(PostCommentModel::class)->getRecentPublic($limit, service('request')->getLocale());

        $items = [];

        foreach ($comments as $comment) {
            $items[] = [
                'author'  => (string) ($comment['user_nickname'] ?? '') !== '' ? (string) $comment['user_nickname'] : (string) ($comment['author_name'] ?? ''),
                'excerpt' => post_excerpt((string) $comment['content'], self::EXCERPT),
                'title'   => (string) $comment['post_title'],
                'url'     => '/board/' . rawurlencode((string) $comment['board_slug']) . '/' . (int) $comment['post_id'],
            ];
        }

        return ['items' => $items];
    }

    /**
     * 설정 값을 1~MAX_LIMIT 정수로 보정한다. 숫자가 아니면 기본값.
     */
    public static function parseLimit(mixed $raw): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT);

        return $value === false ? self::DEFAULT_LIMIT : max(1, min(self::MAX_LIMIT, $value));
    }
}
