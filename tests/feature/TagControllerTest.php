<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class TagControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        cache()->delete('public_tag_counts');
        cache()->delete('seo_sitemap');
    }

    /**
     * @param array<string, mixed> $override
     */
    private function makePost(string $tags, array $override = []): int
    {
        return (int) (new PostModel())->insert($override + [
            'board_id'    => (int) (new BoardModel())->getBySlug('free')['id'],
            'title'       => '태그 테스트 글',
            'content'     => '본문',
            'author_name' => '작성자',
            'tags'        => $tags,
            'is_active'   => 1,
        ], true);
    }

    public function testCountsOnlyPublicPosts(): void
    {
        $this->makePost('php,ci4');
        $this->makePost('PHP');
        $this->makePost('hidden-tag', ['is_secret' => 1]);
        $this->makePost('draft-tag', ['is_active' => 0]);

        $counts = (new PostModel())->getPublicTagCounts();

        $this->assertSame(2, $counts['PHP']);
        $this->assertSame(1, $counts['ci4']);
        $this->assertArrayNotHasKey('hidden-tag', $counts);
        $this->assertArrayNotHasKey('draft-tag', $counts);
    }

    public function testCacheInvalidatedOnPostChange(): void
    {
        $id = $this->makePost('first');
        $this->assertArrayHasKey('first', (new PostModel())->getPublicTagCounts());

        (new PostModel())->update($id, ['tags' => 'second']);
        $counts = (new PostModel())->getPublicTagCounts();

        $this->assertArrayNotHasKey('first', $counts);
        $this->assertArrayHasKey('second', $counts);
    }

    public function testTagCloudLimitsAndOrdersByCount(): void
    {
        $this->makePost('a,b');
        $this->makePost('b');

        $this->assertSame(['b' => 2], (new PostModel())->getTagCloud(1));
    }

    public function testIndexListsTags(): void
    {
        $this->makePost('고유태그');

        $result = $this->get('tags');

        $result->assertStatus(200);
        $result->assertSee('#고유태그');
    }

    public function testShowListsPostsOfTag(): void
    {
        $this->makePost('고유태그', ['title' => '태그글제목']);

        $result = $this->get('tags/' . rawurlencode('고유태그'));

        $result->assertStatus(200);
        $result->assertSee('태그글제목');
    }

    public function testShowUnknownTagThrowsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->get('tags/no-such-tag');
    }

    public function testSitemapIncludesTagsPageWhenTagsExist(): void
    {
        $this->makePost('sitemap-tag');

        $this->assertStringContainsString(base_url('tags'), $this->get('sitemap.xml')->getBody());
    }
}
