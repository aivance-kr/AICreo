<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AssetHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('asset');
    }

    public function testAppendsModifiedTimeVersion(): void
    {
        $this->assertMatchesRegularExpression('~^/css/tokens\.css\?v=\d{14}$~', asset_url('css/tokens.css'));
    }

    public function testMissingFileHasNoVersion(): void
    {
        $this->assertSame('/css/nope.css', asset_url('/css/nope.css'));
    }
}
