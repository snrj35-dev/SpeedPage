<?php
// page.php zaten settings.php ve db.php'yi include ediyor
// burada tekrar include etmeye gerek yok

global $db;

/* Anasayfa hariç aktif sayfalar */
$pages = $db->query("
    SELECT slug, title, description, icon
    FROM pages
    WHERE is_active = 1
      AND slug != 'home'
    ORDER BY sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mt-5">
    <div class="grid-wrapper">
        <?php foreach ($pages as $p): ?>
            <div class="app-card">
                <div class="app-card-icon">
                    <i class="fa <?= htmlspecialchars($p['icon']) ?>"></i>
                </div>

                <div class="app-card-title">
                    <?= htmlspecialchars($p['title']) ?>
                </div>

                <div class="app-card-desc">
                    <?= htmlspecialchars($p['description']) ?>
                </div>

                <a href="?page=<?= htmlspecialchars($p['slug']) ?>">
                    <span lang="go_content">İçeriğe Git</span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mobile-list">
        <?php foreach ($pages as $p): ?>
            <div class="mobile-item">
                <i class="fa <?= htmlspecialchars($p['icon']) ?>"></i>

                <div>
                    <div class="mobile-text-title">
                        <?= htmlspecialchars($p['title']) ?>
                    </div>
                    <div class="mobile-text-desc">
                        <?= htmlspecialchars($p['description']) ?>
                    </div>
                </div>

                <a href="?page=<?= htmlspecialchars($p['slug']) ?>"><span lang="go">Git</span></a>
            </div>
        <?php endforeach; ?>
    </div>
</div>