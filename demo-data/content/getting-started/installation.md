# Installation Guide

Welcome to **Standalone Qwiki**! This document explains how to set up and configure your documentation workspace.

## Quick Start

1. **Clone or Download** the repository to your PHP web server.
2. Ensure write permissions for the `content/` and `uploads/` directories.
3. Configure your documentation structure in `qwiki.json`.
4. Access the web interface in your browser!

### Configuration Example

You can define books and chapters in `qwiki.json`:

```json
{
  "title": "Standalone Qwiki Documentation",
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
        }
      ]
    }
  ]
}
```

> [!NOTE]
> Admin users can edit Markdown pages directly inside the UI or upload PDFs to create new chapters seamlessly.

> [!TIP]
> **Post-Installation Cleanup**
> The sample sections (like this Getting Started guide, Advanced Topics, and User Guide) are provided for illustration purposes. You can safely delete them from the Admin interface at your discretion once you are familiar with the system.

> [!IMPORTANT]
> **Security Reminder**
> Immediately after installation, please navigate to the **Users** section and change the default admin password to secure your site.
