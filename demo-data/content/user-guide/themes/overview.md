# Themes & Styling Overview

Welcome to the styling engine of Qwiki. Qwiki uses a cascading theme system that allows you to customize the look and feel of your entire site, specific categories, or individual documents.

## How the Theme Hierarchy Works

Themes are resolved in the following order (from most specific to least specific):

1. **Document Theme**: If a specific document has a theme assigned, it overrides everything else.
2. **Category Theme**: If a document doesn't have a theme, it falls back to the theme assigned to its parent category.
3. **Site Default Theme**: If neither the document nor the category has a theme, the site-wide default theme is used.

This allows you to maintain a consistent global look while styling specific sections (like a newsletter or API reference) completely differently.

## Visibility Controls

In addition to styling, you can control who sees what:

* **Site-wide Badges**: You can restrict document type badges (MD, HTML, PDF, GDOC) to admin users in the main settings.
* **Category Visibility**: When editing a category, you can set its visibility to:
  * `Public`: Visible to everyone.
  * `Logged In Users`: Visible to anyone with an account.
  * `Admins Only`: Visible only to administrators.
