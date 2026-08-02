# Standalone Qwiki

Standalone Qwiki is a lightweight, self-hosted PHP documentation platform that unifies multiple content formats into a single, beautiful documentation portal without external SaaS dependencies.

## Key Features

- 📄 **Multi-Source Support**:
  - **Local Markdown Files (`.md`)**: High performance server-side Markdown parsing using `Parsedown`.
  - **Published Google Docs (`gdoc`)**: Live published Google Docs HTML parsing and cleanup.
  - **PDF Documents (`.pdf`)**: Native responsive PDF viewer iframe integration.
- 🔐 **Admin Management & In-App Editor**:
  - Admin login session (default password: `admin`).
  - Edit Markdown pages directly inside the browser with live side-by-side editing.
  - Upload PDF manuals or `.md` files directly into books from the UI.
- 🎨 **Modern Aesthetics**:
  - Dark Mode and Light Mode support with local storage persistence.
  - Responsive sidebar with live search filter.
  - Clean typography and code highlighting layout.

---

## Quick Setup

1. Clone or copy the repository to your PHP web server directory.
2. Ensure write permissions for the project directory so uploads and configuration updates can be saved:
   ```bash
   chmod -R 775 content api qwiki.json
   ```
3. Start local PHP development server:
   ```bash
   php -S localhost:8000
   ```
4. Open `http://localhost:8000` in your web browser.

---

## Configuration (`qwiki.json`)

The entire documentation tree is configured via `qwiki.json`:

```json
{
  "title": "Standalone Qwiki",
  "logoText": "QWIKI",
  "adminPasswordHash": "$2y$10$H8vIUts/BIGCXGCmw9xFHuCBnPGgNHZ44F59OcQYYxDVKBmD19DIm",
  "defaultBook": "getting-started",
  "books": [
    {
      "id": "getting-started",
      "title": "Getting Started",
      "chapters": [
        {
          "title": "Installation Guide",
          "slug": "installation",
          "type": "markdown",
          "file": "content/getting-started/installation.md"
        },
        {
          "title": "Sample PDF Document",
          "slug": "sample-pdf",
          "type": "pdf",
          "file": "content/getting-started/sample.pdf"
        },
        {
          "title": "External Google Doc",
          "slug": "google-doc",
          "type": "gdoc",
          "url": "https://docs.google.com/document/d/e/.../pub?embedded=true",
          "editUrl": "https://docs.google.com/document/d/.../edit"
        }
      ]
    }
  ]
}
```

---

## Admin Credentials

- Default admin password: `admin`
- To update the admin password, generate a new hash using PHP:
  ```bash
  php -r 'echo password_hash("your_new_password", PASSWORD_DEFAULT);'
  ```
  and update the `"adminPasswordHash"` field in `qwiki.json`.

---

## License

MIT License.
