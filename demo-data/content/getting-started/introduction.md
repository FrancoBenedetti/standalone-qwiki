# ⚡ Standalone Qwiki - Live Interactive Demo

<div class="alert-box alert-warning" style="background: var(--bg-secondary, #f8f9fa); border: 1px solid rgba(245, 158, 11, 0.35); border-left: 5px solid #f59e0b; padding: 1.25rem 1.5rem; border-radius: 6px; margin: 1.5rem 0 2rem 0;">
<div style="font-size: 1.15rem; font-weight: 700; color: #d97706; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
<span>⚠️</span> <span>Interactive Demo Environment</span>
</div>
<p style="margin: 0 0 0.5rem 0; font-size: 0.95rem; line-height: 1.5;">Welcome to the official interactive demo of <strong>Standalone Qwiki</strong>! You are free to explore, create new pages, upload documents, test drag-and-drop reordering, and try out administrative features.</p>
<p style="margin: 0; font-size: 0.9rem; color: var(--text-muted, #6b7280); line-height: 1.5;"><strong>🔄 Periodic Reload Warning:</strong> This demo environment is <strong>automatically reloaded periodically</strong> back to a clean state. Any documents, categories, user accounts, or media uploads added by visitors will be wiped clean during each scheduled reset.</p>
</div>

---

## 🔑 How to Access the Demo

Anyone can browse and search this demo package without signing in. To test administrative capabilities, content editing, file uploads, and page creation:

1. Click the **`Login`** button in the top right corner of the header.
2. Use the default demo administrator credentials:
   - **Username**: `admin`
   - **Password**: `admin`
3. Once logged in, the admin control bar, action buttons, and drag handles (`⣿`) become active.

---

## 🎯 What to Try in This Demo Package

Here are the key features and workflows you can test right now:

### 1. Create and Edit Content
- Click **`+ Add Document`** in the top navigation bar to create a new **Markdown** article, interactive **HTML** page, embed a published **Google Doc**, or upload a **PDF**.
- Note: This **Introduction** landing page is protected as **read-only** in the demo environment to preserve the guide for all visitors. You can test inline editing and WYSIWYG modes on any other document or any new pages you create!

### 2. Drag-and-Drop Menu Reordering
- In the sidebar menu, click and drag the **`⣿` handles** next to document titles or categories.
- Reorder documents, nest items inside folders, or move categories up and down. All changes sync immediately.

### 3. Agentic Visual & Chart Generator
- Click **`✨ AI Visuals`** in the top navigation bar.
- Generate standalone vector SVGs (Bar Charts, Line Trends, Pie/Donut Charts, Flow Diagrams, or Status Badges) and insert them into your Markdown documents with 1 click.

### 4. Zero-Flicker Themes & Live CSS Editor
- Click the **Dark / Light theme toggle** (`🌙` / `☀️`) in the header for instantaneous, zero-flicker theme switching.
- Navigate to **`Admin User Guide > Themes & Styling`** to see custom category themes or open **`🎨 Theme Editor`** from the Settings menu.

### 5. Multi-User Management & Access Modes
- Open **`👥 Users`** in the header to view user accounts or create new Viewer / Admin users.
- Open **`⚙️ Settings`** to toggle between **Public Access** and **Private Portal** modes, change site branding, or copy RSS feeds.

---

## 🔄 Demo Reload Utility

Standalone Qwiki includes a built-in reload engine (`DemoManager`) and reload script (`demo-reload.php`) designed for automated cron jobs and server administration:

1. **Scheduled Reset**: In public demo environments, configure a standard cron job to reset the demo periodically:
```bash
*/30 * * * * php /var/www/html/demo-reload.php --quiet
```

2. **On-Demand Reset**: Authenticated Admins can also reset the demo immediately via the **`⚙️ Settings`** modal by clicking **`Reload Demo Package`**.

---

## 🚀 Get Standalone Qwiki for Production

Ready to deploy Standalone Qwiki on your own servers or hosting?

- **Zero Database Requirement**: 100% standalone using lightweight file stores (`qwiki.json` and `users.json`).
- **Location Agnostic**: Deploy in any subfolder (`/docs`, `/wiki`) or root domain on Apache, Nginx, Caddy, or PHP CLI.
- **GitHub Repository**: [FrancoBenedetti/standalone-qwiki](https://github.com/FrancoBenedetti/standalone-qwiki)
- **Installation Guide**: Follow the step-by-step [Installation Guide](getting-started/installation).
