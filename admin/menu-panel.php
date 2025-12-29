<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';


/* ============================
    AJAX İŞLEMLERİ
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json; charset=utf-8");

    $action = $_POST['action'] ?? '';

    // CSRF Check
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        echo json_encode(['ok' => false, 'error' => 'CSRF verification failed']);
        exit;
    }

    /*  ADD */
    if ($action === 'add') {

        $page_id = trim($_POST['page_id']) === "" ? null : (int) $_POST['page_id'];
        $title = trim($_POST['title']);
        $icon = trim($_POST['icon']);
        $url = trim($_POST['external_url']) ?: null;
        $order = (int) $_POST['order_no'];

        $stmt = $db->prepare("
            INSERT INTO menus (page_id, title, icon, sort_order, is_active, external_url)
            VALUES (?,?,?,?,1,?)
        ");
        $stmt->execute([$page_id, $title, $icon, $order, $url]);

        $menu_id = $db->lastInsertId();

        //  Locations
        if (!empty($_POST['locations'])) {
            foreach ($_POST['locations'] as $loc) {
                $loc = preg_replace('/[^a-z0-9_-]/', '', $loc);
                $db->prepare("INSERT INTO menu_locations (menu_id, location) VALUES (?,?)")
                    ->execute([$menu_id, $loc]);
            }
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    /*  UPDATE */
    if ($action === 'update') {

        $id = (int) $_POST['id'];

        $fields = [];
        $values = [];

        $map = [
            'title' => 'title',
            'icon' => 'icon',
            'order_no' => 'sort_order',
            'external_url' => 'external_url',
            'page_id' => 'page_id',
            'aktif' => 'is_active'
        ];

        foreach ($map as $postKey => $dbKey) {
            if (isset($_POST[$postKey])) {
                $val = trim($_POST[$postKey]);
                $values[] = ($val === "" ? null : $val);
                $fields[] = "$dbKey=?";
            }
        }

        if ($fields) {
            $values[] = $id;
            $sql = "UPDATE menus SET " . implode(',', $fields) . " WHERE id=?";
            $db->prepare($sql)->execute($values);
        }

        //  Locations
        if (isset($_POST['locations'])) {
            $db->prepare("DELETE FROM menu_locations WHERE menu_id=?")->execute([$id]);

            foreach ($_POST['locations'] as $loc) {
                $loc = preg_replace('/[^a-z0-9_-]/', '', $loc);
                $db->prepare("INSERT INTO menu_locations (menu_id, location) VALUES (?,?)")
                    ->execute([$id, $loc]);
            }
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    /*  DELETE */
    if ($action === 'delete') {
        $id = (int) $_POST['id'];

        $db->prepare("DELETE FROM menu_locations WHERE menu_id=?")->execute([$id]);
        $db->prepare("DELETE FROM menus WHERE id=?")->execute([$id]);

        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'INVALID']);
    exit;
}

/* ============================
    LİSTELER
============================ */
$pages = $db->query("SELECT id, slug FROM pages ORDER BY slug")->fetchAll();

$menus = $db->query("
    SELECT m.*, p.slug
    FROM menus m
    LEFT JOIN pages p ON p.id = m.page_id
    ORDER BY m.sort_order
")->fetchAll();

$locRows = $db->query("SELECT menu_id, location FROM menu_locations")->fetchAll();

$menuLocations = [];
foreach ($locRows as $r) {
    $menuLocations[$r['menu_id']][] = $r['location'];
}
?>

<h4 class="mb-3"><i class="fas fa-clipboard-list"></i> <span lang="menu_management"></span></h4>

<div class="card p-3 mb-4">
    <h5><i class="fas fa-plus"></i> <span lang="add"></span></h5>

    <div class="row g-2">
        <div class="col">
            <input id="m_title" class="form-control" data-placeholder="menu_title_placeholder">
        </div>
        <div class="col">
            <input id="m_icon" class="form-control" data-placeholder="menu_icon_placeholder">
        </div>
        <div class="col">
            <select id="m_page" class="form-select">
                <option value="" lang="select_page_placeholder"></option>
                <?php foreach ($pages as $p): ?>
                    <option value="<?= e($p['id']) ?>"><?= e($p['slug']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col">
            <input id="m_url" class="form-control" data-placeholder="external_url_placeholder">
        </div>
        <div class="col">
            <input id="m_order" type="number" class="form-control" value="1">
        </div>
        <div class="col">
            <select id="m_locations" class="form-select" multiple>
                <option value="navbar" lang="navbar"></option>
                <option value="footer" lang="footer"></option>
                <option value="sidebar" lang="sidebar"></option>
                <option value="home" lang="home"></option>
            </select>
        </div>
        <div class="col">
            <button class="btn btn-success" onclick="MenuEdit.add()" lang="add"></button>
        </div>
    </div>
</div>

<?php foreach ($menus as $m): ?>
    <div class="menu-row d-flex align-items-center gap-2 mb-2" data-id="<?= e($m['id']) ?>">

        <input class="form-control" data-field="title" value="<?= e($m['title']) ?>" disabled>

        <input class="form-control" data-field="icon" value="<?= e($m['icon']) ?>" disabled>

        <input class="form-control" data-field="order_no" value="<?= e($m['sort_order']) ?>" disabled>

        <select class="form-select" data-field="page_id" disabled>
            <option value="" lang="select_page_placeholder"></option>
            <?php foreach ($pages as $p): ?>
                <option value="<?= e($p['id']) ?>" <?= ($m['page_id'] == $p['id']) ? 'selected' : '' ?>>
                    <?= e($p['slug']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input class="form-control" data-field="external_url" value="<?= e($m['external_url']) ?>"
            data-placeholder="external_url_placeholder" disabled>

        <select class="form-select" data-field="aktif" disabled>
            <option value="1" <?= $m['is_active'] ? 'selected' : '' ?> lang="active"></option>
            <option value="0" <?= !$m['is_active'] ? 'selected' : '' ?> lang="passive"></option>
        </select>

        <select class="form-select" data-field="locations" multiple disabled>
            <?php
            $locs = $menuLocations[$m['id']] ?? [];
            foreach (['navbar', 'footer', 'sidebar', 'home'] as $loc):
                ?>
                <option value="<?= $loc ?>" <?= in_array($loc, $locs) ? 'selected' : '' ?> lang="<?= $loc ?>"></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary btn-sm" onclick="MenuEdit.edit(this)"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="MenuEdit.del(<?= e($m['id']) ?>)"><i
                class="fas fa-trash"></i></button>

    </div>
<?php endforeach; ?>