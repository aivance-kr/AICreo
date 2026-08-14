<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '사이트 설정' ?>
<?= $this->section('content') ?>

<!-- 탭 -->
<ul class="nav nav-tabs mb-4">
    <?php foreach (['general' => '기본', 'contact' => '연락처', 'sns' => 'SNS', 'seo' => 'SEO', 'footer' => '푸터'] as $g => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $group === $g ? 'active' : '' ?>" href="/admin/settings/<?= $g ?>"><?= $label ?></a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item">
        <a class="nav-link" href="/admin/settings/oauth">소셜 로그인</a>
    </li>
</ul>

<div class="card border-0 shadow-sm" style="max-width:600px">
    <div class="card-body p-4">
        <form method="post" action="/admin/settings/<?= esc($group) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php
            // 조직 스키마 타입(org_type) 선택 옵션 (schema.org)
            $orgTypeOptions = [
                'Organization'        => '일반 조직 (Organization)',
                'LocalBusiness'       => '지역 사업체 (LocalBusiness)',
                'Corporation'         => '기업 (Corporation)',
                'ProfessionalService' => '전문 서비스 (ProfessionalService)',
                'Store'               => '상점 (Store)',
            ];
            ?>
            <?php foreach ($settings as $s): ?>
            <div class="mb-3">
                <?php if ($s['type'] === 'boolean'): ?>
                    <div class="form-check form-switch">
                        <input type="hidden" name="<?= esc($s['key']) ?>" value="0">
                        <input type="checkbox" class="form-check-input" role="switch"
                               id="chk_<?= esc($s['key']) ?>" name="<?= esc($s['key']) ?>" value="1"
                               <?= $s['value'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold" for="chk_<?= esc($s['key']) ?>"><?= esc($s['label']) ?></label>
                    </div>
                <?php else: ?>
                <label class="form-label small fw-semibold" for="set_<?= esc($s['key']) ?>"><?= esc($s['label']) ?></label>
                <?php if ($s['type'] === 'textarea'): ?>
                    <textarea name="<?= esc($s['key']) ?>" id="set_<?= esc($s['key']) ?>" class="form-control form-control-sm" rows="3"><?= esc($s['value']) ?></textarea>
                <?php elseif ($s['key'] === 'org_type'): ?>
                    <select name="<?= esc($s['key']) ?>" id="set_<?= esc($s['key']) ?>" class="form-select form-select-sm">
                        <?php foreach ($orgTypeOptions as $val => $label): ?>
                        <option value="<?= esc($val) ?>" <?= $s['value'] === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($s['type'] === 'image'): ?>
                    <?php if ($s['value']): ?>
                        <div class="mb-1"><img src="/<?= esc($s['value']) ?>" style="max-height:60px" class="img-thumbnail" alt="<?= esc($s['label']) ?> 현재 이미지"></div>
                    <?php endif; ?>
                    <input type="text" name="<?= esc($s['key']) ?>" id="set_<?= esc($s['key']) ?>" class="form-control form-control-sm"
                           value="<?= esc($s['value']) ?>" placeholder="uploads/media/... 경로 입력"
                           aria-describedby="help_<?= esc($s['key']) ?>">
                    <div class="form-text" id="help_<?= esc($s['key']) ?>">미디어 라이브러리에서 이미지 경로를 복사하세요.</div>
                <?php else: ?>
                    <input type="text" name="<?= esc($s['key']) ?>" id="set_<?= esc($s['key']) ?>" class="form-control form-control-sm" value="<?= esc($s['value']) ?>">
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-sm px-4">저장</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
