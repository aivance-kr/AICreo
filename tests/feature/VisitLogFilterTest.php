<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PageVisitModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class VisitLogFilterTest extends AdminTestCase
{
    private PageVisitModel $visitModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visitModel = new PageVisitModel();
    }

    public function testFrontPageVisitIsLogged(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Test Browser)'])->get('/');

        $this->assertSame(1, $this->visitModel->countAllResults());
        $this->assertSame('/', $this->visitModel->first()['uri']);
    }

    public function testAdminPageVisitIsNotLogged(): void
    {
        $this->withSession($this->adminSession)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Test Browser)'])
            ->get('admin/dashboard');

        $this->assertSame(0, $this->visitModel->countAllResults());
    }

    public function testBotUserAgentIsNotLogged(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/');

        $this->assertSame(0, $this->visitModel->countAllResults());
    }
}
