# 📘 SpeedPage

**SpeedPage** is a modular, panel-oriented Content Management System (CMS) designed for **high performance**, **flexibility**, and **developer-friendly workflows**. It now features a **Universal Installation Wizard** with support for both **SQLite** and **MySQL**.

> 🎯 **Goal:** To provide developers with a clean working environment through **fast setup**, **offline support**, **multi-language support**, and **flexible module management**.

---

## ⚠️ Disclaimer

This project is a **hobby & experimental** work.
Although various security measures (XSS filtering, SQL injection prevention, brute-force protection, etc.) are implemented, you **must perform your own security testing** before using SpeedPage in a production environment.

---

## 🚀 Key Features

### 🛠️ Universal Installer

* SQLite or MySQL selection
* Live MySQL connection tester
* Admin account creation
* Auto config generation (`settings.php`)
* Smart schema deployment (all tables auto-created)
* Auto-detection of `BASE_URL` and `BASE_PATH`
* Installation lock system (prevents re-installation)

### 🧩 Modular Architecture

* Upload modules as ZIP
* Enable / disable modules
* Hook system for extensions
* Unified management of pages, menus, and assets

### 🎨 Theme System

* Upload themes as ZIP
* Activate / deactivate themes
* Theme settings stored in database
* Sidebar, color scheme, footer and layout options

### 🤖 Admin AI Assistant

* Integrated into admin panel
* Debugging assistant
* Code analysis
* Small patch generation
* Supports OpenRouter / OpenAI-compatible APIs

### 🛡️ Smart Maintenance

* SQLite WAL mode support
* Auto installer lock
* Self-healing schema logic
* Error capturing hooks

### 🌍 PWA Ready

* Offline support
* `manifest.json`
* `service-worker.js`

### 👥 User System

* Roles: Admin, Editor, User
* Profile management
* Avatar selection
* Permission-based access

### 🔄 Database Migration

* Built-in wizard
* Migrate from SQLite → MySQL
* No data loss

---

## ⚙️ Installation

1. Upload the files to your localhost or server.
2. Open your site in the browser:

   ```
   http://localhost/speedpage
   ```
3. You will be redirected to the installer: `install.php`
4. Follow the steps:

   * Choose database (SQLite or MySQL)
   * Test MySQL connection (if selected)
   * Create admin account
   * Let the system auto-configure everything
5. When you see the **Success** message:

> ❗ **Delete `install.php` immediately**

---

## 📦 Modules & Themes Repository

Ready-made modules and themes:
🔗 [https://github.com/snrj35-dev/SpeedPage-modul-theme](https://github.com/snrj35-dev/SpeedPage-modul-theme)

### Structure

* `module/` → Feature modules
* `theme/` → UI themes
* `tools/` → System tools

  * `onar.php` → Repair tool (creates missing tables, fixes structure)

> ⚠️ Remove all tools before going live

---

## 📚 Libraries Used

* Bootstrap 5
* Font Awesome 6
* Chart.js
* Marked.js
* Highlight.js

---

## 🛠️ Technologies

**Backend:** PHP 8.3+

**Database:** SQLite or MySQL

**Frontend:** Bootstrap, FontAwesome

**AI Integration:** OpenAI-compatible APIs

---

## 🧪 Development Status

Current version: **v0.2 Alpha**
