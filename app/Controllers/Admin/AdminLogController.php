<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;

class AdminLogController extends BaseController
{
    private const PER_PAGE = 30;

    public function index(): string
    {
        $action     = (string) $this->request->getGet('action');
        $targetType = (string) $this->request->getGet('target_type');
        $keyword    = trim((string) $this->request->getGet('q'));
        $page       = max(1, (int) ($this->request->getGet('page') ?? 1));

        // 목록에 없는 값은 필터로 쓰지 않는다
        $action     = isset(AdminLogModel::ACTIONS[$action]) ? $action : '';
        $targetType = isset(AdminLogModel::TARGET_TYPES[$targetType]) ? $targetType : '';

        $result = model(AdminLogModel::class)->search($action, $targetType, $keyword, $page, self::PER_PAGE);

        return $this->render('admin/admin_logs/list', [
            'items'       => $result['items'],
            'total'       => $result['total'],
            'action'      => $action,
            'targetType'  => $targetType,
            'keyword'     => $keyword,
            'currentPage' => $page,
            'totalPages'  => (int) ceil($result['total'] / self::PER_PAGE),
        ]);
    }
}
