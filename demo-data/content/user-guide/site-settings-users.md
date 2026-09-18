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
- **Global Social Share Metadata**: Configure a fallback **Global Social Share Description** and **Global Social Share Image URL** in Site Settings to represent your portal with rich cards across social platforms and messaging apps.
- **Live Theme Editor**: Launch the Theme Editor to create or customize CSS files directly in the browser with instant preview.

---

## 📡 RSS Feeds & Syndication

- Category-level RSS and JSON feeds are automatically generated (e.g. `/api/feed.php?category=getting-started`).
- For private portals, generate an **RSS Access Token** in Settings to securely subscribe via RSS readers (compatible with RSSHub).
- Configure the number of syndicated items per feed directly in Settings.

---

## 🎉 1-Click Auto Updates

- When a new version of Qwiki is released, an **`🎉 Update Available!`** button appears in the admin menu.
- Click to view the release notes directly from GitHub and install core updates with one click.
- User data (`content/`, `uploads/`, `qwiki.json`, `users.json`) and custom extensions (`assets/extensions/`) are always safely preserved.

---

## 🌐 Subwikis Management

Standalone Qwiki supports single-level nested wikis (subwikis) deployed and managed directly from the parent wiki:
1. Click **`🌐 Subwikis`** in the user menu.
2. **Deploy Subwiki**: Enter the title, slug, and initial admin credentials.
   - **Dashed Grouping**: Use hyphens to group departments or topics cleanly (e.g. `engineering-electrical`, `engineering-mechanical`).
   - **Bi-Directional Collision Protection**: Subwiki slugs and parent category names are protected against clashing.
   - **Single-Level Depth**: Subwikis cannot deploy further child wikis.
3. **Dynamic Parent Title & Navigation**: Subwikis display a prominent **`← Back to [Parent Title]`** banner in the sidebar. This title dynamically tracks renames made to the parent wiki in real-time, or can be customized along with the parent URL in the subwiki's Site Settings.
4. **Automated Cascading Updates**: When updating the parent wiki, all deployed subwikis receive code updates automatically without requiring separate updater clicks.
5. **Sidebar Subwiki Navigation**: In **Site Settings**, enable **"Show Subwikis in Left Sidebar Navigation"** to render a collapsible accordion of all subwikis in the left sidebar. Readers can navigate across subwikis based on their access permissions (public content remains accessible, private content remains shielded) and return seamlessly using the parent navigation banner.

