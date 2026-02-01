<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';
?>

<div class="container-fluid py-3">
    <div class="row">

        <!-- Mobile Sidebar Toggle -->
        <div class="col-12 d-md-none mb-3">
            <button class="btn btn-dark w-100 shadow-sm" type="button" data-bs-toggle="collapse"
                data-bs-target="#tablesCollapse">
                <i class="fas fa-database me-2"></i> <span lang="tables"><?= __('tables') ?></span>
            </button>
            <div class="collapse mt-2" id="tablesCollapse">
                <div class="card card-body p-0 border-0 shadow-sm">
                    <ul class="list-group list-group-flush tables-list" id="tables_mobile"></ul>
                </div>
            </div>
        </div>

        <!-- Desktop Sidebar -->
        <div class="col-md-3 d-none d-md-block bg-white border-end vh-100 overflow-auto p-3 sticky-top"
            style="top:0; z-index:100;">
            <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-database me-2"></i> <span
                    lang="tables"><?= __('tables') ?></span></h5>
            <ul class="list-group list-group-flush tables-list" id="tables"></ul>
        </div>

        <!-- Main Content -->
        <div class="col-12 col-md-9 p-4 db-content">

            <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1" lang="dbpanel_warning_title">
                        <?= __('dbpanel_warning_title') ?></h6>
                    <small lang="dbpanel_warning_body"><?= __('dbpanel_warning_body') ?></small>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 id="title" class="fw-bold m-0" lang="select_table"><?= __('select_table') ?></h4>

                <div class="btn-group">
                    <button class="btn btn-success btn-sm shadow-sm" onclick="addForm()">
                        <i class="fas fa-plus me-1"></i> <span lang="add_record"><?= __('add_record') ?></span>
                    </button>
                    <button class="btn btn-secondary btn-sm shadow-sm" onclick="exportSQL()">
                        <i class="fas fa-file-export me-1"></i> <span lang="db_export"><?= __('db_export') ?></span>
                    </button>
                    <button class="btn btn-outline-primary btn-sm shadow-sm" onclick="downloadSQLFile()">
                        <i class="fas fa-download me-1"></i> <span
                            lang="download_backup"><?= __('download_backup') ?></span>
                    </button>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3 bg-light rounded-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <a href="index.php?page=migration" class="btn btn-info btn-sm text-white shadow-sm">
                            <i class="fas fa-magic me-1"></i> Migration Wizard
                        </a>

                        <form id="uploadSqlForm" class="d-flex align-items-center ms-auto" enctype="multipart/form-data"
                            onsubmit="return uploadSqlFile(this)">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <div class="input-group input-group-sm">
                                <input type="file" name="sql_file" id="sql_file" accept=".sql" class="form-control"
                                    required>
                                <button class="btn btn-warning text-white" type="submit">
                                    <i class="fas fa-upload me-1"></i> <span
                                        lang="upload_backup_button"><?= __('upload_backup_button') ?></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="content" class="table-responsive bg-white shadow-sm rounded-3 border p-0 mb-4">
                <!-- Tablo içeriği JS ile gelecek -->
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-table fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">Verileri görüntülemek için soldan bir tablo seçin.</p>
                </div>
            </div>

            <!-- SQL Console -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 fw-bold">
                            <i class="fas fa-terminal me-2 text-dark"></i> <span
                                lang="sql_console"><?= __('sql_console') ?></span>
                        </div>
                        <div class="card-body">
                            <textarea id="sql" class="form-control font-monospace mb-3" rows="5"
                                placeholder="<?= __('sql_placeholder') ?>"></textarea>
                            <button class="btn btn-primary w-100" onclick="runSQL()">
                                <i class="fas fa-play me-1"></i> <span
                                    lang="run_sql_button"><?= __('run_sql_button') ?></span>
                            </button>
                            <pre id="sqlResult" class="mt-3 bg-light p-3 rounded border text-wrap"
                                style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;"></pre>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 fw-bold">
                            <i class="fas fa-file-import me-2 text-warning"></i> <span
                                lang="sql_import"><?= __('sql_import') ?></span>
                        </div>
                        <div class="card-body">
                            <textarea id="importSql" class="form-control font-monospace mb-3" rows="5"
                                placeholder="<?= __('sql_placeholder') ?>"></textarea>
                            <button class="btn btn-warning text-white w-100" onclick="importSQL()">
                                <i class="fas fa-upload me-1"></i> <span
                                    lang="sql_import_button"><?= __('sql_import_button') ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>