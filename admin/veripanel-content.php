<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';
?>

<div class="container-fluid">
<div class="row">

<div class="col-12 d-md-none p-2">
    <button class="btn btn-dark w-100 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#tablesCollapse">
        <i class="fa fa-database"></i> <span lang="tables">Tabloları Göster</span>
    </button>
    <div class="collapse" id="tablesCollapse">
        <div class="card card-body p-0">
            <ul class="list-group tables-list" id="tables_mobile"></ul>
        </div>
    </div>
</div>

<div class="col-md-3 d-none d-md-block bg-dark text-white vh-100 p-3">
    <h5><i class="fa fa-database"></i> Tablolar</h5>
    <ul class="list-group db-sidebar tables-list" id="tables"></ul>
</div>

<div class="col-12 col-md-9 p-4 db-content">


<div class="alert alert-info">
<b lang="dbpanel_warning_title"></b><br>
<span lang="dbpanel_warning_body"></span>
</div>

<h4 id="title" lang="select_table"></h4>

<button class="btn btn-success btn-sm mb-2" onclick="addForm()">
<i class="fa fa-plus"></i> <span lang="add_record"></span>
</button>

<button class="btn btn-secondary btn-sm mb-2" onclick="exportSQL()">
<i class="fa fa-download"></i> <span lang="db_export"></span>
</button>

    <button class="btn btn-outline-primary btn-sm mb-2" onclick="downloadSQLFile()">
    <i class="fa fa-file-download"></i> <span lang="download_backup"></span>
    </button>

<form id="uploadSqlForm" enctype="multipart/form-data" onsubmit="return uploadSqlFile(this)">
        <div class="input-group mb-2">
        <input type="file" name="sql_file" id="sql_file" accept=".sql" class="form-control form-control-sm">
        <button class="btn btn-warning btn-sm" type="submit"><i class="fa fa-upload"></i> <span lang="upload_backup_button"></span></button>
    </div>
</form>

<div id="content" class="table-responsive"></div>

<hr>
<h5 lang="sql_console"></h5>
<textarea id="sql" class="form-control" rows="4" data-placeholder="sql_placeholder"></textarea>
<button class="btn btn-primary mt-2" onclick="runSQL()"><span lang="run_sql_button"></span></button>
<pre id="sqlResult" class="mt-2 bg-light p-2"></pre>
<hr>
<h5 lang="sql_import"></h5>
<textarea id="importSql" class="form-control" rows="5"
placeholder="CREATE / INSERT / UPDATE / DELETE SQL paste"></textarea>

<button class="btn btn-warning mt-2" onclick="importSQL()">
<i class="fa fa-upload"></i> <span lang="sql_import_button"></span>
</button>

</div>
</div>
</div>

