<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SubscriberModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 뉴스레터 구독자 목록·CSV 내보내기·삭제.
 */
class SubscriberController extends BaseController
{
    private const PER_PAGE = 20;

    private readonly SubscriberModel $subscriberModel;

    public function __construct()
    {
        $this->subscriberModel = new SubscriberModel();
    }

    public function index(): string
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $status  = (string) $this->request->getGet('status');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result  = $this->subscriberModel->search($keyword, $status, $page, self::PER_PAGE);

        return $this->render('admin/subscribers/list', [
            'items'        => $result['items'],
            'total'        => $result['total'],
            'keyword'      => $keyword,
            'status'       => $status,
            'statusCounts' => $this->subscriberModel->countByStatus(),
            'currentPage'  => $page,
            'totalPages'   => (int) ceil($result['total'] / self::PER_PAGE),
        ]);
    }

    public function export(): ResponseInterface
    {
        $rows = $this->subscriberModel->exportAll(
            trim((string) $this->request->getGet('q')),
            (string) $this->request->getGet('status'),
        );

        $out = fopen('php://temp', 'w+b');
        fwrite($out, "\xEF\xBB\xBF"); // 엑셀에서 한글이 깨지지 않게 BOM
        fputcsv($out, ['이메일', '상태', '확인일', '해지일', '신청일'], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, [
                self::csvSafe((string) $row['email']),
                $row['status'],
                $row['confirmed_at'],
                $row['unsubscribed_at'],
                $row['created_at'],
            ], ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="subscribers-' . date('Ymd') . '.csv"')
            ->setBody($csv);
    }

    public function delete(int $id): ResponseInterface
    {
        $row = $this->subscriberModel->find($id);
        if ($row === null) {
            return redirect()->back()->with('error', '구독자를 찾을 수 없습니다.');
        }

        $this->subscriberModel->remove($id);

        return redirect()->back()->with('success', "{$row['email']} 구독자를 삭제했습니다.");
    }

    /**
     * 스프레드시트 수식 주입 방지 — 수식 시작 문자로 시작하는 셀 앞에 작은따옴표를 붙인다.
     */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }
}
