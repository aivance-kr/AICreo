<?php
/**
 * 문의 폼 추가 항목. 입력 이름은 cf[항목ID] (파일은 cf_file[항목ID]).
 *
 * @var list<array<string, mixed>> $inquiryFields
 * @var array<string, string>      $errors
 */
use App\Libraries\InquiryForm;

foreach ($inquiryFields as $field):
    $id       = (int) $field['id'];
    $type     = (string) $field['field_type'];
    $required = (int) $field['is_required'] === 1;
    $options  = InquiryForm::parseOptions((string) ($field['options'] ?? ''));
    $error    = $errors['cf_' . $id] ?? null;
    $inputId  = 'inquiry-cf-' . $id;
    $invalid  = $error !== null ? ' is-invalid' : '';
    $old      = old('cf')[$id] ?? null;
    ?>
        <div class="col-12">
            <?php if ($type === 'checkbox' && $options === []): ?>
            <div class="form-check">
                <input type="checkbox" class="form-check-input<?= $invalid ?>" id="<?= $inputId ?>" name="cf[<?= $id ?>]" value="1"
                       <?= $old === '1' ? 'checked' : '' ?> <?= $required ? 'required' : '' ?>>
                <label class="form-check-label" for="<?= $inputId ?>"><?= esc($field['label']) ?><?= $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
            </div>
            <?php elseif ($type === 'checkbox'): ?>
            <fieldset>
                <legend class="form-label fs-6"><?= esc($field['label']) ?><?= $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></legend>
                <?php foreach ($options as $i => $option): ?>
                <div class="form-check form-check-inline">
                    <input type="checkbox" class="form-check-input<?= $invalid ?>" id="<?= $inputId . '-' . $i ?>" name="cf[<?= $id ?>][]"
                           value="<?= esc($option) ?>" <?= is_array($old) && in_array($option, $old, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= $inputId . '-' . $i ?>"><?= esc($option) ?></label>
                </div>
                <?php endforeach; ?>
            </fieldset>
            <?php else: ?>
            <label class="form-label" for="<?= $inputId ?>"><?= esc($field['label']) ?><?= $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
                <?php if ($type === 'textarea'): ?>
            <textarea id="<?= $inputId ?>" name="cf[<?= $id ?>]" rows="4" class="form-control<?= $invalid ?>" <?= $required ? 'required' : '' ?>><?= esc(is_string($old) ? $old : '') ?></textarea>
                <?php elseif ($type === 'select'): ?>
            <select id="<?= $inputId ?>" name="cf[<?= $id ?>]" class="form-select<?= $invalid ?>" <?= $required ? 'required' : '' ?>>
                <option value=""><?= esc(lang('Site.selectOption')) ?></option>
                    <?php foreach ($options as $option): ?>
                <option value="<?= esc($option) ?>" <?= $old === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                    <?php endforeach; ?>
            </select>
                <?php elseif ($type === 'file'): ?>
            <input type="file" id="<?= $inputId ?>" name="cf_file[<?= $id ?>]" class="form-control<?= $invalid ?>" <?= $required ? 'required' : '' ?>>
            <div class="form-text"><?= esc(lang('Site.fileMaxSize')) ?></div>
                <?php else: ?>
            <input type="text" id="<?= $inputId ?>" name="cf[<?= $id ?>]" class="form-control<?= $invalid ?>"
                   value="<?= esc(is_string($old) ? $old : '') ?>" maxlength="255" <?= $required ? 'required' : '' ?>>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($error !== null): ?><div class="invalid-feedback d-block"><?= esc($error) ?></div><?php endif; ?>
        </div>
<?php endforeach; ?>
