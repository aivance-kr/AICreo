<?php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\FileUploader;
use App\Libraries\Seo\JsonLdBuilder;
use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\PostCommentModel;
use App\Models\PostFileModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class BoardController extends BaseController
{
    private readonly BoardModel $boardModel;
    private readonly PostModel $postModel;
    private readonly PostFileModel $fileModel;
    private readonly PostCommentModel $commentModel;
    private readonly BoardCategoryModel $categoryModel;
    private readonly FileUploader $uploader;

    public function __construct()
    {
        $this->boardModel    = new BoardModel();
        $this->postModel     = new PostModel();
        $this->fileModel     = new PostFileModel();
        $this->commentModel  = new PostCommentModel();
        $this->categoryModel = new BoardCategoryModel();
        $this->uploader      = new FileUploader();
    }

    // ─── 목록 ───────────────────────────────────────────────────────────────

    public function index(string $boardSlug): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        if (! $board) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->checkPermission($board['read_permission'])) {
            return redirect()->to('/auth/login')->with('error', '로그인이 필요합니다.');
        }

        $page       = (int) ($this->request->getGet('page') ?? 1);
        $keyword    = $this->request->getGet('keyword');
        $type       = $this->request->getGet('type') ?? 'title';
        $categories = $this->categoryModel->getByBoard($board['id']);
        $categoryId = (int) ($this->request->getGet('category') ?? 0) ?: null;

        if ($categoryId !== null && ! in_array($categoryId, array_map('intval', array_column($categories, 'id')), true)) {
            $categoryId = null;
        }

        if ($keyword) {
            $result  = $this->postModel->search($board['id'], $keyword, $type, $page, $board['posts_per_page'], $categoryId);
            $posts   = $result['posts'];
            $total   = $result['total'];
            $notices = [];
        } else {
            $list    = $this->postModel->getList($board['id'], $page, $board['posts_per_page'], $categoryId);
            $posts   = $list['posts'];
            $notices = $list['notices'];
            $total   = $this->postModel->getTotalCount($board['id'], $categoryId);
        }

        $totalPages = (int) ceil($total / $board['posts_per_page']);

        // 검색결과·비공개 게시판은 색인 제외, canonical 은 자기 참조(페이지·카테고리 파라미터 포함)
        $canonical   = base_url('board/' . $board['slug']);
        $canonicalQs = array_filter(['category' => $categoryId, 'page' => $page > 1 ? $page : null]);
        if ($canonicalQs !== []) {
            $canonical .= '?' . http_build_query($canonicalQs);
        }

        return $this->render('board/list', [
            'board'           => $board,
            'posts'           => $posts,
            'notices'         => $notices,
            'currentPage'     => $page,
            'totalPages'      => $totalPages,
            'total'           => $total,
            'keyword'         => $keyword,
            'searchType'      => $type,
            'categories'      => $categories,
            'currentCategory' => $categoryId,
            'page'            => [
                'title'     => $board['name'],
                'canonical' => $canonical,
                'noindex'   => (bool) $keyword || $board['read_permission'] !== 'guest',
            ],
        ]);
    }

    // ─── 상세 ───────────────────────────────────────────────────────────────

    public function view(string $boardSlug, int $postId): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        $post  = $this->postModel->getDetail($postId);

        if (! $board || ! $post || $post['board_id'] !== $board['id']) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->checkPermission($board['read_permission'])) {
            return redirect()->to('/auth/login')->with('error', '로그인이 필요합니다.');
        }

        // 비밀글 처리
        if ($post['is_secret'] && ! $this->canAccessSecret($post)) {
            return redirect()->back()->with('error', '비밀글입니다.');
        }

        $this->postModel->incrementView($postId);

        $files    = $this->fileModel->getByPost($postId);
        $comments = $this->commentModel->getByPost($postId);
        $prevPost = $this->postModel->getPrevious($board['id'], $postId);
        $nextPost = $this->postModel->getNext($board['id'], $postId);

        // 비밀글·비공개 게시판 글은 색인 제외
        $noindex   = (bool) $post['is_secret'] || $board['read_permission'] !== 'guest';
        $canonical = base_url('board/' . $board['slug'] . '/' . $post['id']);

        // 공개 글에만 Article + BreadcrumbList JSON-LD 부착 (색인 제외 글은 생략)
        $jsonLd = [];
        if (! $noindex) {
            helper('mask');
            $ld     = new JsonLdBuilder();
            $author = $post['user_nickname'] ?? mask_name($post['author_name'] ?? '');
            $jsonLd = [
                $ld->blogPosting($post, $canonical, (string) $author),
                $ld->breadcrumb([
                    ['name' => '홈', 'url' => base_url('/')],
                    ['name' => $board['name'], 'url' => base_url('board/' . $board['slug'])],
                    ['name' => $post['title'], 'url' => $canonical],
                ]),
            ];
        }

        return $this->render('board/view', [
            'board'    => $board,
            'post'     => $post,
            'files'    => $files,
            'comments' => $comments,
            'prevPost' => $prevPost,
            'nextPost' => $nextPost,
            'jsonLd'   => $jsonLd,
            'page'     => [
                'title'     => $post['title'],
                'meta_desc' => mb_substr(trim(strip_tags((string) $post['content'])), 0, 150),
                'canonical' => $canonical,
                'noindex'   => $noindex,
            ],
        ]);
    }

    // ─── 작성 ───────────────────────────────────────────────────────────────

    public function write(string $boardSlug): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        if (! $board) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->checkPermission($board['write_permission'])) {
            session()->setTempdata('redirect_url', current_url(), 300);

            return redirect()->to('/auth/login')->with('error', '글쓰기 권한이 없습니다.');
        }

        return $this->render('board/write', [
            'board'      => $board,
            'post'       => null,
            'categories' => $this->categoryModel->getByBoard($board['id']),
        ]);
    }

    public function store(string $boardSlug): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        if (! $board || ! $this->checkPermission($board['write_permission'])) {
            return redirect()->back()->with('error', '권한이 없습니다.');
        }

        $isGuest = ! session()->get('user_id');

        $rules = ['title' => 'required|max_length[255]', 'content' => 'required'];
        if ($isGuest) {
            $rules['author_name']     = 'required|max_length[50]';
            $rules['author_password'] = 'required|min_length[4]';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'board_id'    => $board['id'],
            'category_id' => $this->resolveCategoryId($board['id'], $this->request->getPost('category_id')),
            'user_id'     => session()->get('user_id'),
            'title'       => $this->request->getPost('title'),
            'content'     => $this->sanitizeContent($this->request->getPost('content')),
            'author_name' => $isGuest
                            ? $this->request->getPost('author_name')
                            : session()->get('user_nickname'),
            'is_notice'  => $this->getUserRole() === 'admin' ? (int) $this->request->getPost('is_notice') : 0,
            'is_secret'  => (int) $this->request->getPost('is_secret'),
            'ip_address' => $this->request->getIPAddress(),
        ];

        if ($isGuest) {
            $data['author_password'] = password_hash($this->request->getPost('author_password'), PASSWORD_DEFAULT);
        }

        // 파일 사전 검증 (DB 저장 전)
        $multiFiles = $this->request->getFileMultiple('attachments');
        $hasFiles   = $multiFiles && ($board['allow_file'] || $board['allow_image']);
        if ($hasFiles) {
            $fileErrors = $this->uploader->validateFiles($multiFiles);
            if ($fileErrors !== []) {
                return redirect()->back()->withInput()->with('errors', $fileErrors);
            }
        }

        $postId = $this->postModel->insert($data);

        // 파일 저장 (검증 통과 후 디스크 오류 등 예외 대비 롤백)
        if ($hasFiles) {
            $upload = $this->uploader->savePostFiles($postId, $multiFiles);
            if (! empty($upload['errors'])) {
                $this->postModel->delete($postId, true);

                return redirect()->back()->withInput()->with('errors', $upload['errors']);
            }
        }

        // 공개(비밀글 아님 · guest 열람 게시판) 글만 IndexNow 즉시 제출
        if (! $data['is_secret'] && $board['read_permission'] === 'guest') {
            service('indexnow')->submit([base_url("board/{$boardSlug}/{$postId}")]);
        }

        return redirect()->to("/board/{$boardSlug}/{$postId}")->with('success', '게시글이 등록되었습니다.');
    }

    // ─── 수정 ───────────────────────────────────────────────────────────────

    public function edit(string $boardSlug, int $postId): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        $post  = $this->postModel->getDetail($postId);

        if (! $board || ! $post) {
            throw PageNotFoundException::forPageNotFound();
        }

        $userId = session()->get('user_id');
        $role   = $this->getUserRole();

        if ($role === 'admin' || ($userId && $post['user_id'] === $userId)) {
            // 관리자 또는 본인: 바로 수정 폼
        } elseif (! $userId && $post['author_password']) {
            // 비회원 게시글: 세션 인증 토큰 없으면 비밀번호 재확인
            if (! session()->get('edit_auth_' . $postId)) {
                return redirect()->back()->with('error', '수정하려면 비밀번호 확인이 필요합니다.');
            }
        } else {
            return redirect()->back()->with('error', '수정 권한이 없습니다.');
        }

        $files = $this->fileModel->getByPost($postId);

        return $this->render('board/write', [
            'board'      => $board,
            'post'       => $post,
            'files'      => $files,
            'categories' => $this->categoryModel->getByBoard($board['id']),
        ]);
    }

    // ─── 비회원 비밀번호 인증 → 세션 토큰 발급 ─────────────────────────────────

    public function guestVerify(string $boardSlug, int $postId): ResponseInterface|string
    {
        $post = $this->postModel->find($postId);
        if (! $post || ! $post['author_password']) {
            return redirect()->back()->with('error', '잘못된 요청입니다.');
        }

        $inputPw = $this->request->getPost('author_password');
        if (! $inputPw || ! password_verify($inputPw, (string) $post['author_password'])) {
            return redirect()->back()->with('error', '비밀번호가 틀렸습니다.');
        }

        session()->set('edit_auth_' . $postId, true);

        return redirect()->to("/board/{$boardSlug}/{$postId}/edit");
    }

    public function update(string $boardSlug, int $postId): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        $post  = $this->postModel->find($postId);

        if (! $board || ! $post || ! $this->canEditPost($post)) {
            return redirect()->back()->with('error', '권한이 없습니다.');
        }

        // 파일 사전 검증 (수정 저장 전)
        $multiFiles = $this->request->getFileMultiple('attachments');
        if ($multiFiles) {
            $fileErrors = $this->uploader->validateFiles($multiFiles);
            if ($fileErrors !== []) {
                return redirect()->back()->withInput()->with('errors', $fileErrors);
            }
        }

        $this->postModel->update($postId, [
            'category_id' => $this->resolveCategoryId($board['id'], $this->request->getPost('category_id')),
            'title'       => $this->request->getPost('title'),
            'content'     => $this->sanitizeContent($this->request->getPost('content')),
            'is_notice'   => $this->getUserRole() === 'admin' ? (int) $this->request->getPost('is_notice') : $post['is_notice'],
            'is_secret'   => (int) $this->request->getPost('is_secret'),
        ]);

        // 파일 추가 업로드
        if ($multiFiles) {
            $upload = $this->uploader->savePostFiles($postId, $multiFiles);
            if (! empty($upload['errors'])) {
                return redirect()->back()->with('errors', $upload['errors']);
            }
        }

        // 선택 파일 삭제
        $deleteFileIds = $this->request->getPost('delete_files') ?? [];

        foreach ($deleteFileIds as $fileId) {
            $file = $this->fileModel->find((int) $fileId);
            if ($file && $file['post_id'] === $postId) {
                $this->uploader->deleteFile($file);
            }
        }

        session()->remove('edit_auth_' . $postId);

        return redirect()->to("/board/{$boardSlug}/{$postId}")->with('success', '수정되었습니다.');
    }

    // ─── 삭제 ───────────────────────────────────────────────────────────────

    public function delete(string $boardSlug, int $postId): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        $post  = $this->postModel->find($postId);

        if (! $board || ! $post || ! $this->canEditPost($post)) {
            return redirect()->back()->with('error', '권한이 없습니다.');
        }

        $this->fileModel->deleteByPost($postId);
        $this->postModel->delete($postId);
        session()->remove('edit_auth_' . $postId);

        return redirect()->to("/board/{$boardSlug}")->with('success', '삭제되었습니다.');
    }

    // ─── 파일 다운로드 ───────────────────────────────────────────────────────

    public function download(int $fileId): ResponseInterface|string
    {
        $file = $this->fileModel->find($fileId);
        if (! $file) {
            return redirect()->back()->with('error', '파일을 찾을 수 없습니다.');
        }

        $fullPath = FCPATH . $file['file_path'];
        if (! file_exists($fullPath)) {
            return redirect()->back()->with('error', '파일이 존재하지 않습니다.');
        }

        $this->fileModel->incrementDownload($fileId);

        return $this->response
            ->download($fullPath, null)
            ->setFileName($file['original_name']);
    }

    // ─── 댓글 ───────────────────────────────────────────────────────────────

    public function commentStore(string $boardSlug, int $postId): ResponseInterface|string
    {
        $board = $this->boardModel->getBySlug($boardSlug);
        if (! $board || ! $this->checkPermission($board['write_permission'])) {
            return redirect()->back()->with('error', '권한이 없습니다.');
        }

        $isGuest = ! session()->get('user_id');
        $rules   = ['content' => 'required'];
        if ($isGuest) {
            $rules['author_name']     = 'required';
            $rules['author_password'] = 'required|min_length[4]';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $this->commentModel->insert([
            'post_id'         => $postId,
            'user_id'         => session()->get('user_id'),
            'content'         => $this->request->getPost('content'),
            'author_name'     => $isGuest ? $this->request->getPost('author_name') : session()->get('user_nickname'),
            'author_password' => $isGuest ? password_hash($this->request->getPost('author_password'), PASSWORD_DEFAULT) : null,
            'ip_address'      => $this->request->getIPAddress(),
        ]);

        return redirect()->to("/board/{$boardSlug}/{$postId}#comments")->with('success', '댓글이 등록되었습니다.');
    }

    public function commentDelete(string $boardSlug, int $postId, int $commentId): ResponseInterface|string
    {
        $comment = $this->commentModel->find($commentId);
        if (! $comment) {
            return redirect()->back()->with('error', '댓글을 찾을 수 없습니다.');
        }

        $role   = $this->getUserRole();
        $userId = session()->get('user_id');

        $canDelete = $role === 'admin'
            || ($userId && $comment['user_id'] === $userId);

        if (! $canDelete) {
            return redirect()->back()->with('error', '삭제 권한이 없습니다.');
        }

        $this->commentModel->delete($commentId);

        return redirect()->to("/board/{$boardSlug}/{$postId}#comments");
    }

    // ─── 에디터 이미지 업로드 ───────────────────────────────────────────────

    public function imageUpload(): ResponseInterface|string
    {
        if (! session()->get('user_id')) {
            return $this->response->setJSON(['error' => '로그인이 필요합니다.'])->setStatusCode(403);
        }

        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return $this->response->setJSON(['error' => '파일을 찾을 수 없습니다.'])->setStatusCode(400);
        }

        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return $this->response->setJSON(['error' => '이미지 파일만 허용됩니다.'])->setStatusCode(400);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->response->setJSON(['error' => '파일 크기는 10MB 이하여야 합니다.'])->setStatusCode(400);
        }

        $uploadPath = FCPATH . 'uploads/board/images/' . date('Y/m');
        if (! is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($uploadPath, $storedName);

        return $this->response->setJSON([
            'location' => base_url('uploads/board/images/' . date('Y/m') . '/' . $storedName),
        ]);
    }

    // ─── 내부 헬퍼 ──────────────────────────────────────────────────────────

    /**
     * 게시글 저장용 category_id 정규화 — 해당 게시판 소속의 활성 카테고리가 아니면 null.
     */
    private function resolveCategoryId(int $boardId, ?string $rawCategoryId): ?int
    {
        $categoryId = (int) $rawCategoryId;
        if ($categoryId <= 0) {
            return null;
        }

        $category = $this->categoryModel
            ->where('board_id', $boardId)
            ->where('is_active', 1)
            ->find($categoryId);

        return $category ? $categoryId : null;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function canEditPost(array $post): bool
    {
        $role   = $this->getUserRole();
        $userId = session()->get('user_id');

        if ($role === 'admin') {
            return true;
        }
        if ($userId && $post['user_id'] === $userId) {
            return true;
        }

        // 비회원: 세션 인증 토큰 또는 POST 비밀번호 검증
        if (! $userId && $post['author_password']) {
            if (session()->get('edit_auth_' . $post['id'])) {
                return true;
            }
            $inputPw = $this->request->getPost('author_password');

            return $inputPw && password_verify($inputPw, (string) $post['author_password']);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function canAccessSecret(array $post): bool
    {
        $role   = $this->getUserRole();
        $userId = session()->get('user_id');

        return $role === 'admin' || ($userId && $post['user_id'] === $userId);
    }

    /**
     * 에디터 HTML에서 XSS 위험 패턴 제거
     * 완전한 sanitize는 HTMLPurifier 도입 권장 (composer require ezyang/htmlpurifier)
     */
    private function sanitizeContent(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // <script> 블록 제거
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        // on* 이벤트 핸들러 속성 제거 (onclick, onload, onerror 등)
        $html = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $html);

        // javascript: / vbscript: / data: 링크 제거
        $html = preg_replace('/\b(javascript|vbscript|data)\s*:/i', '', (string) $html);

        // <iframe>, <object>, <embed>, <form> 태그 제거
        return preg_replace('/<\/?(iframe|object|embed|form)\b[^>]*>/i', '', (string) $html);
    }
}
