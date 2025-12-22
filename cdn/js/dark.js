
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const icon = themeToggleBtn.querySelector("i");

    // 1. Mantık: Kayıtlı tema varsa onu kullan, yoksa sistem tercihine bak
    if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
        document.documentElement.setAttribute('data-theme', 'dark');
        if(icon) icon.className = "fas fa-sun";
    } else {
        document.documentElement.setAttribute('data-theme', 'light');
        if(icon) icon.className = "fas fa-moon";
    }
});

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const toggleBtn = document.getElementById('theme-toggle');
    const icon = toggleBtn.querySelector("i");
    
    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');
        if(icon) icon.className = "fas fa-moon";
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
        if(icon) icon.className = "fas fa-sun";
    }
}



