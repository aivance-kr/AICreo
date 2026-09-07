<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;
use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

class SettingController extends BaseController
{
    private readonly SettingModel $settingModel;

    public function __construct()
    {
        $this->settingModel = new SettingModel();
    }

    public function index(string $group = 'general'): string
    {
        if ($group === 'oauth') {
            return $this->render('admin/settings/oauth', ['group' => 'oauth']);
        }

        if ($group === 'theme') {
            return $this->render('admin/settings/theme', [
                'group'           => 'theme',
                'activeTheme'     => $this->settingModel->getAllAsMap()['active_theme'] ?? 'default',
                'availableThemes' => $this->scanThemes(),
            ]);
        }

        $allowed = ['general', 'contact', 'sns', 'seo', 'footer'];
        if (! in_array($group, $allowed, true)) {
            $group = 'general';
        }

        return $this->render('admin/settings/index', [
            'group'    => $group,
            'settings' => $this->settingModel->getGroup($group),
        ]);
    }

    public function uploadTheme(): ResponseInterface|string
    {
        $file = $this->request->getFile('theme_zip');

        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return redirect()->back()->with('error', '파일 업로드에 실패했습니다.');
        }

        if (strtolower($file->getClientExtension()) !== 'zip') {
            return redirect()->back()->with('error', 'ZIP 파일만 업로드 가능합니다.');
        }

        // 파일명에서 테마 이름 추출 (영소문자·숫자·하이픈·언더스코어만 허용)
        $themeName = strtolower(pathinfo($file->getClientName(), PATHINFO_FILENAME));
        $themeName = trim((string) preg_replace('/[^a-z0-9\-_]+/', '-', $themeName), '-_');

        if (in_array($themeName, ['', '0', 'default'], true)) {
            return redirect()->back()->with('error', "'{$themeName}'은 사용할 수 없는 테마 이름입니다.");
        }

        // ZIP 열기
        $zip = new ZipArchive();
        if ($zip->open($file->getTempName()) !== true) {
            return redirect()->back()->with('error', 'ZIP 파일을 열 수 없습니다.');
        }

        // 필수 파일 및 보안 검사
        $required       = ['views/layouts/main.php', 'public/css/style.css'];
        $allowedViewExt = ['php'];
        $allowedPubExt  = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
            'woff', 'woff2', 'ttf', 'eot', 'ico', 'map', 'json'];
        $found = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            // Zip-slip 방지
            if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_starts_with($entry, '\\')) {
                $zip->close();

                return redirect()->back()->with('error', "보안 위험: 잘못된 경로 포함 ({$entry})");
            }

            if (str_ends_with($entry, '/')) {
                continue;
            } // 디렉토리 엔트리 skip

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

            if (str_starts_with($entry, 'views/')) {
                if (! in_array($ext, $allowedViewExt, true)) {
                    $zip->close();

                    return redirect()->back()->with('error', "views/ 내 허용되지 않는 파일 형식: {$entry}");
                }
            } elseif (str_starts_with($entry, 'public/')) {
                if (! in_array($ext, $allowedPubExt, true)) {
                    $zip->close();

                    return redirect()->back()->with('error', "public/ 내 허용되지 않는 파일 형식: {$entry}");
                }
            }
            // views/, public/ 외 파일은 무시

            if (in_array($entry, $required, true)) {
                $found[] = $entry;
            }
        }

        $missing = array_diff($required, $found);
        if ($missing !== []) {
            $zip->close();

            return redirect()->back()->with('error', '필수 파일 누락: ' . implode(', ', $missing));
        }

        // 임시 디렉토리에 압축 해제
        $tempDir = rtrim(WRITEPATH, '/') . '/themes_tmp/' . uniqid('theme_', true);
        if (! mkdir($tempDir, 0755, true)) {
            $zip->close();

            return redirect()->back()->with('error', '임시 디렉토리 생성 실패');
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $isUpdate = is_dir(APPPATH . "Views/themes/{$themeName}");

        try {
            if (is_dir("{$tempDir}/views")) {
                $this->copyDirectory("{$tempDir}/views", APPPATH . "Views/themes/{$themeName}");
            }
            if (is_dir("{$tempDir}/public")) {
                $this->copyDirectory("{$tempDir}/public", FCPATH . "themes/{$themeName}");
            }
        } catch (Throwable $e) {
            $this->deleteDirectory($tempDir);

            return redirect()->back()->with('error', '파일 복사 실패: ' . $e->getMessage());
        }

        $this->deleteDirectory($tempDir);

        $action = $isUpdate ? '업데이트' : '설치';

        return redirect()->to('/admin/settings/theme')
            ->with('success', "테마 '{$themeName}'이(가) {$action}되었습니다.");
    }

    private function copyDirectory(string $src, string $dest): void
    {
        if (! is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        ) as $item) {
            $target = $dest . '/' . substr((string) $item->getPathname(), strlen($src) + 1);
            $item->isDir() ? (is_dir($target) ?: mkdir($target, 0755, true))
                           : copy($item->getPathname(), $target);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    public function update(string $group = 'general'): ResponseInterface|string
    {
        $postData = $this->request->getPost();
        unset($postData[csrf_token()]);

        if ($group === 'theme') {
            $theme = $postData['active_theme'] ?? 'default';
            $this->settingModel->saveSettings(['active_theme' => $theme]);

            return redirect()->to('/admin/settings/theme')->with('success', "테마가 '{$theme}'으로 변경되었습니다.");
        }

        if (array_key_exists('blog_home_post_limit', $postData)) {
            $postData['blog_home_post_limit'] = (string) min(100, max(1, (int) $postData['blog_home_post_limit']));
        }

        $this->settingModel->saveSettings($postData);

        return redirect()->to("/admin/settings/{$group}")->with('success', '설정이 저장되었습니다.');
    }

    /**
     * app/Views/themes/ 하위 폴더를 스캔해 테마 목록 반환
     *
     * @return array<string, array<string, mixed>>
     */
    private function scanThemes(): array
    {
        $base   = APPPATH . 'Views/themes/';
        $themes = [];

        if (! is_dir($base)) {
            return $themes;
        }

        foreach (new DirectoryIterator($base) as $item) {
            if ($item->isDir() && ! $item->isDot()) {
                $name          = $item->getFilename();
                $themes[$name] = [
                    'name'       => $name,
                    'label'      => ucfirst($name),
                    'has_css'    => is_file(FCPATH . "themes/{$name}/css/style.css"),
                    'has_layout' => is_file(APPPATH . "Views/themes/{$name}/layouts/main.php"),
                ];
            }
        }

        ksort($themes);

        return $themes;
    }
}
