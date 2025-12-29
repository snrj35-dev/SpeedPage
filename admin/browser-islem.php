<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
// Güvenlik: Eğer page parametresi browser değilse bu dosyayı işleme
if (($_GET['page'] ?? '') !== 'browser') {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        echo json_encode(['ok' => false, 'error' => 'CSRF verification failed']);
        exit;
    }
}
$ROOT = realpath(ROOT_DIR);
$SHOW_HIDDEN = false;

function safePath($root, $path)
{
    $path = trim($path, '/');
    $full = realpath($root . ($path ? '/' . $path : ''));
    if (!$full || strpos($full, $root) !== 0)
        return false;
    return $full;
}

// ================== ZIP İŞLEMLERİ ==================

// ZIP İNDİRME
if (isset($_GET['zip'])) {
    $p = safePath($ROOT, $_GET['zip']);
    if ($p) {
        $zipName = basename($p) . '.zip';
        $zipFile = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if (is_dir($p)) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($files as $file) {
                    $zip->addFile($file->getRealPath(), substr($file->getRealPath(), strlen($p) + 1));
                }
            } else {
                $zip->addFile($p, basename($p));
            }
            $zip->close();
            if (ob_get_level())
                ob_end_clean(); // Bozulmayı önlemek için tamponu temizle
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            readfile($zipFile);
            unlink($zipFile);
            exit;
        }
    }
}

// ZIP ÇIKARTMA (Geri eklendi :D)
if (isset($_GET['unzip'])) {
    header('Content-Type: application/json');
    $p = safePath($ROOT, $_POST['path'] ?? '');
    if ($p && strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'zip') {
        $zip = new ZipArchive;
        if ($zip->open($p) === TRUE) {
            $zip->extractTo(dirname($p));
            $zip->close();
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false]);
        }
    }
    exit;
}

// ================== DİĞER API İŞLEMLERİ ==================
// (API, read, save, delete, rename, mkdir, touch, upload kısımları aynı kalacak)
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $path = $_GET['path'] ?? '';
    $full = safePath($ROOT, $path);
    if (!$full || !is_dir($full))
        exit(json_encode([]));

    $out = [];
    foreach (scandir($full) as $f) {
        if ($f === '.' || $f === '..')
            continue;
        if (!$SHOW_HIDDEN && str_starts_with($f, '.'))
            continue;
        $fp = "$full/$f";
        $out[] = [
            'name' => $f,
            'type' => is_dir($fp) ? 'dir' : 'file',
            'path' => ltrim(($path ? "$path/" : "") . $f, '/'),
            'ext' => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
            'size' => is_dir($fp) ? 0 : filesize($fp),
            'mtime' => date('d.m.Y H:i', filemtime($fp))
        ];
    }
    usort($out, function ($a, $b) {
        if ($a['type'] === $b['type'])
            return strnatcasecmp($a['name'], $b['name']);
        return ($a['type'] === 'dir') ? -1 : 1;
    });
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// Dosya İçeriği Okuma
if (isset($_GET['read'])) {
    header('Content-Type: application/json');
    $p = safePath($ROOT, $_GET['read']);
    if ($p && !is_dir($p))
        echo json_encode(['ok' => true, 'content' => file_get_contents($p)], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kaydetme
if (isset($_GET['save'])) {
    header('Content-Type: application/json');
    $p = safePath($ROOT, $_POST['file'] ?? '');
    if ($p && !is_dir($p)) {
        file_put_contents($p, $_POST['content'] ?? '');
        echo json_encode(['ok' => true]);
    }
    exit;
}

// Silme (Klasör ve Dosya)
// Silme (Klasör ve Dosya)
if (isset($_GET['delete'])) {
    // Path must come from POST for safety
    $f = safePath($ROOT, $_POST['path'] ?? '');

    // Safety check: Do not allow deleting the Root directory!
    if ($f && $f !== $ROOT) {
        if (is_dir($f)) {
            $it = new RecursiveDirectoryIterator($f, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
            rmdir($f);
        } else {
            unlink($f);
        }
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid path or root directory']);
    }
    exit;
}

// Ad Değiştirme ve Yeni Klasör
if (isset($_GET['rename'])) {
    $o = safePath($ROOT, $_POST['old']);
    $n = basename($_POST['new']);
    if ($o && $n)
        rename($o, dirname($o) . '/' . $n);
    exit;
}
if (isset($_GET['mkdir'])) {
    $b = safePath($ROOT, $_POST['path']);
    $n = basename($_POST['name']);
    if ($b && $n)
        mkdir($b . '/' . $n);
    exit;
}
if (isset($_GET['touch'])) {
    $b = safePath($ROOT, $_POST['path']);
    $n = basename($_POST['name']);
    if ($b && $n)
        file_put_contents($b . '/' . $n, '');
    exit;
}
if (isset($_GET['upload'])) {
    $b = safePath($ROOT, $_POST['path']);
    if ($b && isset($_FILES['f']))
        move_uploaded_file($_FILES['f']['tmp_name'], $b . '/' . basename($_FILES['f']['name']));
    exit;
}