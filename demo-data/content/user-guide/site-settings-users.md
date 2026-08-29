# Site Settings, Users & Updates

Standalone Qwiki provides a comprehensive Admin Suite to configure site access, theme styling, syndication, and user accounts.

---

## ⚙️ Access Modes & Privacy

To change site visibility:
1. Click **`⚙️ Settings`** in the header menu.
2. Choose your preferred **Access Mode**:
   - **Public Access**: Anyone on the internet can read and search your documentation.
   - **Private Portal**: Visitors must log in to view any documentation.
3. Click **Save Settings**.

---

## 👥 Managing Users (RBAC)

1. Click **`👥 Users`** in the header menu.
2. **Add User**: Enter a username, password, and select a role:
   - **Admin**: Full rights to create, edit, upload, reorder navigation, and manage users.
   - **Viewer**: Read-only documentation access.
3. **Delete User**: Click the trash icon next to an account to remove it. (Note: You cannot delete your own active account).

---

## 🎨 Theme Editor & Brand Settings

- **Default Theme**: Select the global theme from the Settings dialog.
- **Custom Logo**: Enter a logo image URL to replace the default text logo.
- **Live Theme Editor**: Launch the Theme Editor to create or customize CSS files directly in the browser.

---

## 📡 RSS Feeds & Syndication

- Category-level RSS and JSON feeds are automatically generated (e.g. `/api/feed.php?category=getting-started`).
- For private portals, generate an **RSS Access Token** in Settings to securely subscribe via RSS readers (compatible with RSSHub).

---

## 🎉 1-Click Auto Updates

- When a new version of Qwiki is released, an **`🎉 Update Available!`** button appears in the admin menu.
- Click to view release notes and install core updates with one click.
- User data (`content/`, `uploads/`, `qwiki.json`, `users.json`) and custom extensions (`assets/extensions/`) are always safely preserved.
