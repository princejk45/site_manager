<?php include APP_PATH . '/includes/header.php'; ?>
<?php include APP_PATH . '/includes/sidebar.php'; ?>
<?php
$hostingPlan = $hostingPlan ?? ['id' => 0, 'name' => '', 'service_count' => 0];
$services = $services ?? [];
$userRole = $userRole ?? ($_SESSION['role'] ?? 'viewer');

$safe = static function (array $arr, string $key, string $default = ''): string {
    $v = $arr[$key] ?? null;
    if ($v === null || $v === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '-';
    }
    try {
        return htmlspecialchars((new DateTimeImmutable($date))->format('d-m-Y'), ENT_QUOTES, 'UTF-8');
    } catch (Exception $e) {
        return htmlspecialchars((string)$date, ENT_QUOTES, 'UTF-8');
    }
};
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= __('hosting.services_for') . ' ' . htmlspecialchars($hostingPlan['name'] ?? '') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php?action=hosting&lang=<?= $_SESSION['lang'] ?? 'it' ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('common.back_to_list') ?>
                    </a>
                    <a href="index.php?action=hosting&do=service_create&id=<?= $hostingPlan['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                        class="btn btn-primary ml-2">
                        <i class="fas fa-plus"></i> <?= __('common.add_service') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (empty($services)): ?>
                <div class="alert alert-info"><?= __('hosting.no_services') ?></div>
            <?php else: ?>
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th><?= __('websites.domain_name') ?>
                                            <?php if ($hostingPlan['service_count'] > 0): ?>
                                                <span class="badge badge-info"> <?= $hostingPlan['service_count'] ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">0</span>
                                            <?php endif; ?>
                                        </th>
                                        <th><?= __('common.type') ?></th>
                                        <th><?= __('common.registrant') ?></th>
                                        <th><?= __('hosting.expiry_date') ?></th>
                                        <th><?= __('websites.status') ?></th>
                                        <th><?= __('hosting.actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                        <?php
                                        $serviceType = $service['service_type'] ?? 'hosting_web';
                                        $serviceTypeLabel = __('websites.type_' . $serviceType);
                                        if ($serviceTypeLabel === 'websites.type_' . $serviceType) {
                                            $serviceTypeLabel = ucfirst(str_replace('_', ' ', (string)$serviceType));
                                        }

                                        $registrantLabel = '';
                                        if ($serviceType === 'domain') {
                                            $registrantLabel = __('websites.registrar');
                                        } elseif ($serviceType === 'hosting_web') {
                                            $registrantLabel = __('websites.web_provider');
                                        } elseif ($serviceType === 'hosting_mail') {
                                            $registrantLabel = __('websites.mail_provider');
                                        }

                                        $status = $service['dynamic_status'] ?? 'attivo';
                                        $badgeClass = [
                                            'attivo' => 'success',
                                            'scade_presto' => 'warning',
                                            'scaduto' => 'danger'
                                        ][$status] ?? 'secondary';
                                        $statusTranslations = [
                                            'attivo' => __('common.active'),
                                            'scade_presto' => __('common.expiring_soon'),
                                            'scaduto' => __('common.expired')
                                        ];
                                        ?>
                                        <tr>
                                            <td><?= $safe($service, 'domain', '-') ?></td>
                                            <td><?= htmlspecialchars($serviceTypeLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= $safe($service, 'direct_provider_name', $registrantLabel !== '' ? $registrantLabel : 'N/A') ?></td>
                                            <td><?= $formatDate($service['expiry_date'] ?? null) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $badgeClass ?>">
                                                    <?= $statusTranslations[$status] ?? ucwords(str_replace('_', ' ', $status)) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="index.php?action=websites&do=view&id=<?= $service['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                    class="btn btn-sm btn-success" data-custom-tooltip="<?= __('hosting.view') ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($userRole === 'manager' || $userRole === 'super_admin'): ?>
                                                    <a href="index.php?action=websites&do=edit&id=<?= $service['id'] ?>&lang=<?= $_SESSION['lang'] ?? 'it' ?>"
                                                        class="btn btn-sm btn-primary" data-custom-tooltip="<?= __('hosting.edit') ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="index.php?action=websites&do=delete&id=<?= $service['id'] ?>"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('<?= __('common.sure_delete_service') ?>');"
                                                        data-custom-tooltip="<?= __('hosting.delete') ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include APP_PATH . '/includes/footer.php'; ?>