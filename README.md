📘 SpeedPage

SpeedPage — A modular, panel-oriented Content Management System (CMS) designed for high performance and flexibility. Now featuring a Universal Installation Wizard with support for both SQLite and MySQL.

Goal: To provide developers with a clean working environment through fast setup, offline support, multi-language support, and flexible module management.
⚙️ Installation (New & Simplified)

    Upload the files to your localhost or your server.

    Open your site in the browser (e.g., localhost/speedpage).

    You will be automatically redirected to the Installation Wizard (install.php).

    Follow the steps:

        Database Choice: Select SQLite (for zero-config/local) or MySQL (for production).

        Connection Test: Integrated tool to verify your MySQL credentials before installing.

        Admin Setup: Define your admin username and password during installation.

        Auto-Config: The system automatically writes your settings.php and creates the admin/veritabanı/data.db (if SQLite is chosen).

    Security: Delete install.php after the "Success" message.

🚀 Key Updates (v0.3 Alpha - Installer Edition)
🛠️ Universal Installer

    Agnostic DB Support: Switch between SQLite and MySQL with a single click.

    Smart Schema Deployment: Automatically creates all tables (Pages, Users, Settings, Themes, AI Providers, etc.) with pre-configured default values.

    Theme Settings Integration: Now pre-installs default theme configurations (colors, sidebar positions, footer links) into the database.

    Dynamic settings.php: Automatically detects your BASE_URL and BASE_PATH and updates your configuration file.

🤖 Admin AI Assistant

    Integrated into the panel, capable of analyzing code errors and applying small patches directly to files using OpenRouter/OpenAI API.

🛡️ Smart Maintenance & Security

    WAL Mode Support: Optimized SQLite performance to prevent database locks.

    Auto-Installer Lock: Prevents re-installation if the system is already configured.

📦 Core Features

    Dual Database Engine → Use SQLite for lightweight portability or MySQL for heavy traffic.

    Theme Management → Activate/deactivate themes and manage Theme Settings directly from the DB.

    PWA Ready → Native support for manifest.json and service-worker.js.

    Modular Architecture → Upload ZIP modules/themes through the admin panel.

    User Roles → Granular control with Admin, Editor, and User roles.

    Database Migration → Built-in wizard to move your data from SQLite to MySQL seamlessly.

📚 Libraries Used

    Bootstrap 5

    Font Awesome 6

    Chart.js

    Marked.js & Highlight.js (for AI Chat UX)

🛠️ Technologies

    Backend: PHP 8.3+ (Optimized for modern performance)

    Database: SQLite (File-based) or MySQL (Server-based)

    AI Integration: OpenAI-compatible API support via Admin Panel.
