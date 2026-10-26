<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Thumbnail;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ThumbnailTest extends CIUnitTestCase
{
    private const DIR = 'uploads/_thumbtest';

    protected function tearDown(): void
    {
        $base = FCPATH . self::DIR;

        foreach ([...glob($base . '/thumbs/*') ?: [], ...glob($base . '/*') ?: []] as $f) {
            is_file($f) && unlink($f);
        }
        is_dir($base . '/thumbs') && rmdir($base . '/thumbs');
        is_dir($base) && rmdir($base);

        parent::tearDown();
    }

    private function makeImage(string $name, int $width, int $height): string
    {
        $dir = FCPATH . self::DIR;
        is_dir($dir) || mkdir($dir, 0755, true);

        // 압축이 잘 안 되도록 노이즈를 채워 썸네일이 원본보다 확실히 작게 한다
        $img = imagecreatetruecolor($width, $height);

        for ($i = 0; $i < 300; $i++) {
            imagefilledrectangle($img, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), random_int(0, 0xFFFFFF));
        }
        imagejpeg($img, $dir . '/' . $name, 95);

        return self::DIR . '/' . $name;
    }

    public function testPathForPutsThumbInThumbsFolder(): void
    {
        $this->assertSame('uploads/a/2026/10/thumbs/x.jpg', Thumbnail::pathFor('uploads/a/2026/10/x.jpg'));
        $this->assertTrue(Thumbnail::isThumbPath('uploads/a/thumbs/x.jpg'));
        $this->assertFalse(Thumbnail::isThumbPath('uploads/a/x.jpg'));
    }

    public function testLargeImageGetsThumbnailAndResolveReturnsIt(): void
    {
        $rel = $this->makeImage('big.jpg', 1200, 800);

        $this->assertTrue(Thumbnail::create($rel));
        $this->assertSame(Thumbnail::pathFor($rel), Thumbnail::resolve($rel));

        [$w, $h] = getimagesize(FCPATH . Thumbnail::pathFor($rel));
        $this->assertSame(Thumbnail::MAX_EDGE, $w);
        $this->assertSame(267, $h);
    }

    public function testSmallImageKeepsOriginal(): void
    {
        $rel = $this->makeImage('small.jpg', 300, 200);

        $this->assertFalse(Thumbnail::create($rel));
        $this->assertSame($rel, Thumbnail::resolve($rel));
    }

    public function testRemoveDeletesThumbnail(): void
    {
        $rel = $this->makeImage('rm.jpg', 1000, 1000);
        Thumbnail::create($rel);
        Thumbnail::remove($rel);

        $this->assertSame($rel, Thumbnail::resolve($rel));
    }

    public function testUnsupportedExtensionIsIgnored(): void
    {
        $this->assertFalse(Thumbnail::create('uploads/none/anim.gif'));
        $this->assertFalse(Thumbnail::supports('uploads/logo.svg'));
    }
}
