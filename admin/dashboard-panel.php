<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

// Veri Çekme
try {
    $counts = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM pages) AS total_pages,
            (SELECT COUNT(*) FROM modules WHERE is_active = 1) AS active_modules"
    )->fetch(PDO::FETCH_ASSOC);

    $total_users = (int)($counts['total_users'] ?? 0);
    $total_pages = (int)($counts['total_pages'] ?? 0);
    $active_modules = (int)($counts['active_modules'] ?? 0);
    
    // Son 5 sistem günlüğü
    $recent_logs = $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    if (function_exists('sp_log')) {
        sp_log("Dashboard data fetch error: " . $e->getMessage(), "system_error");
    }
    $total_users = 0;
    $total_pages = 0;
    $active_modules = 0;
    $recent_logs = [];
}
?>

<!-- Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fas fa-tachometer-alt text-primary me-2"></i>
            <span lang="dashboard">Dashboard</span>
        </h4>
        <p class="text-muted mb-0">
            <span lang="welcome">Merhaba,</span> <?= e((string) $finalName) ?>
        </p>
    </div>
    <div class="text-end">
        <small class="text-muted">
            <i class="fas fa-clock me-1"></i>
            <?= e(date('d.m.Y H:i')) ?>
        </small>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="row g-4 mb-4">
    <!-- Kullanıcı Kartı -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100" style="background: var(--card); box-shadow: var(--shadow); border-radius: var(--radius);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle p-3" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1" lang="total_users">Toplam Kullanıcı</h6>
                        <h3 class="mb-0 fw-bold"><?= e(number_format($total_users)) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sayfa Kartı -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100" style="background: var(--card); box-shadow: var(--shadow); border-radius: var(--radius);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle p-3" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                            <i class="fas fa-file-alt fa-lg"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1" lang="total_pages">Toplam Sayfa</h6>
                        <h3 class="mb-0 fw-bold"><?= e(number_format($total_pages)) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modül Kartı -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100" style="background: var(--card); box-shadow: var(--shadow); border-radius: var(--radius);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle p-3" style="background: rgba(251, 146, 60, 0.1); color: var(--warning);">
                            <i class="fas fa-puzzle-piece fa-lg"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1" lang="active_modules">Aktif Modül</h6>
                        <h3 class="mb-0 fw-bold"><?= e(number_format($active_modules)) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sistem Durumu Kartı -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100" style="background: var(--card); box-shadow: var(--shadow); border-radius: var(--radius);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle p-3" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                            <i class="fas fa-server fa-lg"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1" lang="system_status">Sistem Durumu</h6>
                        <h3 class="mb-0 fw-bold">
                            <span class="badge" style="background: var(--success); color: white;">
                                <i class="fas fa-check-circle me-1"></i>
                                <span lang="online">Online</span>
                            </span>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Son Aktiviteler -->
<div class="card" style="background: var(--card); box-shadow: var(--shadow); border-radius: var(--radius);">
    <div class="card-header py-3" style="border-bottom: 1px solid var(--border);">
        <h6 class="m-0 fw-bold">
            <i class="fas fa-history me-2"></i>
            <span lang="recent_activities">Son Aktiviteler</span>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="sp-responsive-table">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th lang="date_time" data-label="Tarih/Saat">Tarih/Saat</th>
                            <th lang="type" data-label="Tür">Tür</th>
                            <th lang="description" data-label="Açıklama">Açıklama</th>
                            <th lang="user" data-label="Kullanıcı">Kullanıcı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted" lang="no_activities">
                                    Henüz aktivite kaydı bulunmuyor.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td data-label="Tarih/Saat">
                                        <?= e(date('d.m.Y H:i', strtotime((string) ($log['created_at'] ?? 'now')))) ?>
                                    </td>
                                    <td data-label="Tür">
                                        <span class="badge rounded-pill" style="background: var(--bg-soft); color: var(--text);">
                                            <?= e($log['type'] ?? 'system') ?>
                                        </span>
                                    </td>
                                    <td data-label="Açıklama">
                                        <?= e($log['message'] ?? 'N/A') ?>
                                    </td>
                                    <td data-label="Kullanıcı">
                                        <?= e(!empty($log['user_id']) ? ('ID: ' . (string) $log['user_id']) : 'System') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı Eylemler -->
<div class="row g-4 mt-2">
    <div class="col-md-6">
        <div class="card h-100" style="background: var(--card); box-shadow: var(--shadow); border-radius: var(--radius);">
            <div class="card-body">
                <h6 class="card-title fw-bold mb-3">
                    <i class="fas fa-rocket me-2"></i>
                    <span lang="quick_actions">Hızlı Eylemler</span>
                </h6>
                <div class="d-grid gap-2">
                    <a href="index.php?page=pages" class="btn btn-outline-primary btn-responsive">
                        <i class="fas fa-plus me-2"></i>
                        <span lang="new_page">Yeni Sayfa</span>
                    </a>
                    <a href="index.php?page=users" class="btn btn-outline-success btn-responsive">
                        <i class="fas fa-user-plus me-2"></i>
                        <span lang="new_user">Yeni Kullanıcı</span>
                    </a>
                    <a href="index.php?page=modules" class="btn btn-outline-warning btn-responsive">
                        <i class="fas fa-download me-2"></i>
                        <span lang="install_module">Modül Yükle</span>
                    </a>
                    <a href="index.php?page=system" class="btn btn-outline-secondary btn-responsive">
                        <i class="fas fa-history me-2"></i>
                        <span lang="system">Hata Kayıtları</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100" style="background: var(--card); box-shadow: var(--shadow); border-radius: var(--radius);">
            <div class="card-body">
                <h6 class="card-title fw-bold mb-3">
                    <i class="fas fa-chart-line me-2"></i>
                    <span lang="system_info">Sistem Bilgisi</span>
                </h6>
                <div class="row g-3">
                    <div class="col-6">
                        <small class="text-muted d-block" lang="php_version">PHP Sürümü</small>
                        <strong><?= PHP_VERSION ?></strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block" lang="server_time">Sunucu Zamanı</small>
                        <strong><?= date('H:i:s') ?></strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block" lang="database">Veritabanı</small>
                        <strong><?= $db->getAttribute(PDO::ATTR_DRIVER_NAME) ?></strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block" lang="theme">Aktif Tema</small>
                        <strong><?= e(ACTIVE_THEME) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
