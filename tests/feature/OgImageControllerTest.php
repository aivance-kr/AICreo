<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\OgImageGenerator;
use App\Models\BoardModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class OgImageControllerTest extends FeatureTestCase
{
    private function makePost(array $overrides = []): int
    {
        return (int) (new PostModel())->insert(array_merge([
            'board_id'    => (int) (new BoardModel())->getBySlug('qna')['id'],
            'title'       => 'OG 이미지 글',
            'content'     => '본문',
            'author_name' => '작성자',
        ], $overrides));
    }

    public function testHiddenPostHasNoOgImage(): void
    {
        $id = $this->makePost(['is_secret' => 1]);

        $this->expectException(PageNotFoundException::class);
        $this->get("og/{$id}.png");
    }

    public function testPublicPostServesPngAndViewReferencesIt(): void
    {
        if (OgImageGenerator::findFont(null) === null) {
            $this->markTestSkipped('시스템에 한글 폰트가 없다.');
        }
        $id = $this->makePost();

        $result = $this->get("og/{$id}.png");
        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'image/png');

        $body = $this->get("board/qna/{$id}")->getBody();
        $this->assertStringContainsString("og/{$id}.png", $body);
    }
}
