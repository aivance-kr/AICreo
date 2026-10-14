<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BlockedIpModel;
use CodeIgniter\HTTP\ResponseInterface;

class BlockedIpController extends BaseController
{
    private readonly BlockedIpModel $blockedIpModel;

    public function __construct()
    {
        $this->blockedIpModel = new BlockedIpModel();
    }

    public function index(): string
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;
        $result  = $this->blockedIpModel->searchActive($keyword, $page, $perPage);

        return $this->render('admin/blocked_ips/list', [
            'items'       => $result['items'],
            'total'       => $result['total'],
            'keyword'     => $keyword,
            'currentPage' => $page,
            'totalPages'  => (int) ceil($result['total'] / $perPage),
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $row = $this->blockedIpModel->find($id);
        if ($row === null) {
            return redirect()->back()->with('error', '차단 내역을 찾을 수 없습니다.');
        }

        $this->blockedIpModel->delete($id);

        return redirect()->back()->with('success', "{$row['ip_address']} 차단을 해제했습니다.");
    }
}
