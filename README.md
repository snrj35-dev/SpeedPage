# 📘 SpeedPage

**SpeedPage** — An **SQLite-based**, modular, and panel-oriented Content Management System (CMS) designed to run on Localhost.
**Goal:** To provide developers with a clean working environment through **fast setup**, **offline support**, **multi-language support**, and **flexible module management**.

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


3. Edit the `settings.php` file according to your setup:

```php
// If running in the root directory:
define('BASE_PATH', '/');

// If running in a subfolder (example):
define('BASE_PATH', '/newsite/');

// For Localhost:
define('BASE_URL', 'http://localhost' . BASE_PATH);

// For your domain:
define('BASE_URL', 'http://yourdomain.com' . BASE_PATH);

```

4. **service-worker.js**: Ensure `const BASE_PATH = "/";` matches your `settings.php` configuration. Also, edit `manifest.json` as needed.
5. Open the project in your browser and start using it.

---

## 🖥️ Admin Panel Features

* **Settings:** Manage site-wide settings (site name, login/registration toggles, etc.).
* **Pages:** Create, edit, delete, and manage pages (HTML, PHP, JS, CSS support) and integrate them into menus.
* **Menu Management:** Add, remove, or delete menus; set page links or external URLs.
* **Modules:** Activate or deactivate features based on pages, menus, and assets (e.g., upload `kitaplik.zip` from the `mods` folder).
* **Users:** Add, edit, or delete users with specific roles (**Admin / Editor / User**).
* **File Manager:** Manage files and folders (create, delete, rename, upload/extract ZIP files) with a built-in basic text editor.
* **Database Panel:** Live management of **SQLite** tables.
* **System Panel:** View detailed information about the system.

---

## 🚀 Key Features

* **SQLite DB:** Lightweight, fast, and optimized for local usage.
* **Modular Architecture:** Unified management of pages, menus, modules, and assets.
* **PWA Compatibility:** Offline functionality via `manifest.json` and `service-worker.js`.
* **Multi-language Support:** JSON-based `lang.js` infrastructure (TR/EN ready, easily expandable).
* **Profile Management:** Simple profile pages with avatar selection and display name changes.

---

## 🛠️ Technologies Used

* **Backend:** PHP 8+, SQLite
* **Frontend:** Modular file structure including `menu.js`, `modules.js`, `pages.js`, etc.
* **UI/UX:** CSS (Minimalist and functional design).

---

## 📂 Database Structure

Primary tables:

* `pages` — Page management
* `menus` + `menu_locations` — Menu system
* `modules` — Module management
* `page_assets` + `module_assets` — Asset control
* `users` — User roles and credentials
* `settings` — System configurations

---

## 📦 Module Example

A sample **library module** (`kitaplik.zip`) is included in the `mods` folder. More examples will be added in the future. To install, simply go to the Admin Panel, select "Add New Module," and upload the ZIP file. Those who wish to create their own modules can examine the structure of the provided ZIP file.

---

Türkçe Açıklama

# 📘 SpeedPage

**SpeedPage** — Localhost üzerinde çalışan, **SQLite tabanlı**, modüler ve panel odaklı bir içerik yönetim sistemi.  
Amaç: **hızlı kurulum**, **offline destek**, **çoklu dil desteği** ve **esnek modül yönetimi** ile geliştiricilere temiz bir çalışma ortamı sağlamak.

---

##  Kullanılan Kütüphaneler
- [Bootstrap](https://getbootstrap.com/)  
- [Font Awesome](https://fontawesome.com/)  
* [Chart.js](https://www.chartjs.org/)
---

##  Kurulum
1. Dosyaları **localhost** veya kendi alan adınıza yükleyin.  
2. Varsayılan **admin paneli** giriş bilgileri:  
   - Kullanıcı adı: `admin`  
   - Şifre: `admin`  

3. `settings.php` dosyasını kendi kurulumunuza göre düzenleyin:  

// Kök dizinde çalışacaksanız:
define('BASE_PATH', '/');

// Örneğin alt klasörde çalışacaksanız:
define('BASE_PATH', '/yenisite/');

// Localhost için:
define('BASE_URL', 'http://localhost' . BASE_PATH);

// Alan adınız için:
define('BASE_URL', 'http://alanadiniz.com' . BASE_PATH);

4.service-worker.js 
const BASE_PATH = "/"; // settings.php ile aynı olmalı
manifest.json  kendinize göre düzenleyin 

5. Tarayıcıdan projenizi açın ve kullanmaya başlayın.  

---

## 🖥️ Admin Panel Özellikleri
- **Ayarlar → Site geneli bazı küçük ayarlar,site ismi , login kayıt vs..
- **Sayfalar → yeni sayfalar ekleme menüye dahil etme düzenleme silme html php js css vs..
- **Menü Yönetimi → yeni menü ekleme çıkarma silme sayfa belirleme harici link verme vs..
- **Modüller → Sayfa, menü, modül ve asset bazlı yönetim.etkinliştirme ve devredışı bırakma(örnek: mods klasöründeki kitaplık.zip yükleyin..)
- **Kullanıcılar → yeni kullanıcı ekleme düzenleme silme roller(Admin / Editor / User)
- **Dosya Yöneticisi → dosya , klasör ekleme silme düzenleme isimlendirme zip indir yükle çıkart basit text editör 
- **Veritabanı Paneli → SQLite tablolarını canlı yönetim  
- **system paneli** → Sistem Hakkında Bilgiler  

---

## 🚀 Diğer Özellikler
- **SQLite DB** → Hafif, hızlı ve local kullanım için optimize.
- **Modüler Mimari** → Sayfa, menü, modül ve asset bazlı yönetim.
- **PWA Uyumluluğu** → `manifest.json` + `service-worker.js` ile offline çalışma.
- **Çoklu Dil Desteği** → JSON tabanlı `lang.js` altyapısı (TR/EN hazır, kolay genişletilebilir).
- **basit profil sayfası avatar seçme görünen isim değişim vs..
---

## 🛠️ Kullanılan Teknolojiler
- **Backend:** PHP 8+, SQLite
- **Frontend:** modüler dosya yapısı: `menu.js`, `modules.js`, `pages.js` vs..
- **UI/UX:** CSS (minimalist ve işlevsel tasarım)

---

## 📂 Veritabanı Yapısı
Ana tablolar:
- `pages` → Sayfa yönetimi
- `menus` + `menu_locations` → Menü sistemi
- `modules` → Modül yönetimi
- `page_assets` + `module_assets` → Asset kontrolü
- `users` → Kullanıcı rolleri
- `settings` → Sistem ayarları

---

## Modül Örneği
`mods` klasöründe örnek bir **kitaplık modülü** (`kitaplik.zip`) bulunmaktadır. ilerde daha fazla örnek bulunacak admin panelden yeni modül ekle deyip zipli dosyayı eklemeniz yeterli, kendi modülünü oluşturmak isteyenler zip dosyasını incelesin... 

---
