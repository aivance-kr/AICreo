<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MenuModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class MenuTest extends AdminTestCase
{
    public function testAdminCreatesMenu(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/menus', [
            'title'      => '오시는길',
            'url'        => '/location',
            'sort_order' => 5,
        ]);

        $result->assertRedirectTo('/admin/menus');
        $this->assertSame(1, (new MenuModel())->where('url', '/location')->countAllResults());
    }

    public function testAdminCreatesParentMenuWithoutUrl(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/menus', [
            'title'      => '소개',
            'url'        => '',
            'sort_order' => 0,
        ]);

        $result->assertRedirectTo('/admin/menus');
        $menu = (new MenuModel())->where('title', '소개')->first();
        $this->assertNull($menu['url']);
    }

    public function testStoreRequiresTitle(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/menus', [
            'title'      => '',
            'url'        => '/x',
            'sort_order' => 0,
        ]);

        $result->assertRedirect();
        $this->assertSame(0, (new MenuModel())->where('url', '/x')->countAllResults());
    }

    public function testAdminDeletesMenu(): void
    {
        $model = new MenuModel();
        $id    = (int) $model->insert([
            'parent_id'  => null,
            'title'      => '임시메뉴',
            'url'        => '/temp',
            'sort_order' => 9,
            'is_active'  => 1,
        ]);

        $result = $this->withSession($this->adminSession)->post("admin/menus/{$id}/delete");

        $result->assertRedirectTo('/admin/menus');
        $this->assertNull($model->find($id));
    }

    public function testMenuListRendersDragHandlesAndTreeStructure(): void
    {
        $model    = new MenuModel();
        $parentId = (int) $model->insert(['parent_id' => null, 'title' => '회사소개', 'url' => '/about', 'sort_order' => 1, 'is_active' => 1]);
        $model->insert(['parent_id' => $parentId, 'title' => '연혁', 'url' => '/about/history', 'sort_order' => 1, 'is_active' => 1]);

        $body = $this->withSession($this->adminSession)->get('admin/menus')->getBody();

        $this->assertStringContainsString('drag-handle', $body);
        $this->assertStringContainsString('data-id="' . $parentId . '"', $body);
        $this->assertStringContainsString('menu-children', $body);
        $this->assertStringContainsString('Sortable', $body);
    }

    public function testReorderPersistsNewSortOrder(): void
    {
        $model = new MenuModel();
        $idA   = (int) $model->insert(['parent_id' => null, 'title' => 'A', 'url' => '/a', 'sort_order' => 1, 'is_active' => 1]);
        $idB   = (int) $model->insert(['parent_id' => null, 'title' => 'B', 'url' => '/b', 'sort_order' => 2, 'is_active' => 1]);

        $result = $this->withSession($this->adminSession)->post('admin/menus/reorder', [
            'ids' => [$idB, $idA],
        ]);

        $result->assertOK();
        $result->assertJSONFragment(['success' => true]);
        $this->assertSame('0', $model->find($idB)['sort_order']);
        $this->assertSame('1', $model->find($idA)['sort_order']);
    }

    public function testReorderRequiresAdmin(): void
    {
        $model = new MenuModel();
        $idA   = (int) $model->insert(['parent_id' => null, 'title' => 'A', 'url' => '/a', 'sort_order' => 1, 'is_active' => 1]);

        $result = $this->post('admin/menus/reorder', ['ids' => [$idA]]);

        $result->assertRedirect();
        $this->assertSame('1', $model->find($idA)['sort_order']);
    }
}
