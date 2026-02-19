# 📘 SpeedPage CMS (v0.4)

SpeedPage — A modular, panel-oriented Content Management System (CMS) designed for high performance and flexibility. Now featuring a Universal Installation Wizard with support for both SQLite and MySQL.

---

## 🤖 SpeedPage Dev-Bot (Interactive Guide)

**Turkish & English Support Available!**  
If you have any questions about the project, file structure, or how to create modules, you can chat with our **Interactive Assistant**. It knows everything about the codebase!

👉 **[Launch Interactive Chatbot / İnteraktif Asistanı Başlat](https://snrj35-dev.github.io/SpeedPage-modul-theme/)**

---

## 🇹🇷 Türkçe Özet
SpeedPage, yüksek performans ve esneklik için tasarlanmış, modüler ve panel odaklı bir İçerik Yönetim Sistemidir (CMS). SQLite ve MySQL desteği sunan Evrensel Kurulum Sihirbazı ile birlikte gelir. Proje yapısı, dosyalar veya modül geliştirme hakkında merak ettiklerinizi yukarıdaki **İnteraktif Bot** üzerinden hem Türkçe hem İngilizce olarak sorabilirsiniz.

---

## 🚀 Key Updates (v0.4)
### 🛠️ Universal Installer
- **Agnostic DB Support:** Switch between SQLite and MySQL with a single click.
- **Smart Schema Deployment:** Automatically creates all tables with pre-configured default values.
- **Dynamic settings.php:** Automatically detects your BASE_URL and updates configurations.

### 🤖 Admin AI Assistant
- Integrated into the panel, capable of analyzing code errors and applying patches directly using OpenRouter/OpenAI API.

### 🛡️ Smart Maintenance & Security
- **WAL Mode Support:** Optimized SQLite performance.
- **Auto-Installer Lock:** Prevents re-installation if the system is already configured.

---

## 📦 Core Features
- **Dual Database Engine:** Use SQLite for lightweight portability or MySQL for heavy traffic.
- **Theme Management:** Manage Theme Settings directly from the DB.
- **PWA Ready:** Native support for manifest.json and service-worker.js.
- **Modular Architecture:** Upload ZIP modules/themes through the admin panel.
- **Database Migration:** Built-in wizard to move data from SQLite to MySQL seamlessly.

---

## ⚙️ Installation
1. Upload files to your server.
2. Open your site in the browser.
3. You will be redirected to `install.php` (Wizard).
4. Follow the steps (Database Choice, Admin Setup).
5. **Security:** Delete `install.php` after success.

---

## 🛠️ Technologies
- **Backend:** PHP 8.3+
- **Database:** SQLite / MySQL
- **Frontend:** HTML5, CSS3 (Glassmorphism), Vanilla JS
- **AI Integration:** OpenAI-compatible API support.

---

## 📚 Libraries Used
- Bootstrap 5, Font Awesome 6, Chart.js, Marked.js, Highlight.js
