<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * 문의 폼 추가 항목의 서버 검증·정리. 입력은 항목 정의(inquiry_fields 행)와 제출 값이다.
 * 결과는 inquiries.extra_data 에 JSON 으로 저장할 [{label,type,value,file?}] 목록이다.
 * 정의가 나중에 바뀌거나 지워져도 제출 당시 라벨을 함께 저장해 두므로 기존 문의는 그대로 읽힌다.
 */
final class InquiryForm
{
    public const FILE_MAX_BYTES = 5 * 1024 * 1024;
    public const FILE_EXTS      = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'hwp'];
    private const EXT_MIME      = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
    ];
    private const TEXT_MAX     = 255;
    private const TEXTAREA_MAX = 5000;

    /**
     * 첨부 파일 저장 폴더. 웹 루트 밖이라 관리자 다운로드 라우트로만 내려받는다.
     */
    public static function uploadDir(): string
    {
        return WRITEPATH . 'uploads/inquiries';
    }

    /**
     * 선택지 문자열(줄바꿈 구분)을 목록으로 바꾼다.
     *
     * @return list<string>
     */
    public static function parseOptions(?string $raw): array
    {
        $options = [];

        foreach (preg_split('/\R/', (string) $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) <= 100 && ! in_array($line, $options, true)) {
                $options[] = $line;
            }
        }

        return $options;
    }

    /**
     * 제출 값을 검증하고 저장용 목록을 만든다. 오류가 하나라도 있으면 파일은 저장하지 않는다.
     *
     * @param list<array<string, mixed>>    $fields    사용 중인 항목 정의
     * @param array<array-key, mixed>       $input     cf[항목ID] 제출 값
     * @param array<int, UploadedFile|null> $files     항목ID => 업로드 파일
     * @param string                        $uploadDir 파일 저장 폴더(절대 경로)
     *
     * @return array{errors: array<string, string>, extra: list<array<string, string>>}
     */
    public function collect(array $fields, array $input, array $files, string $uploadDir): array
    {
        $errors  = [];
        $extra   = [];
        $pending = [];

        foreach ($fields as $field) {
            $id       = (int) $field['id'];
            $label    = (string) $field['label'];
            $type     = (string) $field['field_type'];
            $required = (int) $field['is_required'] === 1;
            $key      = 'cf_' . $id;
            $raw      = $input[$id] ?? null;

            if ($type === 'file') {
                $file = $files[$id] ?? null;
                if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                    if ($required) {
                        $errors[$key] = $label . ' 파일을 첨부해 주세요.';
                    }

                    continue;
                }
                $fileError = $this->fileError($file);
                if ($fileError !== null) {
                    $errors[$key] = $label . ': ' . $fileError;

                    continue;
                }
                $stored    = bin2hex(random_bytes(16)) . '.' . strtolower($file->getClientExtension());
                $pending[] = [$file, $stored];
                $extra[]   = ['label' => $label, 'type' => $type, 'value' => mb_substr($file->getClientName(), 0, 200), 'file' => $stored];

                continue;
            }

            $value = $this->normalize($type, $raw, self::parseOptions((string) ($field['options'] ?? '')), $errors, $key, $label);
            if ($value === '') {
                if ($required && ! isset($errors[$key])) {
                    $errors[$key] = $label . ' 항목을 입력해 주세요.';
                }

                continue;
            }
            $extra[] = ['label' => $label, 'type' => $type, 'value' => $value];
        }

        if ($errors === [] && $pending !== []) {
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($pending as [$file, $stored]) {
                $file->move($uploadDir, $stored);
            }
        }

        return ['errors' => $errors, 'extra' => $errors === [] ? $extra : []];
    }

    /**
     * @param list<string>          $options
     * @param array<string, string> $errors
     */
    private function normalize(string $type, mixed $raw, array $options, array &$errors, string $key, string $label): string
    {
        switch ($type) {
            case 'select':
                $value = is_string($raw) ? trim($raw) : '';
                if ($value !== '' && ! in_array($value, $options, true)) {
                    $errors[$key] = $label . ': 올바른 선택지가 아닙니다.';

                    return '';
                }

                return $value;

            case 'checkbox':
                if ($options === []) {
                    return $raw === '1' ? '예' : '';
                }
                $picked = is_array($raw) ? $raw : [];
                $values = [];

                foreach ($picked as $item) {
                    if (! is_string($item) || ! in_array($item, $options, true)) {
                        $errors[$key] = $label . ': 올바른 선택지가 아닙니다.';

                        return '';
                    }
                    $values[$item] = $item;
                }

                return implode(', ', array_values($values));

            default:
                if (! is_string($raw)) {
                    return '';
                }
                $value = trim($raw);
                $max   = $type === 'textarea' ? self::TEXTAREA_MAX : self::TEXT_MAX;
                if (mb_strlen($value) > $max) {
                    $errors[$key] = $label . ': ' . $max . '자 이내로 입력해 주세요.';

                    return '';
                }

                return $value;
        }
    }

    private function fileError(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return '업로드에 실패했습니다.';
        }
        if (! in_array(strtolower($file->getClientExtension()), self::FILE_EXTS, true)) {
            return '허용되지 않는 파일 형식입니다.';
        }
        // 확장자만 믿지 않고 실제 내용(MIME)이 확장자와 맞는지 본다
        $expected = self::EXT_MIME[strtolower($file->getClientExtension())] ?? null;
        if ($expected !== null && ! in_array($file->getMimeType(), $expected, true)) {
            return '파일 내용이 확장자와 일치하지 않습니다.';
        }
        if ($file->getSize() > self::FILE_MAX_BYTES) {
            return '파일 크기는 5MB 이하여야 합니다.';
        }

        return null;
    }
}
