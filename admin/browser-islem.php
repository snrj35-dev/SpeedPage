<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php'; // Loglama için DB bağlantısı gerekebilir

if (empty($is_admin) || !$is_admin) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

// Güvenlik: Page parametresi kontrolü
$pageParam = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
if ($pageParam !== 'browser') {
    http_response_code(403);
    exit;
}

// POST isteklerinde CSRF kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = filter_input(INPUT_POST, 'csrf', FILTER_SANITIZE_SPECIAL_CHARS);
    if (!$csrf || $csrf !== $_SESSION['csrf']) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'CSRF verification failed']);
        exit;
    }
}

$ROOT = realpath(ROOT_DIR);
$SHOW_HIDDEN = false;

/*
 * Safe Path Resolver
 * Ensures that the requested path is within the ROOT directory.
 */
function safePath(string $root, string $path): string|false
{
    $path = trim($path, '/');
    // Prevent directory traversal
    if (strpos($path, '..') !== false) {
        return false;
    }

    $full = realpath($root . ($path ? '/' . $path : ''));

    // Check if realpath resolved and is within root
    if (!$full || strpos($full, $root) !== 0) {
        return false;
    }

    return $full;
}

function isUnsafeZipEntry(string $name): bool
{
    $name = str_replace('\\', '/', $name);
    if ($name === '' || str_starts_with($name, '/')) {
        return true;
    }
    if (preg_match('/^[A-Za-z]:\//', $name)) {
        return true;
    }
    if (strpos($name, "\0") !== false) {
        return true;
    }
    foreach (explode('/', $name) as $part) {
        if ($part === '..') {
            return true;
        }
    }
    return false;
}

function extractZipSafely(ZipArchive $zip, string $destination): void
{
    $base = realpath($destination);
    if ($base === false) {
        throw new RuntimeException('Invalid extraction base');
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false) {
            continue;
        }
        $entry = str_replace('\\', '/', $entry);
        $entry = ltrim($entry, '/');

        if (isUnsafeZipEntry($entry)) {
            throw new RuntimeException('Unsafe ZIP entry');
        }
        if ($entry === '') {
            continue;
        }

        $target = $base . '/' . $entry;
        if (str_ends_with($entry, '/')) {
            if (!is_dir($target) && !mkdir($target, 0755, true)) {
                throw new RuntimeException('Directory create failed');
            }
            continue;
        }

        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('Directory create failed');
        }
        $dirReal = realpath($dir);
        if ($dirReal === false || !str_starts_with($dirReal, $base)) {
            throw new RuntimeException('Unsafe extraction target');
        }

        $in = $zip->getStream($zip->getNameIndex($i));
        if (!$in) {
            throw new RuntimeException('ZIP stream read failed');
        }
        $out = fopen($target, 'wb');
        if (!$out) {
            fclose($in);
            throw new RuntimeException('Target write failed');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }
}

// ================== ZIP İŞLEMLERİ ==================

// ZIP İNDİRME
if (isset($_GET['zip'])) {
    $reqZip = filter_input(INPUT_GET, 'zip', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $p = safePath($ROOT, $reqZip);

    if ($p) {
        $zipName = basename($p) . '.zip';
        $zipFile = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if (is_dir($p)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($p, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $file) {
                    $localPath = substr($file->getRealPath(), strlen($p) + 1);
                    $zip->addFile($file->getRealPath(), $localPath);
                }
            } else {
                $zip->addFile($p, basename($p));
            }
            $zip->close();

            if (ob_get_level())
                ob_end_clean();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile);
            unlink($zipFile);

            if (function_exists('sp_log')) {
                sp_log("ZIP indirildi: $zipName", "browser_zip_download");
            }
            exit;
        }
    }
}

