<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\TranslationManager;
use App\Models\AdminLogModel;
use App\Models\PageModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\I18n;

class PageManagerController extends BaseController
{
    private readonly PageModel $pageModel;

    public function __construct()
    {
        $this->pageModel = new PageModel();
    }

    public function index(): string
    {
        $locale = (string) $this->request->getGet('locale');
        $locale = in_array($locale, config(I18n::class)->locales(), true) ? $locale : null;

        if ($locale !== null) {
            $this->pageModel->where('locale', $locale);
        }

        return $this->render('admin/pages/list', [
            'pages'         => $this->pageModel->orderBy('sort_order')->findAll(),
            'locales'       => config(I18n::class)->locales(),
            'currentLocale' => $locale,
        ]);
    }

    public function create(): string
    {
        return $this->render('admin/pages/form', ['page' => null, 'translationRows' => [], 'locales' => config(I18n::class)->locales()]);
    }

    public function store(): ResponseInterface|string
    {
        $locales = config(I18n::class)->locales();
        $rules   = [
            // 언어 코드는 /en/... 접두어와 겹치므로 슬러그로 쓸 수 없다. 유일성은 언어별로 아래에서 본다
            'slug'   => 'required|alpha_dash|not_in_list[' . implode(',', $locales) . ']',
            'title'  => 'required',
            'locale' => 'permit_empty|in_list[' . implode(',', $locales) . ']',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $locale = (string) ($this->request->getPost('locale') ?: config(I18n::class)->defaultLocale);
        if ($this->pageModel->withDeleted()->where('slug', $this->request->getPost('slug'))->where('locale', $locale)->first() !== null) {
            return redirect()->back()->withInput()->with('errors', ['같은 언어에 이미 있는 슬러그입니다.']);
        }

        $data           = $this->collectData();
        $data['locale'] = $locale;
        $pageId         = (int) $this->pageModel->insert($data);
        $this->pageModel->update($pageId, ['translation_group_id' => $pageId]);
        model(AdminLogModel::class)->record('create', 'page', $pageId, (string) $data['title']);

        if (($data['status'] ?? '') === 'published') {
            service('indexnow')->submit([locale_url($data['slug'], $locale)]);
        }

        return redirect()->to('/admin/pages')->with('success', '페이지가 생성되었습니다.');
    }

    public function edit(int $id): string
    {
        return $this->render('admin/pages/form', [
            'page'            => $this->pageModel->find($id),
            'translationRows' => config(I18n::class)->prefixedLocales() === [] ? [] : (new TranslationManager())->pageRows($id),
            'locales'         => config(I18n::class)->locales(),
        ]);
    }

    public function update(int $id): ResponseInterface|string
    {
        $data = $this->collectData(isUpdate: true);
        $this->pageModel->update($id, $data);
        model(AdminLogModel::class)->record('update', 'page', $id, (string) $data['title']);

        $page = $this->pageModel->find($id);
        if ($page && ($data['status'] ?? '') === 'published') {
            service('indexnow')->submit([base_url($page['slug'])]);
        }

        return redirect()->to('/admin/pages')->with('success', '저장되었습니다.');
    }

    /**
     * 페이지의 번역본(초안 복사본)을 만들고 그 수정 화면으로 보낸다.
     */
    public function translate(int $id, string $locale): ResponseInterface
    {
        $newId = (new TranslationManager())->createPageTranslation($id, $locale);

        if ($newId === null) {
            return redirect()->back()->with('error', '번역본을 만들 수 없습니다. (이미 있거나 사용하지 않는 언어)');
        }

        model(AdminLogModel::class)->record('create', 'page', $newId, "번역({$locale}) 원본 #{$id}");

        return redirect()->to('/admin/pages/' . $newId . '/edit')->with('success', '번역본을 만들었습니다. 초안 상태이니 번역 후 공개하세요.');
    }

    public function delete(int $id): ResponseInterface|string
    {
        $page = $this->pageModel->find($id);
        $this->pageModel->delete($id);
        model(AdminLogModel::class)->record('delete', 'page', $id, (string) ($page['title'] ?? ''));

        return redirect()->to('/admin/pages')->with('success', '휴지통으로 이동했습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    private function collectData(bool $isUpdate = false): array
    {
        $data = [
            'title'      => $this->request->getPost('title'),
            'content'    => $this->request->getPost('content'),
            'layout'     => $this->request->getPost('layout') ?: 'default',
            'meta_title' => $this->request->getPost('meta_title'),
            'meta_desc'  => $this->request->getPost('meta_desc'),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'status'     => $this->request->getPost('status') ?: 'published',
        ];
        if (! $isUpdate) {
            $data['slug'] = $this->request->getPost('slug');
        }

        return $data;
    }
}
