<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Exceptions\UserScopeMissingException;
use App\Libraries\Plugin\PluginManager;
use App\Libraries\Plugin\WidgetRenderer;
use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Composer\Autoload\ClassLoader;
use Config\Database;
use Config\Services;
use Plugins\Bookmark\Models\BookmarkItemModel;
use Plugins\Bookmark\Widgets\PostBookmarkWidget;
use Tests\Support\FeatureTestCase;

/**
 * 북마크 플러그인: 토글·사용자 격리·노출 가능한 글만 목록·위젯 컨텍스트.
 *
 * 마이그레이션이 DDL(암묵 커밋)이라 SamplePluginTest 처럼 tearDown 에서 직접 정리한다.
 *
 * @internal
 */
final class BookmarkPluginTest extends FeatureTestCase
{
    private string $tmpDir;
    private PluginManager $manager;
    private int $boardId;
    private int $userA;
    private int $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = WRITEPATH . 'plugin_bookmark_test';
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        mkdir($this->tmpDir . '/Plugins/', 0777, true);
        exec(sprintf('cp -R %s %s', escapeshellarg(APPPATH . 'Plugins/Bookmark'), escapeshellarg($this->tmpDir . '/Plugins/')));

        $this->manager = new PluginManager($this->tmpDir . '/active.php', $this->tmpDir . '/Plugins/');
        Services::injectMock('pluginManager', $this->manager);

        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                $loader[0]->addPsr4('Plugins\\Bookmark\\', $this->manager->pluginPath('Bookmark'));

                break;
            }
        }

        $this->manager->sync();
        $this->manager->activate('bookmark');

        // DDL 이후 쓰기는 롤백되지 않으므로 전용 게시판을 만들어 tearDown 에서 지운다
        $this->boardId = (int) (new BoardModel())->insert([
            'slug'            => 'bookmark-test',
            'name'            => '북마크 테스트',
            'read_permission' => 'guest',
            'is_active'       => 1,
        ]);
        $this->userA = $this->makeUser('bm_a');
        $this->userB = $this->makeUser('bm_b');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        $this->db->transRollback();

        Database::forge()->dropTable('bookmark_items', true);
        db_connect()->table('migrations')->like('namespace', 'Plugins\\Bookmark', 'after')->delete();
        db_connect()->table('plugins')->where('plugin_key', 'bookmark')->delete();
        // DDL 커밋 이후 만든 사용자·글은 롤백되지 않으므로 직접 지운다
        db_connect()->table('posts')->whereIn('user_id', [$this->userA, $this->userB])->delete();
        db_connect()->table('boards')->where('id', $this->boardId)->delete();
        db_connect()->table('users')->whereIn('id', [$this->userA, $this->userB])->delete();

        session()->remove(['user_id', 'user_role']);
        Services::reset();
        parent::tearDown();
    }

    private function makeUser(string $name): int
    {
        return (int) (new UserModel())->insert([
            'username' => $name,
            'email'    => $name . '@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'nickname' => $name,
            'role'     => 'member',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makePost(array $overrides = []): int
    {
        return (int) (new PostModel())->insert(array_merge([
            'board_id' => $this->boardId,
            'user_id'  => $this->userA,
            'title'    => '북마크 글',
            'content'  => '본문',
        ], $overrides));
    }

    public function testToggleSavesThenRemoves(): void
    {
        $post  = $this->makePost();
        $model = model(BookmarkItemModel::class)->forUser($this->userA);

        $this->assertTrue($model->toggle($post));
        $this->assertTrue($model->isBookmarked($post));
        $this->assertFalse($model->toggle($post));
        $this->assertFalse($model->isBookmarked($post));
    }

    public function testBookmarksAreIsolatedPerUser(): void
    {
        $post = $this->makePost();
        (new BookmarkItemModel())->forUser($this->userA)->toggle($post);

        $this->assertFalse((new BookmarkItemModel())->forUser($this->userB)->isBookmarked($post));
        $this->assertSame(0, (new BookmarkItemModel())->forUser($this->userB)->countVisible('member'));
        $this->assertSame(1, (new BookmarkItemModel())->forUser($this->userA)->countVisible('member'));
    }

    public function testWithoutSessionThrows(): void
    {
        $this->expectException(UserScopeMissingException::class);
        (new BookmarkItemModel())->isBookmarked(1);
    }

    public function testListExcludesHiddenSecretAndDeletedPosts(): void
    {
        $model = (new BookmarkItemModel())->forUser($this->userA);
        $ok    = $this->makePost(['title' => '보이는 글']);
        $hide  = $this->makePost(['title' => '숨김', 'is_active' => 0]);
        $sec   = $this->makePost(['title' => '비밀', 'is_secret' => 1]);
        $del   = $this->makePost(['title' => '삭제']);

        foreach ([$ok, $hide, $sec, $del] as $id) {
            $model->toggle($id);
        }
        (new PostModel())->delete($del);

        $rows = $model->listVisible('member', 20, 0);

        $this->assertSame(['보이는 글'], array_column($rows, 'title'));
        $this->assertSame(1, $model->countVisible('member'));
    }

    public function testFindVisiblePostRespectsReadPermission(): void
    {
        $post  = $this->makePost();
        $model = (new BookmarkItemModel())->forUser($this->userA);
        $this->assertNotNull($model->findVisiblePost($post, 'member'));

        $this->db->table('boards')->where('id', $this->boardId)->update(['read_permission' => 'admin']);

        $this->assertNull($model->findVisiblePost($post, 'member'));
        $this->assertNotNull($model->findVisiblePost($post, 'admin'));
    }

    public function testWidgetHidesForGuestAndReceivesPostContext(): void
    {
        $post   = $this->makePost();
        $widget = new PostBookmarkWidget();

        $this->assertFalse($widget->data(['post_id' => $post])['show']);

        session()->set(['user_id' => $this->userA, 'user_role' => 'member']);
        $this->assertSame(['show' => true, 'postId' => $post, 'bookmarked' => false], $widget->data(['post_id' => $post]));
        $this->assertFalse($widget->data([])['show']);

        $html = (new WidgetRenderer($this->manager))->render('post_actions', ['post_id' => $post]);
        $this->assertStringContainsString('/my/bookmarks/toggle/' . $post, $html);
        $this->assertStringContainsString('북마크', $html);
    }
}