// ZIP ÇIKARTMA
if (isset($_GET['unzip'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pathPost = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $p = safePath($ROOT, $pathPost);

    if ($p && strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'zip') {
        $zip = new ZipArchive;
        if ($zip->open($p) === TRUE) {
            try {
                extractZipSafely($zip, dirname($p));
                if (function_exists('sp_log')) {
                    sp_log("ZIP çıkartıldı: " . basename($p), "browser_unzip_ok");
                }
                echo json_encode(['ok' => true]);
            } catch (Throwable $e) {
                echo json_encode(['ok' => false, 'error' => 'Unsafe or invalid ZIP']);
            } finally {
                $zip->close();
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Could not open ZIP']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid file']);
    }
    exit;
}

// ================== DİĞER API İŞLEMLERİ ==================

// List Directory (API)
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pathGet = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $full = safePath($ROOT, $pathGet);

    if (!$full || !is_dir($full)) {
        echo json_encode([]);
        exit;
    }

    $out = [];
    $scan = scandir($full);

    if ($scan) {
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..')
                continue;
            if (!$SHOW_HIDDEN && str_starts_with($f, '.'))
                continue;

            $fp = "$full/$f";
            $stat = @stat($fp);
            $size = is_dir($fp) ? 0 : ($stat['size'] ?? 0);
            $mtime = $stat['mtime'] ?? time();

            $out[] = [
                'name' => $f,
                'type' => is_dir($fp) ? 'dir' : 'file',
                'path' => ltrim(($pathGet ? "$pathGet/" : "") . $f, '/'),
                'ext' => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
                'size' => $size,
                'mtime' => date('d.m.Y H:i', $mtime)
            ];
        }
    }

    usort($out, function ($a, $b) {
        if ($a['type'] === $b['type'])
            return strnatcasecmp($a['name'], $b['name']);
        return ($a['type'] === 'dir') ? -1 : 1;
    });

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

// Dosya İçeriği Okuma
if (isset($_GET['read'])) {
    header('Content-Type: application/json; charset=utf-8');
    $readGet = filter_input(INPUT_GET, 'read', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $p = safePath($ROOT, $readGet);

    if ($p && !is_dir($p) && file_exists($p)) {
        $content = file_get_contents($p);
        echo json_encode(['ok' => true, 'content' => $content], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } else {
        echo json_encode(['ok' => false, 'error' => 'File not found or is directory']);
    }
    exit;
}

// Kaydetme
if (isset($_GET['save'])) {
    header('Content-Type: application/json; charset=utf-8');
    // POST request expected
    $filePost = filter_input(INPUT_POST, 'file', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $p = safePath($ROOT, $filePost);
    $content = $_POST['content'] ?? ''; // Raw content allowed

    if ($p && !is_dir($p)) {
        if (file_put_contents($p, $content) !== false) {
            if (function_exists('sp_log')) {
                sp_log("Dosya düzenlendi: " . basename($p), "browser_edit_ok");
            }
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Write error']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid path']);
    }
    exit;
}

// Silme (Klasör ve Dosya)
if (isset($_GET['delete'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pathPost = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $f = safePath($ROOT, $pathPost);

    // Safety check: Do not allow deleting the Root directory!
    if ($f && $f !== $ROOT) {
        $success = false;
        if (is_dir($f)) {
            // Recursive delete
            $it = new RecursiveDirectoryIterator($f, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            $success = rmdir($f);
        } else {
            $success = unlink($f);
        }

        if ($success) {
            if (function_exists('sp_log')) {
                sp_log("Silindi: " . basename($f), "browser_delete_ok");
            }
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Delete failed']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid path or root directory']);
    }
    exit;
}

// Ad Değiştirme
if (isset($_GET['rename'])) {
    header('Content-Type: application/json; charset=utf-8');
    $oldPost = filter_input(INPUT_POST, 'old', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $newPost = filter_input(INPUT_POST, 'new', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    $o = safePath($ROOT, $oldPost);
    $nName = basename($newPost); // Sadece isim, path değil

    if ($o && $nName) {
        $newPath = dirname($o) . '/' . $nName;
        if (rename($o, $newPath)) {
            if (function_exists('sp_log')) {
                sp_log("Yeniden adlandırıldı: " . basename($o) . " -> $nName", "browser_rename_ok");
            }
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Rename failed']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    }
    exit;
}

// Yeni Klasör
if (isset($_GET['mkdir'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pathPost = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $namePost = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    $b = safePath($ROOT, $pathPost);
    $n = basename($namePost);

    if ($b && $n) {
        $newDir = $b . '/' . $n;
        if (!file_exists($newDir)) {
            if (mkdir($newDir)) {
                if (function_exists('sp_log')) {
                    sp_log("Klasör oluşturuldu: $n (in $pathPost)", "browser_mkdir_ok");
                }
                echo json_encode(['ok' => true]);
            } else {
                $err = error_get_last();
                if (function_exists('sp_log')) {
                    sp_log("Klasör oluşturma hatası: $n", "browser_mkdir_error");
                }
                echo json_encode(['ok' => false, 'error' => 'Could not create directory (' . ($err['message'] ?? '') . ')']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Directory already exists']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid path or name']);
    }
    exit;
}

// Yeni Dosya
if (isset($_GET['touch'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pathPost = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $namePost = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    $b = safePath($ROOT, $pathPost);
    $n = basename($namePost);

    if ($b && $n) {
        $newFile = $b . '/' . $n;
        if (!file_exists($newFile)) {
            if (file_put_contents($newFile, '') !== false) {
                if (function_exists('sp_log')) {
                    sp_log("Dosya oluşturuldu: $n (in $pathPost)", "browser_touch_ok");
                }
                echo json_encode(['ok' => true]);
            } else {
                $err = error_get_last();
                if (function_exists('sp_log')) {
                    sp_log("Dosya oluşturma hatası: $n", "browser_touch_error");
                }
                echo json_encode(['ok' => false, 'error' => 'Could not create file (' . ($err['message'] ?? '') . ')']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'File already exists']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid path or name']);
    }
    exit;
}

// Dosya Yükleme
if (isset($_GET['upload'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pathPost = filter_input(INPUT_POST, 'path', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $b = safePath($ROOT, $pathPost);

    if ($b && isset($_FILES['f'])) {
        $uploadedFile = $_FILES['f'];
        $fileName = basename($uploadedFile['name']);

        if (move_uploaded_file($uploadedFile['tmp_name'], $b . '/' . $fileName)) {
            if (function_exists('sp_log')) {
                sp_log("Dosya yüklendi: $fileName -> $pathPost", "browser_upload_ok");
            }
            echo json_encode(['ok' => true]);
        } else {
            $err = error_get_last();
            if (function_exists('sp_log')) {
                sp_log("Yükleme hatası: $fileName", "browser_upload_error");
            }
            echo json_encode(['ok' => false, 'error' => 'Upload failed (' . ($err['message'] ?? '') . ')']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid path or file']);
    }
    exit;
}
