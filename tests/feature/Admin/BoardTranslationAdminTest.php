<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardModel;
use App\Models\BoardTranslationModel;
use Tests\Support\AdminTestCase;
use Tests\Support\EnablesLocales;

/**
 * 관리자 게시판 폼의 언어별 이름·설명 (#584).
 *
 * @internal
 */
final class BoardTranslationAdminTest extends AdminTestCase
{
    use EnablesLocales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLocales();
    }

    protected function tearDown(): void
    {
        $this->restoreLocales();

        parent::tearDown();
    }

    public function testUpdateSavesTranslation(): void
    {
        $id = (int) (new BoardModel())->getBySlug('free')['id'];

        $this->withSession($this->adminSession)->post("admin/boards/{$id}/edit", [
            'name'            => '자유게시판',
            'read_permission' => 'guest',
            'is_active'       => 1,
            'translations'    => ['en' => ['name' => 'Free board', 'description' => 'Talk freely']],
        ]);

        $this->assertSame(
            ['en' => ['name' => 'Free board', 'description' => 'Talk freely']],
            (new BoardTranslationModel())->forBoard($id),
        );
    }

    public function testCreateSavesTranslation(): void
    {
        $this->withSession($this->adminSession)->post('admin/boards/create', [
            'slug'             => 'faq',
            'name'             => '자주 묻는 질문',
            'read_permission'  => 'guest',
            'write_permission' => 'member',
            'translations'     => ['en' => ['name' => 'FAQ']],
        ]);

        $id = (int) (new BoardModel())->getBySlug('faq')['id'];
        $this->assertSame('FAQ', (new BoardTranslationModel())->forBoard($id)['en']['name']);
    }

    public function testEditFormShowsFieldPerExtraLocale(): void
    {
        $id = (int) (new BoardModel())->getBySlug('free')['id'];

        $body = $this->withSession($this->adminSession)->get("admin/boards/{$id}/edit")->getBody();

        $this->assertStringContainsString('name="translations[en][name]"', $body);
        $this->assertStringNotContainsString('name="translations[ko][name]"', $body);
    }
}
