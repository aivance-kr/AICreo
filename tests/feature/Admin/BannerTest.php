<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BannerModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class BannerTest extends AdminTestCase
{
    private function makeBanner(): int
    {
        return (int) (new BannerModel())->insert([
            'image_path' => 'uploads/banners/sample.jpg',
            'position'   => 'main_top',
            'priority'   => 0,
            'is_active'  => 1,
        ]);
    }

    public function testIndexLoads(): void
    {
        $this->withSession($this->adminSession)->get('admin/banners')->assertStatus(200);
    }

    public function testStoreRejectsInvalidPosition(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/banners/create', [
            'position' => 'nowhere',
        ]);

        $result->assertRedirect();
        $this->assertSame(0, (new BannerModel())->countAllResults());
    }

    public function testStoreRequiresImage(): void
    {
        // position 은 유효하지만 이미지 미첨부
        $result = $this->withSession($this->adminSession)->post('admin/banners/create', [
            'position' => 'main_top',
        ]);

        $result->assertRedirect();
        $this->assertSame(0, (new BannerModel())->countAllResults());
    }

    public function testUpdateChangesFieldsKeepingImage(): void
    {
        $id = $this->makeBanner();

        $result = $this->withSession($this->adminSession)->post("admin/banners/{$id}/edit", [
            'position'  => 'sub_left',
            'link_url'  => 'https://example.com',
            'alt_text'  => '봄맞이 신제품 할인 안내',
            'priority'  => 3,
            'is_active' => 1,
        ]);

        $result->assertRedirectTo('/admin/banners');
        $banner = (new BannerModel())->find($id);
        $this->assertSame('sub_left', $banner['position']);
        $this->assertSame('https://example.com', $banner['link_url']);
        $this->assertSame('봄맞이 신제품 할인 안내', $banner['alt_text']);
        $this->assertSame('uploads/banners/sample.jpg', $banner['image_path']);
    }

    /**
     * 링크가 걸린 배너는 대체 텍스트가 없으면 접근 가능한 이름이 없는 링크가 된다
     * (WCAG 2.4.4 / 4.1.2). 그래서 link_url 이 있으면 alt_text 를 강제한다.
     */
    public function testUpdateRejectsLinkedBannerWithoutAltText(): void
    {
        $id = $this->makeBanner();

        $result = $this->withSession($this->adminSession)->post("admin/banners/{$id}/edit", [
            'position'  => 'sub_left',
            'link_url'  => 'https://example.com',
            'priority'  => 3,
            'is_active' => 1,
        ]);

        $result->assertRedirect();
        $this->assertNotSame('/admin/banners', $result->getRedirectUrl());

        // 저장되지 않아야 한다
        $banner = (new BannerModel())->find($id);
        $this->assertNotSame('https://example.com', $banner['link_url']);
    }

    /**
     * 링크가 없는 배너(장식)는 대체 텍스트 없이도 저장된다.
     */
    public function testUpdateAllowsUnlinkedBannerWithoutAltText(): void
    {
        $id = $this->makeBanner();

        $result = $this->withSession($this->adminSession)->post("admin/banners/{$id}/edit", [
            'position'  => 'sub_left',
            'priority'  => 3,
            'is_active' => 1,
        ]);

        $result->assertRedirectTo('/admin/banners');
        $this->assertSame('sub_left', (new BannerModel())->find($id)['position']);
    }

    public function testAdminDeletesBanner(): void
    {
        $id = $this->makeBanner();

        $result = $this->withSession($this->adminSession)->post("admin/banners/{$id}/delete");

        $result->assertRedirectTo('/admin/banners');
        $this->assertNull((new BannerModel())->find($id));
    }
}
