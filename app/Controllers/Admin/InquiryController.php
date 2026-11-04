<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\InquiryForm;
use App\Models\InquiryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class InquiryController extends BaseController
{
    private const EXPORT_LIMIT = 5000;

    private readonly InquiryModel $model;

    public function __construct()
    {
        $this->model = new InquiryModel();
    }

    public function index(): string
    {
        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $filter = $this->request->getGet('filter') ?? '';
        $limit  = 20;

        $builder = $this->model->builder();
        if ($filter === 'unread') {
            $builder->where('is_read', 0);
        }

        $total     = (clone $builder)->countAllResults(false);
        $inquiries = $builder->orderBy('id', 'DESC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()->getResultArray();

        return $this->render('admin/inquiries/index', [
            'inquiries'   => $inquiries,
            'totalPages'  => (int) ceil($total / $limit),
            'currentPage' => $page,
            'total'       => $total,
            'filter'      => $filter,
            'unreadCount' => $this->model->getUnreadCount(),
            'totalAll'    => $this->model->countAllResults(),
        ]);
    }

    public function view(int $id): ResponseInterface|string
    {
        $inquiry = $this->model->find($id);
        if (! $inquiry) {
            return redirect()->to('/admin/inquiries');
        }

        $this->model->markRead($id);

        return $this->render('admin/inquiries/view', ['inquiry' => $inquiry, 'extra' => InquiryModel::extraOf($inquiry)]);
    }

    /**
     * CSV 내보내기. 기본 항목 뒤에 제출된 추가 항목 라벨별 열이 붙는다.
     */
    public function export(): ResponseInterface
    {
        $builder = $this->model->builder();
        if ($this->request->getGet('filter') === 'unread') {
            $builder->where('is_read', 0);
        }
        $rows = $builder->orderBy('id', 'DESC')->limit(self::EXPORT_LIMIT)->get()->getResultArray();

        $labels = [];
        $parsed = [];

        foreach ($rows as $row) {
            foreach (InquiryModel::extraOf($row) as $item) {
                $labels[$item['label']]             = $item['label'];
                $parsed[$row['id']][$item['label']] = $item['value'];
            }
        }

        $out = fopen('php://temp', 'r+b');
        fwrite($out, "\xEF\xBB\xBF"); // 엑셀에서 한글이 깨지지 않게 BOM
        fputcsv($out, array_merge(['번호', '이름', '이메일', '연락처', '제목', '내용', '접수일'], array_values($labels)), ',', '"', '');

        foreach ($rows as $row) {
            $line = [$row['id'], $row['name'], $row['email'], $row['phone'], $row['subject'], $row['message'], $row['created_at']];

            foreach ($labels as $label) {
                $line[] = $parsed[$row['id']][$label] ?? '';
            }
            fputcsv($out, array_map(self::csvSafe(...), $line), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="inquiries-' . date('Ymd') . '.csv"')
            ->setBody($csv);
    }

    /**
     * 첨부 파일 내려받기. 항목 번호는 저장된 추가 항목 목록의 위치다.
     */
    public function file(int $id, int $index): ResponseInterface
    {
        $inquiry = $this->model->find($id);
        $item    = $inquiry ? (InquiryModel::extraOf($inquiry)[$index] ?? null) : null;
        // 저장 이름은 서버가 만든 hex+확장자만 허용해 경로 조작을 막는다
        if ($item === null || ! isset($item['file']) || preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $item['file']) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $path = InquiryForm::uploadDir() . '/' . $item['file'];
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName($item['value'])
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * CSV 수식 주입 방지: 수식 시작 문자로 시작하면 작은따옴표를 붙인다
     */
    private static function csvSafe(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'" . $text : $text;
    }

    public function delete(int $id): ResponseInterface|string
    {
        $this->model->delete($id);

        return redirect()->to('/admin/inquiries')->with('success', '삭제되었습니다.');
    }
}
