<?php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\Seo\JsonLdBuilder;
use App\Models\BoardModel;
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
        $page = $this->pageModel->getBySlug($slug);

        // 짧은 주소(/{게시글ID}) — 같은 슬러그의 페이지가 없을 때만 게시글로 보낸다.
        // 게시글이 다른 게시판으로 옮겨져도 깨지지 않게 301이 아닌 302를 쓴다.
        if (! $page && ctype_digit($slug)) {
            $post  = (new PostModel())->find((int) $slug);
            $board = $post ? (new BoardModel())->find($post['board_id']) : null;

            if ($board && $board['is_active']) {
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
            'page'   => $page,
            'jsonLd' => [$ld->webPage($page, base_url($page['slug']))],
        ]);
    }

    /**
     * 문의폼 처리
     */
    public function inquirySubmit(): ResponseInterface|string
    {
        $rules = [
            'name'    => 'required|max_length[100]',
            'email'   => 'required|valid_email',
            'message' => 'required|min_length[10]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        (new InquiryModel())->insert([
            'name'       => $this->request->getPost('name'),
            'email'      => $this->request->getPost('email'),
            'phone'      => $this->request->getPost('phone'),
            'subject'    => $this->request->getPost('subject'),
            'message'    => $this->request->getPost('message'),
            'ip_address' => $this->request->getIPAddress(),
        ]);

        // 클라이언트 이메일 발송 (설정에서 수신 이메일 읽기)
        $this->sendInquiryEmail();

        return redirect()->back()->with('success', '문의가 접수되었습니다. 빠른 시일 내에 답변드리겠습니다.');
    }

    private function sendInquiryEmail(): void
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
            '연락처: ' . $this->request->getPost('phone') . "\n\n" .
            $this->request->getPost('message'),
        );

        try {
            $email->send();
        } catch (Throwable) {
            // 이메일 발송 실패해도 문의 저장은 완료된 상태
        }
    }
}
