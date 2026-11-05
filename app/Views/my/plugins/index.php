<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
use App\Libraries\Plugin\AccessLevel;
use App\Libraries\Plugin\EntitlementStatus;
use App\Libraries\Plugin\LicenseType;
?>

<div class="container py-4">
    <h1 class="h5 mb-4 fw-bold"><?= esc(lang('MyPlugins.title')) ?></h1>

    <div class="table-responsive">
        <table class="table align-middle">
            <caption class="visually-hidden"><?= esc(lang('MyPlugins.caption')) ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?= esc(lang('MyPlugins.colPlugin')) ?></th>
                    <th scope="col"><?= esc(lang('MyPlugins.colLicense')) ?></th>
                    <th scope="col"><?= esc(lang('MyPlugins.colPrice')) ?></th>
                    <th scope="col"><?= esc(lang('MyPlugins.colStatus')) ?></th>
                    <th scope="col"><span class="visually-hidden"><?= esc(lang('MyPlugins.colManage')) ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($plugins === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= esc(lang('MyPlugins.empty')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($plugins as $plugin): ?>
                    <?php
                    $key         = (string) $plugin['plugin_key'];
                    $licenseType = LicenseType::tryFrom((string) $plugin['license_type']);
                    $entitlement = $entitlements[$key] ?? null;
                    $access      = $entitlement?->accessLevel();
                    $missing     = $missingRequirements[$key] ?? [];
                    $isRevoked   = $entitlement?->status === EntitlementStatus::Revoked;
                    $canCancel   = $licenseType === LicenseType::Subscription
                        && $entitlement !== null && ! $entitlement->isCancelled() && ! $isRevoked;
                    $canActivate = $licenseType === LicenseType::Credit
                        && $access !== AccessLevel::Full && $missing === [] && ! $isRevoked;
                    ?>
                <tr>
                    <td><?= esc($plugin['name']) ?></td>
                    <td><?= esc($licenseType?->label() ?? (string) $plugin['license_type']) ?></td>
                    <td>
                        <?php if ($plugin['price_onetime'] !== null): ?>
                            <?= esc(number_format((int) $plugin['price_onetime'])) ?><?= esc(lang('MyPlugins.won')) ?>
                        <?php elseif ($plugin['price_subscription'] !== null): ?>
                            <?= esc(lang('MyPlugins.monthly')) ?> <?= esc(number_format((int) $plugin['price_subscription'])) ?><?= esc(lang('MyPlugins.won')) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entitlement === null): ?>
                            <span class="text-muted"><?= esc(lang('MyPlugins.notOwned')) ?></span>
                            <?php if ($missing !== []): ?>
                                <div class="small text-muted"><?= esc(implode(', ', $missing)) ?> <?= esc(lang('MyPlugins.requires')) ?></div>
                            <?php endif; ?>
                        <?php elseif ($isRevoked): ?>
                            <span class="badge bg-danger"><?= esc($entitlement->status->label()) ?></span>
                        <?php elseif ($access === AccessLevel::ReadOnly): ?>
                            <span class="badge bg-warning text-dark"><?= esc(lang('MyPlugins.expiredReadOnly')) ?></span>
                        <?php else: ?>
                            <span class="badge bg-success"><?= esc(lang('MyPlugins.owned')) ?></span>
                            <?php if ($entitlement->isCancelled()): ?>
                                <span class="badge bg-secondary"><?= esc($entitlement->status->label()) ?></span>
                            <?php endif; ?>
                            <?php if ($entitlement->expiresAt !== null): ?>
                                <div class="small text-muted"><?= esc(date('Y-m-d', $entitlement->expiresAt)) ?> <?= esc(lang('MyPlugins.until')) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if ($canActivate): ?>
                        <form method="post" action="/my/plugins/<?= esc(rawurlencode($key), 'url') ?>/activate" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary"><?= esc(lang('MyPlugins.activate')) ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($canCancel): ?>
                        <form method="post" action="/my/plugins/<?= esc(rawurlencode($key), 'url') ?>/cancel" class="d-inline"
                              onsubmit="return confirm(<?= esc(json_encode(lang('MyPlugins.cancelConfirm'), JSON_UNESCAPED_UNICODE), 'attr') ?>)">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= esc(lang('MyPlugins.cancel')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
