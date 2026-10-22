<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PostModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * 게시글 태그 입력 정규화.
 *
 * @internal
 */
final class PostTagsTest extends CIUnitTestCase
{
    public function testNormalizeTrimsDedupesAndStripsHash(): void
    {
        $this->assertSame('php,CI4,개발', PostModel::normalizeTags(' #php, CI4 ,,php,ci4, 개발 '));
    }

    public function testNormalizeEmptyReturnsNull(): void
    {
        $this->assertNull(PostModel::normalizeTags(' , ,'));
        $this->assertNull(PostModel::normalizeTags(null));
    }

    public function testNormalizeLimitsCountAndLength(): void
    {
        $many = implode(',', range(1, 15));
        $this->assertCount(PostModel::MAX_TAGS, PostModel::splitTags(PostModel::normalizeTags($many)));
        $this->assertSame(PostModel::MAX_TAG_LENGTH, mb_strlen((string) PostModel::normalizeTags(str_repeat('가', 50))));
    }

    public function testSplitTags(): void
    {
        $this->assertSame([], PostModel::splitTags(null));
        $this->assertSame(['a', 'b'], PostModel::splitTags('a,b'));
    }
}
