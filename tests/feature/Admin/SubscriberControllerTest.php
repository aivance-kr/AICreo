<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SubscriberModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class SubscriberControllerTest extends AdminTestCase
{
    private SubscriberModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SubscriberModel();
    }

    public function testListShowsSubscribersAndFiltersByStatus(): void
    {
        $this->model->confirm($this->model->subscribe('alpha@example.com')['confirm_token']);
        $this->model->subscribe('beta@example.com');

        $all = $this->withSession($this->adminSession)->get('admin/subscribers');
        $all->assertSee('alpha@example.com');
        $all->assertSee('beta@example.com');

        $confirmed = $this->withSession($this->adminSession)->get('admin/subscribers?status=confirmed');
        $confirmed->assertSee('alpha@example.com');
        $confirmed->assertDontSee('beta@example.com');
    }

    public function testExportReturnsCsvWithSpreadsheetFormulaNeutralized(): void
    {
        $this->model->subscribe('alpha@example.com');
        $this->model->subscribe('=cmd@example.com');

        $result = $this->withSession($this->adminSession)->get('admin/subscribers/export');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $result->getBody();
        $this->assertStringContainsString('alpha@example.com', $body);
        $this->assertStringContainsString("'=cmd@example.com", $body);
        $this->assertStringNotContainsString(',=cmd@example.com', $body);
    }

    public function testAdminCanDeleteSubscriber(): void
    {
        $id = (int) $this->model->subscribe('alpha@example.com')['id'];

        $this->withSession($this->adminSession)->post("admin/subscribers/{$id}/delete");

        $this->assertNull($this->model->find($id));
    }

    public function testMemberCannotDelete(): void
    {
        $id = (int) $this->model->subscribe('alpha@example.com')['id'];

        $this->withSession($this->memberSession)->post("admin/subscribers/{$id}/delete");

        $this->assertNotNull($this->model->find($id));
    }
}
