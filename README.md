# 📘 SpeedPage (English Version)

**SpeedPage** — An **SQLite-based**, modular, and panel-oriented Content Management System (CMS) designed to run on Localhost.  
**Goal:** To provide developers with a clean working environment through **fast setup**, **offline support**, **multi-language support**, and **flexible module management**.

**Important Note (Disclaimer)**  
This project is a hobby work. Although many security measures (XSS protection, SQL injection prevention, Brute Force protection) have been implemented, we strongly recommend that you perform your own security tests before making the project live (public).

---

## 📚 Libraries Used
* [Bootstrap](https://getbootstrap.com/)  
* [Font Awesome](https://fontawesome.com/)  
* [Chart.js](https://www.chartjs.org/)

---

## ⚙️ Installation
1. Upload the files to your **localhost** or your own domain.  
2. Default **Admin Panel** login credentials:  
   * **Username:** `admin`  
   * **Password:** `admin`  
3. Edit the `settings.php` file according to your setup.  
4. Adjust `service-worker.js` and `manifest.json` according to your `BASE_PATH`.  
5. Open the project in your browser and start using it.  

---

## 🚀 Features
- **SQLite DB** → Lightweight, fast, optimized for local usage.  
- **Modular Architecture** → Unified management of pages, menus, modules, and assets.  
- **Admin Panel** →  
  - Site settings (name, login/register toggles)  
  - Page management (create, edit, delete, integrate into menus)  
  - Menu management (add/remove menus, external links)  
  - Module management (activate/deactivate, upload ZIP)  
  - Theme management (activate/deactivate, upload ZIP)  
  - User management (Admin / Editor / User roles)  
  - File manager (manage files/folders, upload/extract ZIP, basic text editor)  
  - Database panel (live SQLite table management)  
  - System panel (detailed system information)  
- **PWA Compatibility** → Offline functionality with `manifest.json` + `service-worker.js`.  
- **Multi-language Support** → JSON-based `lang.js` (TR/EN ready, easily expandable).  
- **Profile Management** → Avatar selection, display name changes.  
- **Hooks System** → Extend functionality with custom hooks.  
- **Log System** → Basic logging for actions and events.  
- **Mobile-Friendly** → Improved responsive design.  
- **Admin/Site Separation** → Clear distinction between admin panel and site frontend.  

---

## 📦 [SpeedPage-modul-theme](https://github.com/snrj35-dev/SpeedPage-modul-theme) 

- **module/** → Ready-made modules can be downloaded from the link above and installed via the admin panel.  
  
- **theme/** → Ready-made themes are available in the external repository.  
  - The default theme is already integrated.  
  - New themes can be downloaded from the repository and uploaded as ZIP files through the admin panel.

- **tools/** → Tools can be downloaded here and run on your system.  
  - `onar.php` → **Repair Tool** (⚠️ Resets the database and deletes test data. Use only before publishing or cleaning up. Do not keep this file on production servers.)


---

## 🛠️ Technologies Used
- **Backend:** PHP 8+, SQLite  
- **Frontend:** Modular JS (`menu.js`, `modules.js`, `pages.js`, etc.)  
- **UI/UX:** CSS (Minimalist and functional design)  

---

# 📘 SpeedPage (Türkçe Versiyon)

**SpeedPage** — Localhost üzerinde çalışan, **SQLite tabanlı**, modüler ve panel odaklı bir içerik yönetim sistemi.  
**Amaç:** Geliştiricilere **hızlı kurulum**, **offline destek**, **çoklu dil desteği** ve **esnek modül yönetimi** ile temiz bir çalışma ortamı sağlamak.

**Önemli Not (Disclaimer)**  
Bu proje bir hobi çalışmasıdır. Birçok güvenlik önlemi (XSS koruması, SQL injection önleme, Brute Force koruması) alınmış olsa da, projeyi canlı (public) ortama açmadan önce mutlaka kendi güvenlik testlerinizi yapmanızı öneririz.

---

## 📚 Kullanılan Kütüphaneler
- [Bootstrap](https://getbootstrap.com/)  
- [Font Awesome](https://fontawesome.com/)  
- [Chart.js](https://www.chartjs.org/)

---

## ⚙️ Kurulum
1. Dosyaları **localhost** veya kendi alan adınıza yükleyin.  
2. Varsayılan **admin paneli** giriş bilgileri:  
   - Kullanıcı adı: `admin`  
   - Şifre: `admin`  
3. `settings.php` dosyasını kendi kurulumunuza göre düzenleyin.  
4. `service-worker.js` ve `manifest.json` dosyalarını `BASE_PATH` ayarınıza göre düzenleyin.  
5. Tarayıcıdan projenizi açın ve kullanmaya başlayın.  

---

## 🚀 Özellikler
- **SQLite DB** → Hafif, hızlı, local kullanım için optimize.  
- **Modüler Mimari** → Sayfa, menü, modül ve asset bazlı yönetim.  
- **Admin Paneli** →  
  - Site ayarları (isim, login/register seçenekleri)  
  - Sayfa yönetimi (ekleme, düzenleme, silme, menü entegrasyonu)  
  - Menü yönetimi (ekleme, çıkarma, harici linkler)  
  - Modül yönetimi (aktif/pasif etme, ZIP yükleme)  
  - Tema yönetimi (aktif/pasif etme, ZIP yükleme)  
  - Kullanıcı yönetimi (Admin / Editor / User rolleri)  
  - Dosya yöneticisi (dosya/klasör işlemleri, ZIP yükleme/çıkarma, basit editör)  
  - Veritabanı paneli (SQLite tablolarını canlı yönetim)  
  - Sistem paneli (detaylı sistem bilgileri)  
- **PWA Uyumluluğu** → `manifest.json` + `service-worker.js` ile offline çalışma.  
- **Çoklu Dil Desteği** → JSON tabanlı `lang.js` (TR/EN hazır, kolay genişletilebilir).  
- **Profil Yönetimi** → Avatar seçme, görünen isim değiştirme.  
- **Hooks Sistemi** → Siteye özel fonksiyonlar eklenebilir.  
- **Log Sistemi** → Basit log kaydı ile aksiyon takibi.  
- **Mobil Uyumluluk** → Responsive tasarım geliştirilmiş.  
- **Admin/Site Ayrımı** → Yönetim paneli ve site arayüzü net şekilde ayrıldı.  

---

## 📦 [SpeedPage-modul-theme](https://github.com/snrj35-dev/SpeedPage-modul-theme) 

- **modul/** → Hazır Modüller üstteki linkten indirip admin panelden yükleyebilirsiniz.  
  
- **theme/** → Hazır Temalar harici repodan indirilir.  
  - Varsayılan tema entegre edilmiştir.  
  - Yeni temaları reposundan indirip ZIP olarak admin panelden yükleyebilirsiniz.

- **tools/** → Araçlar buradan indirip sisteminizde çalıştırabilirsiniz.  
  - `onar.php` → **Onarım Aracı** (⚠️ Veritabanını sıfırlar ve test verilerini siler. Yalnızca yayına çıkmadan önce veya temizlik için kullanın. Production ortamında tutmayın.)


---

## 🛠️ Kullanılan Teknolojiler
- **Backend:** PHP 8+, SQLite  
- **Frontend:** Modüler JS (`menu.js`, `modules.js`, `pages.js` vb.)  
- **UI/UX:** CSS (Minimalist ve işlevsel tasarım)  

---
