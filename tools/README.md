# Standalone Qwiki - Desktop Postbox CLI & OS Integrations

The **Qwiki Postbox Desktop Tools** allow you to asynchronously transfer Markdown documents (`.md`), HTML files (`.html`), and entire document folders directly from your local computer (Linux, Windows, macOS) or CI/CD pipelines to any Standalone Qwiki or subwiki.

All transfers land safely in the receiving wiki's **Inbound Postbox** queue for editorial review — no documents are ever published or overwritten without reviewer confirmation.

---

## 🚀 Quick Setup

### Step 1: Configure Your Target Wiki
If you downloaded this bundle directly from Qwiki (**Qwiki Postbox ➔ Peers & Settings ➔ Download CLI & Desktop Tools**), `config.sample.json` is already pre-populated with your wiki's URL and Postbox Access Token.

Copy `config.sample.json` to your user configuration directory:

- **Linux / macOS**:
  ```bash
  mkdir -p ~/.config/qwiki
  cp config.sample.json ~/.config/qwiki/config.json
  ```
- **Windows (Command Prompt / PowerShell)**:
  ```cmd
  mkdir "%USERPROFILE%\.qwiki"
  copy config.sample.json "%USERPROFILE%\.qwiki\config.json"
  ```

---

## 🖱️ OS File Manager Integrations ("Send to Qwiki")

Install native right-click context menu actions for your operating system:

### 🐧 Linux (GNOME Files / Nautilus, Nemo, Caja)

1. **Install the script**:
   ```bash
   bash desktop/linux/install-nautilus.sh
   ```
   *(Or make it executable: `chmod +x desktop/linux/install-nautilus.sh && ./desktop/linux/install-nautilus.sh`)*

2. **How to access in Files (Nautilus)**:
   - **Select a file or folder** in your file manager.
     > ⚠️ **Important:** In GNOME / Nautilus, the **Scripts** menu is context-sensitive and **only appears when a file or folder is selected**. It will not appear when right-clicking on empty background space or the desktop wallpaper.
   - Right-click the selected document or folder.
   - Hover over or click **Scripts** ➔ **Send to Qwiki**.

3. **Troubleshooting Menu Visibility**:
   - Nautilus runs as a continuous desktop daemon. If the **Scripts** submenu does not show up immediately, reload Nautilus by running:
     ```bash
     nautilus -q
     ```
     Then re-open **Files**.

---

### 🪟 Windows (File Explorer)

1. **Install the SendTo shortcut**:
   - Double-click `desktop\windows\setup-sendto.bat`.
2. **How to access**:
   - Right-click any document or folder in File Explorer.
   - Choose **Send to** ➔ **Send to Qwiki**.

---

### 🍏 macOS (Finder Quick Actions)

1. **Install the Quick Action workflow**:
   ```bash
   bash desktop/macos/install-quickaction.sh
   ```
2. **How to access**:
   - Right-click any document or folder in Finder.
   - Choose **Quick Actions** ➔ **Send to Qwiki**.

---

## 💻 Terminal CLI Usage

You can also send files or folders directly from your terminal:

```bash
# Send a single markdown document
python3 qwiki-postbox.py send path/to/document.md

# Send with a recommended destination category hint
python3 qwiki-postbox.py send path/to/document.md --category "Guides"

# Send an entire directory of documents
python3 qwiki-postbox.py send path/to/docs/ --category "Engineering Specs"

# Dry run (verify discovered documents and assets without transmitting)
python3 qwiki-postbox.py send path/to/docs/ --dry-run

# Include unreferenced images/assets in directory scans
python3 qwiki-postbox.py send path/to/docs/ --all-assets
```

---

## 🌐 Multi-Wiki Profile Selection & Interactive GUI Picker

If you publish to multiple Qwiki instances or subwikis (e.g., `main`, `engineering`, `client-docs`):

### 1. Managing Named Profiles
```bash
# Add or update a profile
python3 qwiki-postbox.py config --profile engineering \
  --url https://wiki.example.com/engineering \
  --token 32_character_token_here

# List all configured profiles
python3 qwiki-postbox.py profiles
```

### 2. Automatic OS Context Menu GUI Picker
When right-clicking **Send to Qwiki** in your file manager (Linux, Windows, macOS):
- **Single Profile**: If only one profile is configured, documents transmit immediately without extra prompts.
- **Multiple Profiles**: A native GUI selection dialog appears automatically:
  - **Linux (GNOME / KDE)**: Displays a Zenity / kdialog table showing profile names and target URLs.
  - **Windows (File Explorer)**: Displays a PowerShell `Out-GridView` window with instant search and OK/Cancel buttons.
  - **macOS (Finder)**: Displays an AppleScript / Cocoa list chooser with your configured profiles.
- Select the destination wiki and click OK.

### 3. Terminal CLI Profile Selection & Batch Transfer
```bash
# Explicitly target a specific profile
python3 qwiki-postbox.py send doc.md --profile engineering

# Interactively choose target profile in terminal
python3 qwiki-postbox.py send doc.md --interactive

# Send multiple files and folders together in one envelope
python3 qwiki-postbox.py send guide.md specs/ release-notes.html --category "Release Docs"
```

---

## ⚙️ System Requirements

- **Python**: Version 3.6 or higher (uses standard library only; zero external `pip` packages required).
- **Linux Notifications & GUI (Optional)**: `zenity` (or `kdialog`) for interactive GUI profile picker; `libnotify-bin` (`notify-send`) for desktop notification bubbles.
