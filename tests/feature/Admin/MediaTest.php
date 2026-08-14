<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MediaModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class MediaTest extends AdminTestCase
{
    private function makeMedia(): int
    {
        return (int) (new MediaModel())->insert([
            'original_name' => 'photo.jpg',
            'stored_name'   => 'abc123.jpg',
            'file_path'     => 'uploads/media/abc123.jpg',
            'file_size'     => 1024,
            'mime_type'     => 'image/jpeg',
            'alt'           => '',
        ]);
    }

    public function testIndexLoads(): void
    {
        $this->withSession($this->adminSession)->get('admin/media')->assertStatus(200);
    }

    public function testPickerListReturnsJsonItems(): void
    {
        $id = $this->makeMedia();

        $result = $this->withSession($this->adminSession)->get('admin/media/list');

        $result->assertStatus(200);
        $result->assertJSONFragment([
            'currentPage' => 1,
            'items'       => [
                ['id' => $id, 'path' => 'uploads/media/abc123.jpg', 'name' => 'photo.jpg', 'alt' => ''],
            ],
        ]);
    }

    public function testUploadWithoutFileReturnsError(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/media/upload');

        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => false, 'error' => '파일 없음']);
    }

    public function testUpdateAltChangesAltText(): void
    {
        $id = $this->makeMedia();

        $result = $this->withSession($this->adminSession)->post("admin/media/{$id}/alt", [
            'alt' => '대체 텍스트',
        ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => true]);
        $this->assertSame('대체 텍스트', (new MediaModel())->find($id)['alt']);
    }

    public function testAdminDeletesMedia(): void
    {
        $id = $this->makeMedia();

        $result = $this->withSession($this->adminSession)->post("admin/media/{$id}/delete");

        $result->assertRedirectTo('/admin/media');
        $this->assertNull((new MediaModel())->find($id));
    }
}
