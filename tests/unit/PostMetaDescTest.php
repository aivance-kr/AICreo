<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PostModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PostMetaDescTest extends CIUnitTestCase
{
    public function testEmptyBecomesNull(): void
    {
        $this->assertNull(PostModel::normalizeMetaDesc(null));
        $this->assertNull(PostModel::normalizeMetaDesc("  \n "));
    }

    public function testCollapsesWhitespaceAndLimitsLength(): void
    {
        $this->assertSame('한 줄 설명', PostModel::normalizeMetaDesc("  한 줄\n  설명 "));
        $this->assertSame(300, mb_strlen((string) PostModel::normalizeMetaDesc(str_repeat('가', 400))));
    }
}
