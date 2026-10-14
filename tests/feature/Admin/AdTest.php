<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class AdTest extends AdminTestCase
{
    private function makeAd(): int
    {
        return (int) (new AdModel())->insert([
            'name'      => '홈 상단 광고',
            'position'  => 'home_top',
            'code'      => '<script>adsbygoogle</script>',
            'priority'  => 0,
            'is_active' => 1,
        ]);
    }

    public function testIndexLoads(): void
    {
        $this->withSession($this->adminSession)->get('admin/ads')->assertStatus(200);
    }

    public function testStoreRejectsInvalidPosition(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/ads/create', [
            'name'     => '테스트 광고',
            'position' => 'nowhere',
            'code'     => '<script></script>',
        ]);

        $result->assertRedirect();
        $this->assertSame(0, (new AdModel())->countAllResults());
    }

    public function testStoreRequiresCode(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/ads/create', [
            'name'     => '테스트 광고',
            'position' => 'home_top',
        ]);

        $result->assertRedirect();
        $this->assertSame(0, (new AdModel())->countAllResults());
    }

    public function testStoreCreatesAd(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/ads/create', [
            'name'      => '본문 하단 광고',
            'position'  => 'post_bottom',
            'code'      => '<ins class="adsbygoogle"></ins>',
            'priority'  => 1,
            'is_active' => 1,
        ]);

        $result->assertRedirectTo('/admin/ads');
        $this->assertSame(1, (new AdModel())->countAllResults());
    }

    public function testUpdateChangesFields(): void
    {
        $id = $this->makeAd();

        $result = $this->withSession($this->adminSession)->post("admin/ads/{$id}/edit", [
            'name'      => '수정된 광고',
            'position'  => 'post_top',
            'code'      => '<ins>수정된 코드</ins>',
            'priority'  => 5,
            'is_active' => 0,
        ]);

        $result->assertRedirectTo('/admin/ads');
        $ad = (new AdModel())->find($id);
        $this->assertSame('수정된 광고', $ad['name']);
        $this->assertSame('post_top', $ad['position']);
        $this->assertSame('<ins>수정된 코드</ins>', $ad['code']);
        $this->assertSame(0, (int) $ad['is_active']);
    }

    public function testAdminDeletesAd(): void
    {
        $id = $this->makeAd();

        $result = $this->withSession($this->adminSession)->post("admin/ads/{$id}/delete");

        $result->assertRedirectTo('/admin/ads');
        $this->assertNull((new AdModel())->find($id));
    }

    public function testGetActiveByPositionReturnsOnlyActiveAdsForPosition(): void
    {
        $model = new AdModel();
        $model->insert(['name' => 'A', 'position' => 'home_top', 'code' => 'a', 'priority' => 1, 'is_active' => 1]);
        $model->insert(['name' => 'B', 'position' => 'home_top', 'code' => 'b', 'priority' => 0, 'is_active' => 0]);
        $model->insert(['name' => 'C', 'position' => 'home_bottom', 'code' => 'c', 'priority' => 0, 'is_active' => 1]);

        $active = $model->getActiveByPosition('home_top');

        $this->assertCount(1, $active);
        $this->assertSame('a', $active[0]['code']);
    }
}
