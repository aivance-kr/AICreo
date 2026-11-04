<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\TrashManager;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 휴지통 — 소프트 삭제된 글·댓글·페이지의 목록, 복원, 영구 삭제.
 */
class TrashController extends BaseController
{
    private const PER_PAGE = 20;

    private readonly TrashManager $trash;

    public function __construct()
    {
        $this->trash = new TrashManager();
    }

    public function index(): string
    {
        $type = (string) ($this->request->getGet('type') ?? TrashManager::TYPE_POSTS);
        if (! TrashManager::isValidType($type)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result = $this->trash->list($type, $page, self::PER_PAGE);

        return $this->render('admin/trash/list', [
            'type'          => $type,
            'types'         => TrashManager::TYPES,
            'counts'        => $this->trash->counts(),
            'items'         => $result['items'],
            'total'         => $result['total'],
            'currentPage'   => $page,
            'totalPages'    => (int) ceil($result['total'] / self::PER_PAGE),
            'retentionDays' => TrashManager::RETENTION_DAYS,
        ]);
    }

    public function restore(string $type, int $id): ResponseInterface
    {
        $this->assertType($type);

        return $this->trash->restore($type, $id)
            ? redirect()->back()->with('success', '복원했습니다.')
            : redirect()->back()->with('error', '휴지통에서 항목을 찾을 수 없습니다.');
    }

    public function purge(string $type, int $id): ResponseInterface
    {
        $this->assertType($type);

        return $this->trash->purge($type, $id)
            ? redirect()->back()->with('success', '영구 삭제했습니다.')
            : redirect()->back()->with('error', '휴지통에서 항목을 찾을 수 없습니다.');
    }

    private function assertType(string $type): void
    {
        if (! TrashManager::isValidType($type)) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
