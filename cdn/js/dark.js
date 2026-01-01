// 1. Sayfa yüklenir yüklenmez temayı uygula (Parlamayı önlemek için)
(function () {
    const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', savedTheme);
})();

// 2. Tema değiştirme fonksiyonu (Global)
window.toggleTheme = function () {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    // Tüm butonlardaki ikonları güncelle
    document.querySelectorAll('#theme-toggle i').forEach(icon => {
        icon.className = (newTheme === 'dark') ? 'fas fa-sun' : 'fas fa-moon';
    });

    console.log("SpeedPage: Tema " + newTheme + " moduna alındı. (HTML data-theme updated)");
};

// 3. Olay Dinleyicisi (Delegation)
document.addEventListener('click', function (e) {
    const toggleBtn = e.target.closest('#theme-toggle');
    if (toggleBtn) {
        toggleTheme();
    }
});

// 4. Sayfa yüklendiğinde ikonları senkronize et
document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    document.querySelectorAll('#theme-toggle i').forEach(icon => {
        icon.className = (currentTheme === 'dark') ? 'fas fa-sun' : 'fas fa-moon';
    });
});