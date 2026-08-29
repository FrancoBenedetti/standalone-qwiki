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
