<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\PostModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 태그가 없는 기존 공개 글에 AI(Groq)로 태그를 채운다.
 * 외부 전송이므로 공개글(비밀글 아님 · 활성 · guest 열람 게시판)만 대상이다. 이미 태그가 있는 글은 건드리지 않으므로
 * 중단 후 다시 실행해도 이어서 처리된다. 뉴스레터 큐·updated_at 은 건드리지 않는다.
 */
class GeneratePostTagsCommand extends BaseCommand
{
    private const MAX_CONSECUTIVE_FAILURES = 5;
    private const MAX_RATE_LIMIT_RETRIES   = 3;
    private const DEFAULT_RETRY_WAIT       = 20; // Retry-After 가 없을 때(초)
    private const MAX_RETRY_WAIT           = 120; // 이보다 길면 분·일 단위 한도 소진으로 보고 중단(초)

    protected $group       = 'Blog';
    protected $name        = 'posts:generate-tags';
    protected $description = '태그가 없는 기존 공개 글에 AI 로 태그를 채웁니다.';
    protected $usage       = 'posts:generate-tags [--limit N] [--board slug] [--sleep ms] [--dry-run]';
    protected $options     = [
        '--limit'   => '이번 실행에서 처리할 최대 글 수 (기본 100)',
        '--board'   => '특정 게시판 slug 만 처리',
        '--sleep'   => '글 사이 대기(ms, 기본 500) — Groq 호출 한도 보호',
        '--dry-run' => '저장하지 않고 뽑힌 태그만 출력',
    ];

    public function run(array $params)
    {
        $limit  = max(1, (int) (CLI::getOption('limit') ?? 100));
        $sleep  = max(0, (int) (CLI::getOption('sleep') ?? 500));
        $board  = CLI::getOption('board');
        $dryRun = CLI::getOption('dry-run') !== null;

        $service = service('groqTags');
        if (! $service->isEnabled()) {
            CLI::error('GROQ_API_KEY 가 설정되지 않았습니다.');

            return EXIT_ERROR;
        }

        $posts = new PostModel();
        $db    = db_connect();

        $builder = $db->table('posts')
            ->select('posts.id, posts.title, posts.content')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->groupStart()->where('posts.tags', null)->orWhere('posts.tags', '')->groupEnd()
            ->where('posts.is_secret', 0)
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->orderBy('posts.id', 'DESC')
            ->limit($limit);
        if (is_string($board) && $board !== '') {
            $builder->where('boards.slug', $board);
        }

        $rows = $builder->get()->getResultArray();
        if ($rows === []) {
            CLI::write('태그를 채울 글이 없습니다.', 'green');

            return EXIT_SUCCESS;
        }

        $done = $failed = $streak = 0;

        foreach ($rows as $row) {
            $tags = null;

            for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES; $attempt++) {
                $tags = PostModel::normalizeTags(implode(',', $service->extract((string) $row['title'], (string) $row['content'])));
                $wait = $service->retryAfter();
                if ($tags !== null || $wait === null || $attempt === self::MAX_RATE_LIMIT_RETRIES) {
                    break;
                }
                // 429: 한도가 풀릴 때까지 기다렸다가 같은 글을 다시 시도한다. 너무 오래 걸리면(일일 한도 등) 중단.
                $wait = $wait > 0 ? $wait : self::DEFAULT_RETRY_WAIT;
                if ($wait > self::MAX_RETRY_WAIT) {
                    CLI::error(sprintf('Groq 호출 한도 소진(%d초 후 재시도 가능). 한도가 풀린 뒤 다시 실행하세요.', $wait));

                    break 2;
                }
                CLI::write(sprintf('#%d 호출 한도(429) — %d초 대기 후 재시도 (%d/%d)', $row['id'], $wait, $attempt + 1, self::MAX_RATE_LIMIT_RETRIES), 'yellow');
                sleep($wait);
            }

            if ($tags === null) {
                $failed++;
                $streak++;
                CLI::write(sprintf('#%d 실패: %s', $row['id'], $service->lastError() ?? '알 수 없음'), 'yellow');
                if ($streak >= self::MAX_CONSECUTIVE_FAILURES) {
                    CLI::error('연속 실패가 많아 중단합니다. 네트워크·키·한도를 확인하세요.');

                    break;
                }
            } else {
                $streak = 0;
                if (! $dryRun) {
                    $posts->setTagsSilently((int) $row['id'], $tags);
                }
                $done++;
                CLI::write(sprintf('#%d %s → %s', $row['id'], mb_strimwidth((string) $row['title'], 0, 40, '…'), $tags));
            }

            if ($sleep > 0) {
                usleep($sleep * 1000);
            }
        }

        if (! $dryRun && $done > 0) {
            $posts->clearTaggingCaches();
        }

        CLI::write(sprintf('완료: 태그 %d건%s, 실패 %d건.', $done, $dryRun ? '(dry-run, 저장 안 함)' : ' 저장', $failed), 'green');

        return EXIT_SUCCESS;
    }
}
