# Authentication & User Access Control

Standalone Qwiki features a lightweight, file-based **Role-Based Access Control (RBAC)** engine stored in `users.json`. It requires no external database while providing enterprise-grade security using PHP Bcrypt password hashing (`password_hash()`), stateless HMAC-SHA256 password resets, and native RFC 6238 Two-Factor Authentication (2FA).

> [!NOTE]
> This advanced topic page is primarily provided as an illustration of Qwiki's feature set and documentation style. Feel free to delete it once you're familiar with the system.

---

## 👥 User Roles & Permissions

| Action / Capability | Viewer | Admin |
| :--- | :---: | :---: |
| **Read Documentation (Markdown, HTML, PDF, Google Docs)** | ✅ | ✅ |
| **Search & Filter Navigation** | ✅ | ✅ |
| **Manage Personal Email & 2FA (`👤 Account & Security`)** | ✅ | ✅ |
| **Resize Sidebar Width** | ✅ | ✅ |
| **Create / Upload / Edit Documents & Categories** | ❌ | ✅ |
| **Edit HTML Documents with SunEditor WYSIWYG** | ❌ | ✅ |
| **Generate Vector Charts & Diagrams (`uploads/`)** | ❌ | ✅ |
| **Drag & Drop Menu Reordering** | ❌ | ✅ |
| **Upload Images inside Markdown Editor** | ❌ | ✅ |
| **Manage Users, Roles & Passwords (`👥 Users`)** | ❌ | ✅ |
| **Configure Wiki 2FA Policy & SMTP (`⚙️ Site Settings`)** | ❌ | ✅ |

---

## 🔑 Default Credentials

- **Username**: `admin`
- **Password**: `admin`

> [!IMPORTANT]
> Change the default admin password or add a new admin account in **`👥 Users`** immediately after deploying to production.

---

## 🔄 Self-Service Password Reset

Users can securely reset their own passwords if an email address is associated with their account:

1. **Email Verification**: Users configure their verified email under **`👤 Account & Security`**.
2. **Stateless HMAC Tokens**: Reset links contain cryptographically signed tokens (`action=reset_password&token=...`) expiring in 2 hours. Tokens incorporate a cryptographic slice of the user's current password hash, guaranteeing **0 disk writes** during request generation and **immediate, automatic invalidation** once the password is changed.
3. **Air-Gapped & Offline Fallback**: In environments without outbound email or SMTP, administrators can click **`🔗 Reset Link`** next to any user in the **`👥 Users`** management modal to copy a 24-hour offline reset link directly to their clipboard.

---

## 🛡️ Two-Factor Authentication (2FA / TOTP)

Standalone Qwiki implements pure native RFC 6238 Time-Based One-Time Passwords (TOTP) without requiring any external libraries or cloud dependencies.

### Features
- **Broad Authenticator Compatibility**: Compatible with Google Authenticator, Bitwarden, 1Password, Microsoft Authenticator, and Aegis.
- **Offline Client-Side QR Codes**: Secret QR codes are rendered as pure SVG vector graphics in the browser with zero external calls to third-party image APIs.
- **8 Emergency Recovery Codes**: Users receive eight single-use 10-character recovery codes upon enrollment. Each code is BCrypt-hashed in `users.json` and consumed on first use.
- **Configurable Wiki Policies**: Administrators can configure the wiki-wide policy in **`⚙️ Site Settings`**:
  - `Optional`: Users can choose whether to enable 2FA on their account (default).
  - `Required for Admins`: All administrators must enroll in 2FA before accessing administrative tools.
  - `Required for All`: All users (viewers and admins) must enroll in 2FA.
  - `Disabled`: Two-factor authentication is globally disabled.

### 🚨 Emergency CLI Recovery
If an administrator loses access to both their authenticator app and their recovery codes, 2FA can be safely disabled from the server terminal:

1. Connect to the server terminal via SSH.
2. Run the recovery command with the target username:
```bash
php bin/reset-2fa.php admin
```
3. Log in with the standard username and password.

---

## ✉️ Outbound Email & SMTP Delivery

Outbound mail (password resets, email verification) supports two delivery modes configured in **`⚙️ Site Settings`**:

1. **Direct Socket SMTP**: Connects directly via `stream_socket_client()` with support for TLS (STARTTLS port 587) and SSL (port 465) with `AUTH LOGIN` authentication. Includes a built-in "Test Connection" button.
2. **Native PHP `mail()` Fallback**: If custom SMTP is disabled, Standalone Qwiki uses the server's native `mail()` function with `-f` envelope sender parameter alignment.