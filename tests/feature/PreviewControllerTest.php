<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filters\VisitLogFilter;
use App\Models\BoardModel;
use App\Models\PageVisitModel;
use App\Models\PostModel;
use App\Models\PostPreviewTokenModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\FeatureTestCase;

/**
 * 비공개·예약 글 미리보기 공유 링크 (#564).
 *
 * @internal
 */
final class PreviewControllerTest extends FeatureTestCase
{
    private int $postId;
    private PostPreviewTokenModel $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = new PostPreviewTokenModel();
        $this->postId = (int) (new PostModel())->insert([
            'board_id'  => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'     => '아직 비공개인 글', 'content' => '<p>미리보기 본문</p>', 'author_name' => '관리자',
            'is_active' => 0, 'publish_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
    }

    public function testValidTokenShowsPrivatePostReadOnlyAndNoindex(): void
    {
        $result = $this->get('preview/' . $this->tokens->issue($this->postId, 7));

        $result->assertOK();
        $result->assertSee('아직 비공개인 글');
        $result->assertSee('미리보기 본문');
        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $result->getBody());
        $this->assertStringContainsString('noindex', $result->response()->getHeaderLine('X-Robots-Tag'));
        $this->assertStringContainsString('no-referrer', $result->response()->getHeaderLine('Referrer-Policy'));
        $this->assertStringNotContainsString('id="post-like"', $result->getBody());
        $this->assertStringNotContainsString('id="comments"', $result->getBody());
    }

    public function testPreviewDoesNotCountView(): void
    {
        $this->get('preview/' . $this->tokens->issue($this->postId, 7));

        $this->assertSame(0, (int) (new PostModel())->find($this->postId)['views']);
    }

    public function testUnknownTokenIs404(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->get('preview/' . str_repeat('a', 64));
    }

    public function testExpiredTokenIs404(): void
    {
        $token = $this->tokens->issue($this->postId, 7);
        $this->tokens->where('token', $token)->set('expires_at', date('Y-m-d H:i:s', time() - 60))->update();

        $this->expectException(PageNotFoundException::class);
        $this->get('preview/' . $token);
    }

    public function testRevokedTokenIs404(): void
    {
        $token = $this->tokens->issue($this->postId, 7);
        $this->tokens->revoke($this->postId, (int) $this->tokens->listByPost($this->postId)[0]['id']);

        $this->expectException(PageNotFoundException::class);
        $this->get('preview/' . $token);
    }

    public function testPrivatePostStaysOutOfPublicListAndSitemap(): void
    {
        $this->tokens->issue($this->postId, 7);

        $this->assertStringNotContainsString('아직 비공개인 글', $this->get('board/notice')->getBody());
        $this->assertStringNotContainsString("/board/notice/{$this->postId}", $this->get('sitemap.xml')->getBody());
        $this->assertStringNotContainsString('아직 비공개인 글', $this->get('feed')->getBody());
    }

    public function testPreviewPathIsExcludedFromVisitLog(): void
    {
        $this->assertContains('preview/*', VisitLogFilter::EXCLUDED_PATHS);
        $this->get('preview/' . $this->tokens->issue($this->postId, 7));

        $this->assertSame(0, (new PageVisitModel())->like('uri', '/preview', 'after')->countAllResults());
    }
}
