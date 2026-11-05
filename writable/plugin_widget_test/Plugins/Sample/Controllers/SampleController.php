<?php

declare(strict_types=1);

namespace Plugins\Sample\Controllers;

use App\Controllers\BaseController;
use Plugins\Sample\Models\SampleNoteModel;

class SampleController extends BaseController
{
    public function index(): string
    {
        return $this->render('\Plugins\Sample\Views\index', [
            'page'  => ['title' => '샘플 플러그인', 'meta_title' => '샘플 플러그인', 'meta_desc' => '', 'noindex' => true],
            'notes' => model(SampleNoteModel::class)->latest(),
        ]);
    }
}
