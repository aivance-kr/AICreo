<?php

declare(strict_types=1);

namespace Plugins\Bookmark\Widgets;

use App\Libraries\Plugin\WidgetInterface;
use Plugins\Bookmark\Models\BookmarkItemModel;

/**
 * 글 상세의 북마크 버튼. 로그인한 회원에게만 보인다.
 */
final class PostBookmarkWidget implements WidgetInterface
{
    public function data(array $context = []): array
    {
        $postId = $context['post_id'] ?? null;

        if (! is_int($postId) || ! session()->get('user_id')) {
            return ['show' => false, 'postId' => 0, 'bookmarked' => false];
        }

        return [
            'show'       => true,
            'postId'     => $postId,
            'bookmarked' => model(BookmarkItemModel::class)->isBookmarked($postId),
        ];
    }
}
