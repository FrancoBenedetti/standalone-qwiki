# Installation & Quick Start Guide

Welcome to **Standalone Qwiki**! This document explains how to set up and configure your documentation workspace.

---

## 🚀 Quick Start (Auto-Setup)

1. **Download the Latest Release**:
   Download the latest release `.zip` from GitHub and extract it to your web server root or any subfolder (e.g. `/help`, `/docs`, `/wiki`).

2. **Ensure Write Permissions**:
   Ensure your web server process has write access to the folder so it can initialize files:
   ```bash
   chmod -R 775 /path/to/your/qwiki-folder
   chown -R www-data:www-data /path/to/your/qwiki-folder
   ```

3. **Open in Browser**:
   Navigate to your URL (e.g. `http://your-domain.com/docs/`). 
   Qwiki will automatically detect a fresh installation, run the **Auto-Setup** script, and initialize `qwiki.json`, `users.json`, `content/`, and `uploads/` using the included demo data.

4. **Login as Administrator**:
   Click **Login** in the top-right header:
   - **Username**: `admin`
   - **Password**: `admin`

---

## ⚙️ Configuration Structure (`qwiki.json`)

Your wiki tree, settings, and navigation are stored in `qwiki.json`:

```json
{
  "title": "Standalone Qwiki",
  "theme": "theme-default.css",
  "defaultBook": "getting-started",
  "requireLoginToView": false,
  "books": [
    {
      "id": "getting-started",
      "title": "Getting Started",
      "type": "folder",
      "visibility": "public",
      "items": [
        {
          "title": "Introduction",
          "slug": "introduction",
          "type": "markdown",
          "file": "content/getting-started/introduction.md"
        },
        {
          "title": "Interactive Dashboard",
          "slug": "interactive-dashboard",
          "type": "html",
          "file": "content/getting-started/interactive-dashboard.html"
        },
        {
          "title": "PDF Manual",
          "slug": "sample-pdf",
          "type": "pdf",
          "file": "content/getting-started/sample.pdf"
        }
      ]
    }
  ]
}
```

---

## 🧩 Installing Extensions

Drop any extension folder into `assets/extensions/{extension-name}/`:
- **`page-html`**: Native HTML documents with the SunEditor visual WYSIWYG editor.
- **`tool-ai-visuals`**: Vector chart and flow diagram generator.
- **`tool-gallery`**: Media and image assets gallery for browsing and inserting uploaded images.
- Custom extensions are automatically discovered and safely preserved during 1-click core updates.

---

> [!IMPORTANT]
> **Security Reminder**
> Immediately after installation, please open the **`👥 Users`** or **`⚙️ Settings`** panel and change the default `admin` password to secure your site.

> [!TIP]
> **Post-Installation Cleanup**
> The sample documentation sections are provided for illustration purposes. You can modify, reorder, or delete them from the Admin interface once you are familiar with the system.
