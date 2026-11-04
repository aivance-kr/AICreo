<?php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\InquiryForm;
use App\Libraries\Seo\Alternates;
use App\Libraries\Seo\JsonLdBuilder;
use App\Libraries\SlackInquiryNotifier;
use App\Models\BoardModel;
use App\Models\InquiryFieldModel;
use App\Models\InquiryModel;
use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

class PageController extends BaseController
{
    private readonly PageModel $pageModel;

    public function __construct()
    {
        $this->pageModel = new PageModel();
    }

    /**
     * 슬러그 기반 동적 페이지 라우팅
     * layout 값에 따라 다른 뷰 파일 렌더링
     */
    public function show(string $slug): ResponseInterface|string
    {
        $page = $this->pageModel->getBySlug($slug, $this->request->getLocale());

        // 짧은 주소(/{게시글ID}) — 같은 슬러그의 페이지가 없을 때만 게시글로 보낸다.
        // 게시글이 다른 게시판으로 옮겨져도 깨지지 않게 301이 아닌 302를 쓴다.
        if (! $page && ctype_digit($slug)) {
            $post  = (new PostModel())->find((int) $slug);
            $board = $post ? (new BoardModel())->find($post['board_id']) : null;

            // 비공개·비밀·숨김 글은 ID 존재 여부가 드러나지 않도록 리다이렉트하지 않고 404로 둔다.
            if ($board && $board['is_active'] && $post['is_active'] && ! $post['is_secret'] && $board['read_permission'] === 'guest') {
                return redirect()->to('/board/' . $board['slug'] . '/' . $post['id']);
            }
        }

        if (! $page) {
            throw PageNotFoundException::forPageNotFound();
        }

        $viewFile = match ($page['layout']) {
            'contact' => 'pages/contact',
            'landing' => 'pages/landing',
            default   => 'pages/default',
        };

        $ld = new JsonLdBuilder();

        return $this->render($viewFile, [
            'page'          => $page + ['alternates' => Alternates::forPage((int) $page['id']), 'translatable' => true],
            'jsonLd'        => [$ld->webPage($page, locale_url($page['slug']))],
            'inquiryFields' => $page['layout'] === 'contact' ? model(InquiryFieldModel::class)->activeFields() : [],
        ]);
    }

    /**
     * 문의폼 처리
     */
    public function inquirySubmit(): ResponseInterface|string
    {
        // 허니팟: 사람 눈에 안 보이는 입력란이 채워졌으면 봇이다. 저장·알림 없이 성공처럼 돌려보낸다.
        if ((string) $this->request->getPost('website') !== '') {
            return redirect()->back()->with('success', lang('Content.inquiryReceived'));
        }

        $rules = [
            'name'    => 'required|max_length[100]',
            'email'   => 'required|valid_email',
            'message' => 'required|min_length[10]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $fields = model(InquiryFieldModel::class)->activeFields();
        $files  = [];

        foreach ($fields as $field) {
            if ($field['field_type'] === 'file') {
                $files[(int) $field['id']] = $this->request->getFile('cf_file.' . $field['id']);
            }
        }
        $input = $this->request->getPost('cf');
        $extra = (new InquiryForm())->collect($fields, is_array($input) ? $input : [], $files, InquiryForm::uploadDir());
        if ($extra['errors'] !== []) {
            return redirect()->back()->withInput()->with('errors', $extra['errors']);
        }

        $inquiryId = (int) (new InquiryModel())->insert([
            'name'       => $this->request->getPost('name'),
            'email'      => $this->request->getPost('email'),
            'phone'      => $this->request->getPost('phone'),
            'subject'    => $this->request->getPost('subject'),
            'message'    => $this->request->getPost('message'),
            'extra_data' => $extra['extra'] === [] ? null : json_encode($extra['extra'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_address' => $this->request->getIPAddress(),
        ]);

        // 운영 채널 Slack 알림 (실패해도 접수에는 영향 없음)
        $extraFields = [];

        foreach ($extra['extra'] as $item) {
            $extraFields[$item['label']] = $item['value'];
        }
        SlackInquiryNotifier::fromEnv('AiCreo')->notify($inquiryId, [
            '이름'      => (string) $this->request->getPost('name'),
            '이메일'    => (string) $this->request->getPost('email'),
            '연락처'    => (string) $this->request->getPost('phone'),
            '제목'      => (string) $this->request->getPost('subject'),
            '접수 시각' => date('Y-m-d H:i:s'),
            '요청 IP'   => $this->request->getIPAddress(),
        ] + $extraFields, (string) $this->request->getPost('message'), base_url('admin/inquiries/' . $inquiryId));

        // 클라이언트 이메일 발송 (설정에서 수신 이메일 읽기)
        $this->sendInquiryEmail($extra['extra']);

        return redirect()->back()->with('success', lang('Content.inquiryReceived'));
    }

    /**
     * @param list<array<string, string>> $extra
     */
    private function sendInquiryEmail(array $extra): void
    {
        $toEmail = $this->viewData['settings']['email'] ?? '';
        if (! $toEmail) {
            return;
        }

        $email = Services::email();
        $email->setTo($toEmail);
        $email->setSubject('[문의] ' . ($this->request->getPost('subject') ?: '새 문의가 도착했습니다'));
        $email->setMessage(
            '이름: ' . $this->request->getPost('name') . "\n" .
            '이메일: ' . $this->request->getPost('email') . "\n" .
            '연락처: ' . $this->request->getPost('phone') . "\n" .
            implode('', array_map(static fn (array $item): string => $item['label'] . ': ' . $item['value'] . "\n", $extra)) . "\n" .
            $this->request->getPost('message'),
        );

        try {
            $email->send();
        } catch (Throwable) {
            // 이메일 발송 실패해도 문의 저장은 완료된 상태
        }
    }
}
