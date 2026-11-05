<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Models\BoardModel;
use App\Models\PostCommentModel;
use App\Models\PostModel;
use Composer\Autoload\ClassLoader;
use Plugins\RecentComments\Widgets\RecentCommentsWidget;
use Tests\Support\FeatureTestCase;

/**
 * 출고 최신 댓글 위젯(app/Plugins/RecentComments): 데이터 준비 + 뷰 이스케이프.
 *
 * @internal
 */
final class RecentCommentsPluginTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 앞선 테스트의 Services::reset() 이 CI 오토로더를 갈아끼우므로 Composer 로더에 직접 등록한다
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                $loader[0]->addPsr4('Plugins\\RecentComments\\', APPPATH . 'Plugins/RecentComments/');

                break;
            }
        }
        service('autoloader')->addNamespace('Plugins\\RecentComments', APPPATH . 'Plugins/RecentComments');
    }

    private function comment(string $content, string $author = '손님'): void
    {
        $postId = (int) (new PostModel())->insert([
            'board_id'    => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'       => '<b>글 제목</b>',
            'content'     => '본문',
            'author_name' => '글쓴이',
        ]);
        (new PostCommentModel())->insert(['post_id' => $postId, 'content' => $content, 'author_name' => $author]);
    }

    public function testDataBuildsExcerptAndLink(): void
    {
        $this->comment('<script>alert(1)</script>' . str_repeat('가', 60));

        $items = (new RecentCommentsWidget())->data()['items'];

        $this->assertCount(1, $items);
        $this->assertStringStartsWith('/board/notice/', $items[0]['url']);
        $this->assertStringNotContainsString('<', $items[0]['excerpt']);
        $this->assertLessThanOrEqual(41, mb_strlen($items[0]['excerpt']));
        $this->assertSame('손님', $items[0]['author']);
    }

    public function testViewEscapesAndHidesWhenEmpty(): void
    {
        $this->comment('<i>내용</i>');
        $html = view('\Plugins\RecentComments\Views\widgets\recent_comments', (new RecentCommentsWidget())->data());

        $this->assertStringNotContainsString('<b>글', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertSame('', trim(view('\Plugins\RecentComments\Views\widgets\recent_comments', ['items' => []])));
    }

    public function testParseLimitClamps(): void
    {
        $this->assertSame(5, RecentCommentsWidget::parseLimit(''));
        $this->assertSame(5, RecentCommentsWidget::parseLimit('abc'));
        $this->assertSame(1, RecentCommentsWidget::parseLimit('0'));
        $this->assertSame(10, RecentCommentsWidget::parseLimit('99'));
        $this->assertSame(3, RecentCommentsWidget::parseLimit('3'));
    }
}
