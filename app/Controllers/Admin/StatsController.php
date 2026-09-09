<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PageVisitModel;
use CodeIgniter\I18n\Time;

class StatsController extends BaseController
{
    private readonly PageVisitModel $visitModel;

    public function __construct()
    {
        $this->visitModel = new PageVisitModel();
    }

    public function index(): string
    {
        $todayStart = Time::now()->setTime(0, 0, 0);
        $weekStart  = Time::now()->subDays(6)->setTime(0, 0, 0);
        $monthStart = Time::now()->subDays(29)->setTime(0, 0, 0);

        return $this->render('admin/stats/index', [
            'todayCount'     => $this->visitModel->countSince($todayStart),
            'todayUniqueIps' => $this->visitModel->countUniqueIpsSince($todayStart),
            'weekCount'      => $this->visitModel->countSince($weekStart),
            'monthCount'     => $this->visitModel->countSince($monthStart),
            'dailyCounts'    => $this->visitModel->dailyCounts(14),
            'topPages'       => $this->visitModel->topPages(30, 10),
        ]);
    }
}
