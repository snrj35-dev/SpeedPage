<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

// Support both 't' (requested) and 'theme' (already used in themes-panel)
$themeName = $_GET['t'] ?? $_GET['theme'] ?? '';
if (!$themeName) {
    echo "<div class='alert alert-danger'>" . __('theme_name_missing') . "</div>";
    return;
}

$themePath = ROOT_DIR . "themes/" . $themeName . "/";
$jsonPath = $themePath . "settings.json";

if (!file_exists($jsonPath)) {
    echo "<div class='alert alert-warning'>" . __('theme_settings_not_found') . " ($jsonPath). <br> " . __('theme_upload_help') . "</div>";
    return;
}

$settingsMeta = json_decode(file_get_contents($jsonPath), true);
if (!$settingsMeta) {
    echo "<div class='alert alert-danger'>" . __('theme_json_error') . "</div>";
    return;
}

// Kaydetme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme_settings'])) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        die("CSRF Hatası");
    }

    foreach ($settingsMeta['fields'] as $field) {
        $key = $field['id'];
        $val = $_POST[$key] ?? '';

        // Checkbox handling (comes as 'on' or nothing)
        if ($field['type'] === 'checkbox') {
            $val = isset($_POST[$key]) ? '1' : '0';
        }

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $db->prepare("INSERT INTO theme_settings (theme_name, setting_key, setting_value) 
                                  VALUES (?, ?, ?) 
                                  ON CONFLICT(theme_name, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
            $stmt->execute([$themeName, $key, $val]);
        } else {
            // MySQL: ON DUPLICATE KEY UPDATE
            $stmt = $db->prepare("INSERT INTO theme_settings (theme_name, setting_key, setting_value) 
                                  VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$themeName, $key, $val]);
        }
    }
    echo "<div class='alert alert-success'>" . __('settings_saved') . "</div>";
}

