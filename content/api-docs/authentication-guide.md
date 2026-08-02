# Authentication & User Access Control

Standalone Qwiki features a lightweight, file-based **Role-Based Access Control (RBAC)** engine stored in `users.json`. It requires no external database while providing enterprise-grade security using PHP Bcrypt password hashing (`password_hash()`).

---

## 👥 User Roles & Permissions

| Action / Capability | Viewer | Admin |
| :--- | :---: | :---: |
| **Read Documentation (Markdown, PDF, Google Docs)** | ✅ | ✅ |
| **Search & Filter Navigation** | ✅ | ✅ |
| **Resize Sidebar Width** | ✅ | ✅ |
| **Create / Upload / Edit Documents & Categories** | ❌ | ✅ |
| **Drag & Drop Menu Reordering** | ❌ | ✅ |
| **Upload Images inside Markdown Editor** | ❌ | ✅ |
| **Manage Users & Roles (`users.json`)** | ❌ | ✅ |

---

## 🔑 Default Credentials

- **Username**: `admin`
- **Password**: `admin`

> [!IMPORTANT]
> Change the default admin password or add a new admin account in **`👥 Users`** immediately after deploying to production.