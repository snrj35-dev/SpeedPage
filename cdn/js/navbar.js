/**
 * SpeedPage Navbar & Notifications Core
 */

document.addEventListener('DOMContentLoaded', function () {
    // === 1. Mobile Sidebar Logic ===
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const closeBtn = document.getElementById('mobile-menu-close');
    const sidebar = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('mobile-sidebar-overlay');

    function toggleSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
    if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
    if (overlay) {
        overlay.classList.remove('active');
        overlay.addEventListener('click', toggleSidebar);
    }

    // === 2. Notification System Logic ===
    const navRight = document.querySelector('.user-profile-nav');
    if (!navRight) return; // Only run if user is logged in (nav element exists)

    const notiWrapper = document.createElement('div');
    notiWrapper.className = 'notifications-wrapper';
    notiWrapper.innerHTML = `
        <button class="notifications-btn" id="global-noti-toggle" title="Bildirimler">
            <i class="far fa-bell fs-5"></i>
            <span class="notifications-badge" id="global-noti-unread" style="display:none">0</span>
        </button>
        <div class="notifications-dropdown" id="global-noti-dropdown">
            <div class="notifications-header">
                <span>Bildirimler</span>
                <button class="btn btn-link btn-sm text-decoration-none p-0" id="global-noti-mark-all">Hepsini Oku</button>
            </div>
            <div class="notifications-list" id="global-noti-list">
                <div class="p-4 text-center text-muted small">
                    <i class="fas fa-circle-notch fa-spin me-2"></i>Yükleniyor...
                </div>
            </div>
        </div>
    `;
    navRight.prepend(notiWrapper);

    const toggle = document.getElementById('global-noti-toggle');
    const dropdown = document.getElementById('global-noti-dropdown');
    const listEl = document.getElementById('global-noti-list');
    const badge = document.getElementById('global-noti-unread');

    async function loadNotifications() {
        const formData = new FormData();
        formData.append('action', 'get_notifications');
        const url = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'php/notifications-action.php';
        try {
            const res = await fetch(url, { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) return;

            const items = data.data.list || [];
            const unread = data.data.unread || 0;

            if (unread > 0) {
                badge.textContent = unread;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }

            if (!items.length) {
                listEl.innerHTML = '<div class="p-4 text-center text-muted small">Henüz bildirim yok.</div>';
                return;
            }

            listEl.innerHTML = items.map(n => {
                const escapeHTML = (str) => String(str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
                let msg = '';
                let actorName = escapeHTML(n.actor_username || '');
                let contentText = escapeHTML(n.content || '');

                if (n.action_type === 'new_user') {
                    msg = `Yeni üye kaydı: <b>${contentText || actorName}</b>`;
                } else if (n.action_type === 'new_page') {
                    msg = `Yeni içerik yayınlandı: <b>${contentText}</b>`;
                } else if (n.action_type === 'mention') {
                    msg = `<b>@${actorName}</b> bir mesajda senden bahsetti.`;
                } else if (n.action_type === 'reaction') {
                    msg = `<b>@${actorName}</b> mesajına tepki verdi.`;
                } else if (n.action_type === 'reply') {
                    msg = `<b>@${actorName}</b> sana cevap yazdı.`;
                } else {
                    msg = contentText || 'Yeni bildirim';
                }

                let targetUrl = '#';
                const base = (typeof BASE_URL !== 'undefined' ? BASE_URL : '');
                if (n.target_type === 'system' && n.action_type === 'new_page' && n.target_id) {
                    targetUrl = base + 'index.php';
                } else if (n.target_type === 'system' && n.action_type === 'new_user') {
                    targetUrl = base + 'admin/index.php?page=users';
                } else if (n.target_type === 'forum') {
                    targetUrl = base + '?page=forum';
                }

                const created = n.created_at ? new Date(n.created_at).toLocaleString() : '';

                return `
                    <a href="${targetUrl}"
                       class="notifications-item ${n.is_read == 0 ? 'unread' : ''}"
                       data-id="${n.id}">
                        <div class="notifications-avatar"><i class="fas ${n.actor_avatar || 'fa-user'}"></i></div>
                        <div class="notifications-content">
                            <div>${msg}</div>
                            <div class="text-muted extra-small mt-1">${created}</div>
                        </div>
                    </a>
                `;
            }).join('');
        } catch (e) {
            console.error('Notification load error', e);
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
            if (dropdown.classList.contains('active')) {
                loadNotifications();
            }
        });
    }

    document.addEventListener('click', () => {
        if (dropdown) dropdown.classList.remove('active');
    });

    const markAllBtn = document.getElementById('global-noti-mark-all');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', async function (e) {
            e.stopPropagation();
            const formData = new FormData();
            formData.append('action', 'mark_notifications_read');
            const url = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'php/notifications-action.php';
            await fetch(url, { method: 'POST', body: formData });
            if (badge) badge.style.display = 'none';
            if (dropdown) dropdown.classList.remove('active');
        });
    }

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            const item = e.target.closest('.notifications-item');
            if (!item) return;
            const id = item.getAttribute('data-id');
            const formData = new FormData();
            formData.append('action', 'mark_notification_read');
            formData.append('id', id);
            const url = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'php/notifications-action.php';
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, formData);
            } else {
                fetch(url, { method: 'POST', body: formData, keepalive: true });
            }
        });
    }

    // Initial load
    loadNotifications();

    // Refresh interval (60 seconds)
    setInterval(loadNotifications, 60000);
});
