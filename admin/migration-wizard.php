<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-lg border-0">
                <div
                    class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-magic me-2"></i> <span lang="migration_title">Veritabanı Taşıma
                            Sihirbazı (SQLite -> MySQL)</span></h5>
                    <button class="btn btn-danger btn-sm" onclick="rollbackSystem()">
                        <i class="fa fa-undo"></i> <span lang="migration_rollback_btn">Acil Durum / Rollback</span>
                    </button>
                </div>
                <div class="card-body p-4">

                    <!-- Progress Bar -->
                    <div class="progress mb-4" style="height: 30px; font-size: 1rem;">
                        <div id="wizard-progress"
                            class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar"
                            style="width: 25%">
                            <span lang="step_1_title">Adım 1: Bağlantı</span>
                        </div>
                    </div>

                    <!-- Alert Area -->
                    <div id="wizard-alert" class="alert d-none"></div>

                    <!-- STEP 1: CONFIGURATION -->
                    <div id="step-1" class="wizard-step">
                        <h4 class="mb-3 text-primary"><span lang="target_db_settings">Hedef Veritabanı Ayarları</span>
                        </h4>
                        <form id="db-config-form" onsubmit="return false;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" lang="db_type">Veritabanı Türü</label>
                                    <select class="form-select" name="type" id="db_type">
                                        <option value="mysql">MySQL / MariaDB</option>
                                        <option value="pgsql">PostgreSQL</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" lang="db_host">Sunucu (Host)</label>
                                    <input type="text" class="form-control" name="host" value="localhost" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" lang="db_name">Veritabanı Adı</label>
                                    <input type="text" class="form-control" name="name" placeholder="speedpage_db"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" lang="db_port">Port</label>
                                    <input type="number" class="form-control" name="port" value="3306" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" lang="db_user">Kullanıcı Adı</label>
                                    <input type="text" class="form-control" name="user" value="root" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" lang="db_pass">Şifre</label>
                                    <input type="password" class="form-control" name="pass"
                                        data-placeholder="db_pass_placeholder">
                                </div>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-primary btn-lg" onclick="testConnection()">
                                    <span lang="btn_test_connect">Bağlantıyı Test Et ve İlerle</span> <i
                                        class="fa fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- STEP 2: SCHEMA MIGRATION -->
                    <div id="step-2" class="wizard-step d-none">
                        <h4 class="mb-3 text-primary"><span lang="schema_creating">Tablo Yapısı Oluşturuluyor...</span>
                        </h4>
                        <div class="alert alert-warning">
                            <i class="fa fa-info-circle"></i> <span lang="schema_info">SQLite tabloları hedef veritabanı
                                formatına dönüştürülüyor.</span>
                        </div>
                        <div class="console-log bg-dark text-success p-3 rounded mb-3" id="schema-log"
                            style="height: 200px; overflow-y: auto; font-family: monospace;">
                            > <span lang="ready_msg">Hazır...</span>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-primary btn-lg disabled" id="btn-next-2"
                                onclick="startDataMigration()">
                                <span lang="btn_start_migration">Veri Taşıma İşlemine Geç</span> <i
                                    class="fa fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: DATA MIGRATION -->
                    <div id="step-3" class="wizard-step d-none">
                        <h4 class="mb-3 text-primary"><span lang="migrating_data">Veriler Taşınıyor...</span></h4>
                        <div id="migration-progress-container">
                            <!-- Dynamic Progress Bars will be added here -->
                        </div>
                        <div class="console-log bg-dark text-warning p-3 rounded mb-3" id="data-log"
                            style="height: 200px; overflow-y: auto; font-family: monospace;">
                            > <span lang="waiting_migration">Veri taşıma bekleniyor...</span>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-primary btn-lg disabled" id="btn-next-3"
                                onclick="finalizeMigration()">
                                <span lang="btn_finalize">İşlemi Tamamla</span> <i class="fa fa-check"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 4: FINALIZE -->
                    <div id="step-4" class="wizard-step d-none text-center p-5">
                        <div class="mb-4">
                            <i class="fa fa-check-circle text-success" style="font-size: 5rem;"></i>
                        </div>
                        <h2 class="text-success" lang="migration_success_title">Taşıma İşlemi Başarılı!</h2>
                        <p class="lead" lang="migration_success_msg">Config dosyası güncellendi. Artık MySQL (veya
                            seçilen DB) kullanıyorsunuz.</p>
                        <hr>
                        <p class="text-muted" lang="migration_backup_info">Eski `db.php` dosyası `db.php.bak` olarak
                            yedeklendi. Bir sorun yaşarsanız "Acil Durum" butonu ile geri alabilirsiniz.</p>
                        <a href="index.php" class="btn btn-success btn-lg mt-3" lang="back_to_admin">Yönetim Paneline
                            Dön</a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const API_URL = 'migration-process.php';
    let TABLES = []; // To store table list

    // JS side translations helper
    function t(key) {
        return (window.lang && window.lang[key]) ? window.lang[key] : key;
    }

    function showStep(step, percent, textKey) {
        $('.wizard-step').addClass('d-none');
        $('#step-' + step).removeClass('d-none');
        const text = t(textKey);
        $('#wizard-progress').css('width', percent + '%').text(text).removeClass('bg-danger bg-success bg-info').addClass(step === 4 ? 'bg-success' : 'bg-info');
    }

    function log(elementId, msg) {
        const el = $('#' + elementId);
        el.append('<div>> ' + msg + '</div>');
        el.scrollTop(el[0].scrollHeight);
    }

    function testConnection() {
        const formData = new FormData(document.getElementById('db-config-form'));
        formData.append('action', 'connect');

        $('#wizard-alert').addClass('d-none');

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.status === 'success') {
                    showStep(2, 50, 'step_2_title');
                    startSchemaMigration();
                } else {
                    $('#wizard-alert').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                }
            },
            error: function () {
                alert(t('sunucuerror'));
            }
        });
    }

    function startSchemaMigration() {
        $.post(API_URL, { action: 'prepare_schema' }, function (res) {
            if (res.status === 'success') {
                res.logs.forEach(l => log('schema-log', l));
                TABLES = res.tables;
                log('schema-log', t('all_tables_created'));
                $('#btn-next-2').removeClass('disabled');
            } else {
                log('schema-log', t('error_prefix') + res.message);
            }
        });
    }

    function startDataMigration() {
        showStep(3, 75, 'step_3_title');
        processNextTable(0);
    }

    function processNextTable(index) {
        if (index >= TABLES.length) {
            log('data-log', t('migration_finished'));
            $('#btn-next-3').removeClass('disabled');
            return;
        }

        const table = TABLES[index];
        log('data-log', `${t('table_starting')} ${table}...`);

        // Create progress bar for this table
        const pId = `progress-${table}`;
        $('#migration-progress-container').append(`
            <div class="mb-2">
                <small>${table}</small>
                <div class="progress" style="height: 10px;">
                    <div id="${pId}" class="progress-bar" style="width: 0%"></div>
                </div>
            </div>
        `);

        migrateTableChunk(table, 0, pId, function () {
            log('data-log', `${t('table_finished')} ${table}`);
            processNextTable(index + 1);
        });
    }

    function migrateTableChunk(table, offset, progressId, onComplete) {
        $.post(API_URL, { action: 'migrate_data', table: table, offset: offset }, function (res) {
            if (res.status === 'success') {
                // Update progress
                let percent = 0;
                if (res.total > 0) {
                    percent = Math.round((res.next_offset / res.total) * 100);
                } else {
                    percent = 100; // Empty table
                }
                $(`#${progressId}`).css('width', percent + '%');

                if (!res.finished) {
                    migrateTableChunk(table, res.next_offset, progressId, onComplete);
                } else {
                    $(`#${progressId}`).addClass('bg-success'); // Mark green
                    onComplete();
                }
            } else {
                log('data-log', `${t('error_prefix')} (${table}): ` + res.message);

                if (confirm(t('error_occured') + ' ' + res.message + '. ' + t('continue_confirm'))) {
                    onComplete(); // Skip this table
                }
            }
        });
    }

    function finalizeMigration() {
        $.post(API_URL, { action: 'finalize' }, function (res) {
            if (res.status === 'success') {
                showStep(4, 100, 'completed');
            } else {
                alert(t('error_prefix') + res.message);
            }
        });
    }

    function rollbackSystem() {
        if (confirm(t('rollback_confirm'))) {
            $.post(API_URL, { action: 'rollback' }, function (res) {
                alert(res.message);
                if (res.status === 'success') {
                    location.reload();
                }
            });
        }
    }
</script>