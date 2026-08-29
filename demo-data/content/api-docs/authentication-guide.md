# Authentication & User Access Control

Standalone Qwiki features a lightweight, file-based **Role-Based Access Control (RBAC)** engine stored in `users.json`. It requires no external database while providing enterprise-grade security using PHP Bcrypt password hashing (`password_hash()`).

> [!NOTE]
> This advanced topic page is primarily provided as an illustration of Qwiki's feature set and documentation style. Feel free to delete it once you're familiar with the system.

---

## 👥 User Roles & Permissions

| Action / Capability | Viewer | Admin |
| :--- | :---: | :---: |
| **Read Documentation (Markdown, HTML, PDF, Google Docs)** | ✅ | ✅ |
| **Search & Filter Navigation** | ✅ | ✅ |
| **Resize Sidebar Width** | ✅ | ✅ |
| **Create / Upload / Edit Documents & Categories** | ❌ | ✅ |
| **Edit HTML Documents with SunEditor WYSIWYG** | ❌ | ✅ |
| **Generate Vector Charts & Diagrams (`uploads/`)** | ❌ | ✅ |
| **Drag & Drop Menu Reordering** | ❌ | ✅ |
| **Upload Images inside Markdown Editor** | ❌ | ✅ |
| **Manage Users & Passwords (`users.json`)** | ❌ | ✅ |

---

## 🔑 Default Credentials

- **Username**: `admin`
- **Password**: `admin`

> [!IMPORTANT]
> Change the default admin password or add a new admin account in **`👥 Users`** immediately after deploying to production.