<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BlockedIpModel;
use App\Models\PageVisitModel;
use CodeIgniter\I18n\Time;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PageVisitNotFoundBurstTest extends DatabaseTestCase
{
    private function insertNotFound(string $ip, Time $at, int $isNotFound = 1): void
    {
        $this->db->table('page_visits')->insert([
            'uri' => '/x', 'ip_address' => $ip, 'is_not_found' => $isNotFound, 'created_at' => $at->toDateTimeString(),
        ]);
    }

    public function testThreeNotFoundWithinAnHourIsDetected(): void
    {
        $now = Time::now();
        $this->insertNotFound('1.1.1.1', $now->subMinutes(50));
        $this->insertNotFound('1.1.1.1', $now->subMinutes(30));
        $this->insertNotFound('1.1.1.1', $now->subMinutes(5));

        $ips = (new PageVisitModel())->notFoundBurstIps($now->subHours(24), 3, 3600);

        $this->assertSame(['1.1.1.1'], $ips);
    }

    public function testSpreadOutOrNormalVisitsAreNotDetected(): void
    {
        $now = Time::now();
        $this->insertNotFound('2.2.2.2', $now->subHours(5));
        $this->insertNotFound('2.2.2.2', $now->subHours(3));
        $this->insertNotFound('2.2.2.2', $now->subMinutes(5));
        $this->insertNotFound('3.3.3.3', $now->subMinutes(30), 0);
        $this->insertNotFound('3.3.3.3', $now->subMinutes(20), 0);
        $this->insertNotFound('3.3.3.3', $now->subMinutes(10), 0);

        $this->assertSame([], (new PageVisitModel())->notFoundBurstIps($now->subHours(24), 3, 3600));
    }

    public function testMarkNotFoundSetsFlag(): void
    {
        $model = new PageVisitModel();
        $id    = $model->logVisit('/nope', '4.4.4.4', null, null);

        $model->markNotFound($id);

        $this->assertSame(1, (int) $model->find($id)['is_not_found']);
    }

    public function testCommandBlocksBurstIp(): void
    {
        $now = Time::now();
        $this->insertNotFound('5.5.5.5', $now->subMinutes(40));
        $this->insertNotFound('5.5.5.5', $now->subMinutes(20));
        $this->insertNotFound('5.5.5.5', $now->subMinutes(1));

        command('ip:auto-block');

        $this->assertTrue((new BlockedIpModel())->isBlocked('5.5.5.5'));
    }
}
