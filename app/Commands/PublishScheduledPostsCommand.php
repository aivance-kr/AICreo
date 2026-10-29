<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\PostModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 예약 시각이 지난 글을 공개 처리하고 RSS·sitemap·관련 글·시리즈 캐시를 무효화한다.
 * 1분에 한 번 크론으로 실행한다.
 */
class PublishScheduledPostsCommand extends BaseCommand
{
    protected $group       = 'Blog';
    protected $name        = 'posts:publish-scheduled';
    protected $description = '예약 시각이 지난 글을 공개 처리합니다.';
    protected $usage       = 'posts:publish-scheduled';

    public function run(array $params)
    {
        $published = (new PostModel())->publishDue();

        CLI::write(sprintf('완료: 예약 글 %d건 공개.', $published), 'green');
    }
}