// Mevcut değerleri çek
$stmt = $db->prepare("SELECT setting_key, setting_value FROM theme_settings WHERE theme_name = ?");
$stmt->execute([$themeName]);
$currentValues = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<div class="container py-2">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php?page=themes" class="btn btn-sm btn-outline-secondary me-3 shadow-sm rounded-pill">
            <i class="fas fa-arrow-left"></i> <span lang="back_to_home"><?= __('back_to_home') ?></span>
        </a>
        <h4 class="m-0 fw-bold"><i class="fas fa-palette text-primary me-2"></i>
            <?= e($settingsMeta['title'] ?? $themeName) ?>
        </h4>
    </div>

    <style>
        .theme-card {
            transition: transform 0.2s;
        }

        .form-label {
            font-size: 0.9rem;
            color: #555;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 0.6rem 0.8rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
        }

        .color-preview-swatch {
            transition: transform 0.2s;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .color-preview-swatch:hover {
            transform: scale(1.2);
        }

        .input-group-text-color {
            background-color: #fff;
            border-right: 0;
        }
    </style>

    <form method="POST">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="save_theme_settings" value="1">

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header py-3 border-bottom-0">
                <h6 class="m-0 fw-bold"><i class="fas fa-sliders-h me-2"></i> <span
                        lang="customize_settings"><?= __('customize_settings') ?></span></h6>
            </div>
            <div class="card-body px-4">
                <?php foreach ($settingsMeta['fields'] as $field): ?>
                    <div class="mb-4 pb-3 border-bottom border-light">
                        <label class="form-label fw-bold d-block mb-1">
                            <?= e($field['label']) ?>
                        </label>

                        <?php if (in_array($field['type'], ['text', 'email', 'url', 'number'])): ?>
                            <input type="<?= e($field['type']) ?>" name="<?= e($field['id']) ?>" class="form-control"
                                value="<?= e($currentValues[$field['id']] ?? $field['default'] ?? '') ?>"
                                <?php if($field['type'] === 'number'): ?>
                                    min="<?= e($field['min'] ?? '') ?>" max="<?= e($field['max'] ?? '') ?>" step="<?= e($field['step'] ?? '1') ?>"
                                <?php endif; ?>>

                        <?php elseif ($field['type'] == 'image'): 
                            $val = $currentValues[$field['id']] ?? $field['default'] ?? '';
                        ?>
                            <div class="image-picker-container" data-id="<?= e($field['id']) ?>">
                                <div class="input-group mb-2 shadow-sm rounded-3">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-image text-muted"></i></span>
                                    <input type="text" name="<?= e($field['id']) ?>" class="form-control border-start-0 image-url-input" 
                                        value="<?= e($val) ?>" placeholder="https://... veya /media/...">
                                </div>
                                <div class="preview-wrapper bg-light rounded-3 d-flex align-items-center justify-content-center p-2" style="min-height: 60px; border: 1px dashed #ddd;">
                                    <?php if($val): ?>
                                        <img src="<?= e($val) ?>" class="img-fluid rounded preview-img" style="max-height: 120px;">
                                    <?php else: ?>
                                        <div class="text-muted small no-preview"><i class="fas fa-eye-slash me-1"></i> <?= __('no_preview') ?></div>
                                        <img src="" class="img-fluid rounded preview-img d-none" style="max-height: 120px;">
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif ($field['type'] === 'textarea'): ?>
                            <textarea name="<?= e($field['id']) ?>" class="form-control"
                                rows="3"><?= e($currentValues[$field['id']] ?? $field['default'] ?? '') ?></textarea>

                        <?php elseif ($field['type'] === 'color'):
                            $val = $currentValues[$field['id']] ?? $field['default'] ?? '#000000';
                            $hexVal = $val;
                            // Ensure it's a valid 6-char hex for the color input
                            if (preg_match('/^#([0-9A-Fa-f]{3})$/', $val, $matches)) {
                                $hexVal = '#' . $matches[1][0] . $matches[1][0] . $matches[1][1] . $matches[1][1] . $matches[1][2] . $matches[1][2];
                            } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $val)) {
                                $hexVal = '#000000';
                            }
                            ?>
                            <div class="d-flex align-items-center color-picker-container" data-id="<?= e($field['id']) ?>">
                                <div class="input-group shadow-sm"
                                    style="max-width: 280px; border-radius: 8px; overflow: hidden;">
                                    <span class="input-group-text p-1 bg-white border-end-0">
                                        <input type="color" class="form-control form-control-color border-0 color-input"
                                            style="width: 40px; height: 32px; cursor: pointer; background: none;"
                                            value="<?= e($hexVal) ?>">
                                    </span>
                                    <input type="text" name="<?= e($field['id']) ?>"
                                        class="form-control border-start-0 hex-input font-monospace" placeholder="#000000"
                                        style="font-size: 0.9rem; text-transform: uppercase;" value="<?= e($val) ?>">
                                </div>
                                <div class="ms-3 color-preview-swatch rounded-circle shadow-sm"
                                    style="width: 24px; height: 24px; background-color: <?= e($hexVal) ?>;"></div>
                            </div>

                        <?php elseif ($field['type'] === 'checkbox'): ?>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="<?= e($field['id']) ?>"
                                    <?= ($currentValues[$field['id']] ?? $field['default'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label
                                    class="form-check-label text-muted"><?= e($field['description'] ?? __('activate_it')) ?></label>
                            </div>

                        <?php elseif ($field['type'] === 'select'): ?>
                            <select name="<?= e($field['id']) ?>" class="form-select">
                                <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                                    <option value="<?= e($optVal) ?>" <?= ($currentValues[$field['id']] ?? $field['default'] ?? '') == $optVal ? 'selected' : '' ?>>
                                        <?= e($optLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($field['type'] === 'radio'): 
                            $val = $currentValues[$field['id']] ?? $field['default'] ?? '';
                        ?>
                            <div class="mt-2 d-flex flex-wrap gap-3">
                                <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                                    <div class="form-check custom-radio-card p-0">
                                        <input class="form-check-input d-none" type="radio" name="<?= e($field['id']) ?>" 
                                            id="radio_<?= e($field['id'] . $optVal) ?>" value="<?= e($optVal) ?>"
                                            <?= $val == $optVal ? 'checked' : '' ?>>
                                        <label class="form-check-label btn btn-sm btn-outline-secondary px-3 rounded-pill" for="radio_<?= e($field['id'] . $optVal) ?>">
                                            <?= e($optLabel) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($field['type'] === 'range'): 
                            $val = $currentValues[$field['id']] ?? $field['default'] ?? '0';
                            $min = $field['min'] ?? '0';
                            $max = $field['max'] ?? '100';
                            $step = $field['step'] ?? '1';
                        ?>
                            <div class="d-flex align-items-center gap-3">
                                <input type="range" name="<?= e($field['id']) ?>" class="form-range flex-grow-1" 
                                    min="<?= e($min) ?>" max="<?= e($max) ?>" step="<?= e($step) ?>" 
                                    value="<?= e($val) ?>" oninput="this.nextElementSibling.innerText = this.value">
                                <span class="badge bg-secondary rounded-pill px-3" style="min-width: 45px;"><?= e($val) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($field['help'])): ?>
                            <div class="form-text small opacity-50 mt-1"><?= e($field['help']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer border-0 p-4 rounded-bottom-4">
                <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm">
                    <i class="fas fa-save me-2"></i> <span lang="save_changes"><?= __('save_changes') ?></span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const containers = document.querySelectorAll('.color-picker-container');

        containers.forEach(container => {
            const colorInput = container.querySelector('.color-input');
            const hexInput = container.querySelector('.hex-input');
            const swatch = container.querySelector('.color-preview-swatch');

            // Sync Color Picker -> Text Input
            colorInput.addEventListener('input', (e) => {
                const val = e.target.value.toUpperCase();
                hexInput.value = val;
                if (swatch) swatch.style.backgroundColor = val;
            });

            // Sync Text Input -> Color Picker
            hexInput.addEventListener('input', (e) => {
                let val = e.target.value;
                if (!val.startsWith('#')) val = '#' + val;

                // Basic HEX validation
                if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                    colorInput.value = val;
                    if (swatch) swatch.style.backgroundColor = val;
                } else if (/^#[0-9A-Fa-f]{3}$/.test(val)) {
                    // Support 3-digit hex
                    const r = val[1] + val[1];
                    const g = val[2] + val[2];
                    const b = val[3] + val[3];
                    const fullHex = '#' + r + g + b;
                    colorInput.value = fullHex;
                    if (swatch) swatch.style.backgroundColor = fullHex;
                }
            });
        });

        // Image Preview Logic
        const imageInputs = document.querySelectorAll('.image-url-input');
        imageInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const val = e.target.value;
                const container = e.target.closest('.image-picker-container');
                const img = container.querySelector('.preview-img');
                const noPrev = container.querySelector('.no-preview');
                
                if (val && (val.startsWith('http') || val.startsWith('/') || val.length > 5)) {
                    img.src = val;
                    img.classList.remove('d-none');
                    if(noPrev) noPrev.classList.add('d-none');
                } else {
                    img.classList.add('d-none');
                    if(noPrev) noPrev.classList.remove('d-none');
                }
            });
        });
    });
</script>
```