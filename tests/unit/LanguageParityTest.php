<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * 공개 화면 고정 문구: ko/en 언어 파일의 키 불일치·빈 번역·영어 값에 남은 한글을 잡는다.
 *
 * @internal
 */
final class LanguageParityTest extends CIUnitTestCase
{
    private const FILES_DIR = APPPATH . 'Language/';

    /**
     * 한국어 기준 언어 파일 이름(Validation 은 프레임워크 오버라이드라 제외).
     *
     * @return list<string>
     */
    private function koFiles(): array
    {
        $names = [];

        foreach (glob(self::FILES_DIR . 'ko/*.php') ?: [] as $path) {
            $name = basename($path, '.php');
            if ($name !== 'Validation') {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $locale, string $name): array
    {
        $path = self::FILES_DIR . $locale . '/' . $name . '.php';
        $this->assertFileExists($path, "{$locale}/{$name}.php 없음");

        /** @var array<string, mixed> $data */
        $data = require $path;

        return $data;
    }

    public function testEnglishFileExistsForEveryKoreanFile(): void
    {
        $this->assertNotEmpty($this->koFiles());

        foreach ($this->koFiles() as $name) {
            $this->assertFileExists(self::FILES_DIR . 'en/' . $name . '.php', "en/{$name}.php 없음");
        }
    }

    public function testKeysMatchBetweenKoAndEn(): void
    {
        foreach ($this->koFiles() as $name) {
            $ko = array_keys($this->load('ko', $name));
            $en = array_keys($this->load('en', $name));

            $this->assertSame([], array_values(array_diff($ko, $en)), "{$name}: en 에 없는 키");
            $this->assertSame([], array_values(array_diff($en, $ko)), "{$name}: ko 에 없는 키");
        }
    }

    public function testEnglishValuesAreNotKoreanOrEmpty(): void
    {
        foreach ($this->koFiles() as $name) {
            foreach ($this->load('en', $name) as $key => $value) {
                $this->assertIsString($value, "{$name}.{$key} 는 문자열이어야 한다");
                $this->assertNotSame('', trim($value), "{$name}.{$key} 영어 값이 비어 있다");
                $this->assertSame(0, preg_match('/[\x{AC00}-\x{D7A3}]/u', $value), "{$name}.{$key} 영어 값에 한글이 남아 있다");
            }
        }
    }

    /**
     * 공개 화면 뷰에는 한글 고정 문구가 남으면 안 된다(주석 제외). 문구는 lang() 으로 옮긴다.
     */
    public function testPublicViewsHaveNoHardcodedKorean(): void
    {
        // 관리자·오류 화면과 한국어 전용 소개 페이지는 번역 대상이 아니다.
        $skip = ['admin', 'errors', 'pages/_aicreo_intro.php'];

        $offenders = [];
        $iterator  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPPATH . 'Views', FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace(APPPATH . 'Views/', '', $file->getPathname());

            foreach ($skip as $prefix) {
                if (str_starts_with($relative, $prefix)) {
                    continue 2;
                }
            }

            $source = (string) file_get_contents($file->getPathname());
            $source = (string) preg_replace('/<!--.*?-->|\/\*.*?\*\/|^\s*(?:\/\/|#).*$|(?<![:\'"])\/\/[^\n]*$/ms', '', $source);
            if (preg_match('/[\x{AC00}-\x{D7A3}]/u', $source) === 1) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);
        $this->assertSame([], $offenders, "한글이 남은 공개 뷰:\n- " . implode("\n- ", $offenders));
    }
}
