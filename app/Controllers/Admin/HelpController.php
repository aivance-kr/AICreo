<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\MarkdownRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;

class HelpController extends BaseController
{
    public function index(): string
    {
        $path = ROOTPATH . 'docs/manual.md';

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        // 표(GFM)·목차 링크(#anchor)·헤딩 한 단계 낮추기는 MarkdownRenderer 가 맡는다.
        $html = MarkdownRenderer::toHtml((string) file_get_contents($path));

        return $this->render('admin/help/index', [
            'manualHtml' => $html,
        ]);
    }
}
