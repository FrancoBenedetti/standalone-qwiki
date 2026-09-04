# Managing Content

As an Admin, you have full control over the documentation tree and content directly from the web interface.

---

## ➕ Creating New Documents

Click the **`+ Document`** button in the header menu (or under Category actions):
1. **Markdown (`.md`)**: Write new Markdown using the Toast UI WYSIWYG editor or import an existing `.md` file.
2. **HTML Pages (`.html`)**: Design rich interactive pages using the built-in **SunEditor** WYSIWYG editor, switch to raw code view, or upload an existing `.html` file.
3. **Upload PDF / Files**: Upload `.pdf` documents directly to the wiki tree.
4. **Google Docs (`gdoc`)**: Embed any published Google Doc URL with automatic formatting and theme integration.

---

## 📝 Editing Existing Pages

- **Markdown Documents**: Click **`✏️ Edit Content`** in the top right toolbar to open the inline visual editor with live markdown preview.
- **HTML Documents**: Click **`✏️ Edit HTML`** in the document viewer toolbar to open SunEditor, format text, modify tables, or tweak raw HTML code.
- **Document Metadata**: Click **`⚙️ Edit Details`** to change the document title, custom slug, individual CSS theme, short description, or social preview image.

---

## 🎬 Embedding Playable Videos

Standalone Qwiki automatically turns standalone video URLs into responsive, full-featured players in Markdown:
- **YouTube**: Paste a watch URL (`https://www.youtube.com/watch?v=...`), short link (`https://youtu.be/...`), or Shorts link. You can also specify start timestamps like `?t=1m30s`. All embeds use privacy-enhanced `youtube-nocookie.com`.
- **Vimeo**: Paste any standard Vimeo link (`https://vimeo.com/...`) for immediate playback with Do Not Track (`dnt=1`) privacy.
- **Loom**: Paste a Loom share URL (`https://www.loom.com/share/...`) to embed responsive screencasts.
- **Direct Video Files**: Paste any direct `.mp4`, `.webm`, `.ogg`, or `.mov` link to render a native HTML5 video player with playback controls and download fallback.
- **Custom Captions**: Format video links as `[Caption Title](https://...)` or image syntax `![Caption Title](video.mp4)` to display an italicized subtitle below the player.
- **Inline Text Links**: Links placed inside sentences remain standard clickable hyperlinks and are never converted into embeds.

---

## 🔒 Multi-Tab & Collaborative Soft Locks

To prevent concurrent write conflicts and accidental overwrites when multiple tabs or users edit the same document:
- **Advisory Lock Banner**: If another tab or user is editing a document, a warning banner alerts you immediately.
- **Cross-Tab Synchronization**: Tabs communicate in real-time via `BroadcastChannel` (`qwiki_doc_locks`) with zero network delay.
- **Takeover Option**: If a previous session was abandoned or an urgent edit is needed, authorized admins can force-takeover the active lease.
- **Automatic Draft Safety Net**: While editing in Toast UI or SunEditor, your work is continuously mirrored to browser `localStorage`. If a session is evicted, your draft is preserved and can be recovered.

---

## 🖼️ Media & Image Gallery

1. Open the header menu and click **`🖼️ Image Gallery`**.
2. Browse, search, filter, and inspect all uploaded images and generated vector visuals in `uploads/`.
3. Click any asset to preview dimensions and file size, copy Markdown embed tags (`![alt](url)`), or insert directly into the active editor.

---

## ✨ Generating Charts & Visuals

1. Open the header menu and click **`✨ Generate Visual / Chart`**.
2. Select your desired visual type (Bar Chart, Line Chart, Pie/Donut Chart, Flow Diagram, or Status Badge).
3. Type a directive (e.g. `Quarterly Sales: Q1: 30, Q2: 55, Q3: 90, Q4: 120`).
4. Click **`Generate & Save`** to generate the vector SVG graphic into `uploads/`.
5. Click **`Insert into Editor`** (or **`Copy Markdown`**) to place the visual into your document.

---

## 📷 Uploading Images

1. While in the Markdown Editor, click the **`📷 Insert Image`** button.
2. Select an image from your computer (`.png`, `.jpg`, `.svg`, `.webp`).
3. The file is uploaded to `uploads/images/` and the Markdown tag is inserted at your cursor position automatically.

---

## 🖐️ Drag & Drop Menu Reordering

You can organize your documentation hierarchy visually:
- Grab the drag handle (`⣿`) next to any menu item in the left sidebar.
- **Reorder**: Drag it up or down to change its position in the list.
- **Nest**: Drag a document into a folder or sub-category.
- Changes sync automatically to `qwiki.json`.

---

## 🖨️ Print & Social Share

- **Print / PDF**: Click **`🖨️`** in the document action bar to print or save the article as a clean PDF using the print-optimized stylesheet.
- **Share**: Click **`🔗`** to share the page via the native Web Share API (on supported devices) or quickly copy the clean URL to your clipboard.
