<?php
declare(strict_types=1);

/**
 * System Panel (Admin)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';

/** @var PDO $db */
global $db;

$startTime = microtime(true);

$data = [];

// 0. Otomatik Temizlik ve Logları Çek
if (function_exists('sp_log_cleanup')) {
    sp_log_cleanup(30); // 30 günden eski logları sil
}

// Logları Çek
$logs = [];
try {
    if (function_exists('ensure_log_table')) {
        ensure_log_table();
    }
    $logs = $db->query("
        SELECT l.*, u.username 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.id DESC 
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (function_exists('sp_log')) {
        sp_log('System Panel Logs Error: ' . $e->getMessage(), 'system_error');
    }
    $logs = [];
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">
            <i class="fas fa-history"></i>
            <span lang="system"><?= e(__('system')) ?></span>
        </h3>
        <button class="btn btn-outline-danger btn-responsive" onclick="confirmClearLogs()">
            <i class="fas fa-trash-alt me-1"></i>
            <span lang="clear"><?= e(__('clear')) ?></span>
        </button>
    </div>

    <div class="card">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold">
                <span lang="audit_logs"><?= e(__('audit_logs')) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="sp-responsive-table">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th width="160" lang="date"><?= e(__('date')) ?></th>
                                <th lang="action_type"><?= e(__('action_type')) ?></th>
                                <th lang="user"><?= e(__('user')) ?></th>
                                <th lang="ip"><?= e(__('ip')) ?></th>
                                <th lang="message"><?= e(__('message')) ?></th>
                                <th width="90" class="text-end" lang="details"><?= e(__('details')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-3 text-muted" lang="no_logs"><?= e(__('no_logs')) ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td data-label="#"><?= e((string) $log['id']) ?></td>
                                        <td data-label="<?= e(__('date')) ?>"><?= e((string) $log['created_at']) ?></td>
                                        <td data-label="<?= e(__('action_type')) ?>">
                                            <span class="badge" style="background: var(--bg-soft); color: var(--text);">
                                                <?= e((string) ($log['action_type'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td data-label="<?= e(__('user')) ?>"><?= e((string) ($log['username'] ?? '')) ?></td>
                                        <td data-label="<?= e(__('ip')) ?>" class="text-muted small"><?= e((string) ($log['ip_address'] ?? '')) ?></td>
                                        <td data-label="<?= e(__('message')) ?>"><?= e((string) ($log['message'] ?? '')) ?></td>
                                        <td class="text-end" data-label="<?= e(__('details')) ?>">
                                            <?php if (!empty($log['old_data']) || !empty($log['new_data'])): ?>
                                                <button class="btn btn-sm btn-outline-secondary"
                                                    onclick='viewLogDetails(<?= json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
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
</div>

<!-- LOG DETAY MODAL -->
<div class="modal fade" id="logDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>
                    <span lang="log_details"><?= e(__('log_details')) ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2" lang="old_data"><?= e(__('old_data')) ?></h6>
                        <pre id="logOld" class="bg-body border p-3 rounded" style="max-height: 400px; overflow: auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2" lang="new_data"><?= e(__('new_data')) ?></h6>
                        <pre id="logNew" class="bg-body border p-3 rounded" style="max-height: 400px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function viewLogDetails(log) {
        const formatJSON = (str) => {
            if (!str) return "{}";
            try {
                return JSON.stringify(JSON.parse(str), null, 4);
            } catch (e) {
                return str;
            }
        };

        document.getElementById('logOld').textContent = formatJSON(log.old_data);
        document.getElementById('logNew').textContent = formatJSON(log.new_data);

        const modal = new bootstrap.Modal(document.getElementById('logDetailModal'));
        modal.show();
    }

    function confirmClearLogs() {
        if (!confirm("<?= e(__('confirm_clear_logs')) ?>")) return;

        const formData = new FormData();
        formData.append('action', 'clear_logs');
        formData.append('csrf', '<?= e($_SESSION['csrf']) ?>');

        fetch('modul-func.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || 'Error');
                }
            })
            .catch(() => alert('Network error'));
    }
</script>