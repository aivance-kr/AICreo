<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '플러그인' ?>
<?= $this->section('content') ?>

<?php
$statusLabels = ['active' => ['사용', 'bg-success'], 'inactive' => ['꺼짐', 'bg-secondary'], 'missing' => ['파일 없음', 'bg-danger']];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <span class="text-muted small">총 <?= number_format(count($plugins)) ?>개</span>
    <form method="post" action="/admin/plugins/sync">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary">플러그인 폴더 다시 읽기</button>
    </form>
</div>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack align-middle">
            <thead class="table-light">
                <tr>
                    <th scope="col">이름</th>
                    <th scope="col">키</th>
                    <th scope="col">버전</th>
                    <th scope="col">상태</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plugins)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">설치된 플러그인이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($plugins as $plugin): ?>
                <?php [$label, $badge] = $statusLabels[$plugin['status']] ?? [$plugin['status'], 'bg-secondary']; ?>
                <?php $base = '/admin/plugins/' . rawurlencode((string) $plugin['plugin_key']); ?>
                <tr>
                    <td data-label="이름"><?= esc($plugin['name']) ?></td>
                    <td data-label="키" class="font-monospace small"><?= esc($plugin['plugin_key']) ?></td>
                    <td data-label="버전"><?= esc($plugin['version']) ?></td>
                    <td data-label="상태"><span class="badge <?= $badge ?>"><?= esc($label) ?></span></td>
                    <td data-label="" class="cell-actions text-nowrap">
                        <?php if (! empty($plugin['has_settings'])): ?>
                        <a href="<?= $base ?>/settings" class="btn btn-sm btn-outline-primary">설정</a>
                        <?php endif; ?>
                        <?php if ($plugin['status'] === 'active'): ?>
                        <form method="post" action="<?= $base ?>/deactivate" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">비활성화</button>
                        </form>
                        <?php elseif ($plugin['status'] === 'inactive'): ?>
                        <form method="post" action="<?= $base ?>/activate" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary">활성화</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="<?= $base ?>/uninstall" class="d-inline"
                              onsubmit="return confirm('플러그인을 제거할까요? 이 플러그인의 테이블과 데이터도 함께 삭제되며 되돌릴 수 없습니다.')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">제거</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 class="h6">ZIP 업로드</h2>
        <?php if ($allowUpload): ?>
        <p class="small text-muted">ZIP 에는 실행되는 PHP 코드가 들어 있습니다. 신뢰하는 곳에서 받은 플러그인만 올리세요. 설치 직후에는 꺼진 상태입니다.</p>
        <form method="post" action="/admin/plugins/upload" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
            <?= csrf_field() ?>
            <label class="visually-hidden" for="plugin-zip">플러그인 ZIP 파일</label>
            <input type="file" name="plugin_zip" id="plugin-zip" class="form-control form-control-sm w-auto" accept=".zip" required>
            <button type="submit" class="btn btn-sm btn-primary">설치</button>
        </form>
        <?php else: ?>
        <p class="small text-muted mb-0">
            ZIP 업로드는 꺼져 있습니다. 서버에 임의 PHP 를 올리는 기능이라 기본으로 막아 두었습니다.
            필요하면 서버 <code>.env</code> 에 <code>plugins.allowUpload = true</code> 를 넣어 켭니다.
            폴더를 <code>app/Plugins/</code> 에 직접 두고 "다시 읽기"를 눌러도 됩니다.
        </p>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
