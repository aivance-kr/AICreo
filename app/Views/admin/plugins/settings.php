<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('pageTitle', $plugin['name'] . ' 설정') ?>
<?= $this->section('content') ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="/admin/plugins/<?= rawurlencode((string) $plugin['plugin_key']) ?>/settings">
            <?= csrf_field() ?>
            <?php foreach ($definitions as $definition): ?>
            <div class="mb-3">
                <label class="form-label" for="plugin-setting-<?= esc($definition->key) ?>"><?= esc($definition->label) ?></label>
                <input type="text" class="form-control" id="plugin-setting-<?= esc($definition->key) ?>"
                       name="<?= esc($definition->key) ?>" maxlength="<?= \App\Libraries\Plugin\PluginSetting::MAX_LENGTH ?>"
                       value="<?= esc($values[$definition->key] ?? '') ?>"
                       placeholder="<?= esc($definition->default) ?>">
                <?php if ($definition->help !== ''): ?>
                <div class="form-text"><?= esc($definition->help) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">저장</button>
                <a href="/admin/plugins" class="btn btn-outline-secondary">목록</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
