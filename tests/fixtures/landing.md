<div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(192, 132, 252, 0.08) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.75rem 2rem; margin-bottom: 2.5rem; text-align: center; position: relative; overflow: hidden;">
<div style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.95rem; border-radius: 9999px; background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4); font-size: 0.85rem; font-weight: 600; color: #a5b4fc; margin-bottom: 1.25rem;">
<span>⚡ Eating our own dogfood:</span>
<span style="color: var(--text-primary);">This landing page is powered live by Qwiki</span>
</div>
<h1 style="font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 900; letter-spacing: -0.035em; line-height: 1.15; margin: 0 0 0.75rem 0; background: linear-gradient(135deg, #818cf8 0%, #c084fc 50%, #f472b6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Standalone Qwiki</h1>
<p style="font-size: 1.35rem; font-weight: 600; color: var(--text-primary); margin: 0 0 1rem 0; letter-spacing: -0.015em;">Incredibly powerful, stupidly simple.</p>
<p style="font-size: 1.1rem; color: var(--text-secondary); max-width: 720px; margin: 0 auto 2rem auto; line-height: 1.7;">The zero-database, drop-in documentation portal and wiki engine for modern web creators. Written in vanilla PHP. Deploy into any domain or subfolder in 60 seconds with zero configuration.</p>
<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.85rem; margin-bottom: 2rem;">
<a href="demo" class="btn btn-primary" style="padding: 0.75rem 1.4rem; font-size: 0.95rem; font-weight: 600; border-radius: var(--radius-sm); background-color: #10b981; border-color: #059669;">🎮 Try Live Admin Demo (/demo)</a>
<a href="https://github.com/FrancoBenedetti/standalone-qwiki" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="padding: 0.75rem 1.4rem; font-size: 0.95rem; font-weight: 600; border-radius: var(--radius-sm);">⭐ Star on GitHub</a>
<a href="docs" class="btn btn-outline" style="padding: 0.75rem 1.4rem; font-size: 0.95rem; font-weight: 600; border-radius: var(--radius-sm); border-color: var(--accent-color); color: #a5b4fc;">📖 Explore Full Docs (/docs)</a>
<a href="https://github.com/FrancoBenedetti/standalone-qwiki/releases/latest" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="padding: 0.75rem 1.4rem; font-size: 0.95rem; font-weight: 600; border-radius: var(--radius-sm);">🚀 Download Release (.zip)</a>
</div>
<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; font-size: 0.85rem; color: var(--text-secondary); padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.08);">
<div>⚡ <strong>0</strong> Database Queries</div>
<div>📁 <strong>100%</strong> Subfolder Agnostic</div>
<div>🎮 <strong>Live Admin Sandbox</strong> (/demo)</div>
<div>📑 <strong>Markdown + HTML + PDF + GDoc</strong></div>
<div>🌓 <strong>Instant Zero-Flicker</strong> Theming</div>
<div>🔒 <strong>MIT</strong> Open Source</div>
</div>
</div>

---

## ⚡ 60-Second Quick Start

Installing Standalone Qwiki requires **no database setup, no Docker container, and no composer build step**. Follow these 3 copy-paste steps:

1. **Download the latest release package**:
```bash
wget https://github.com/FrancoBenedetti/standalone-qwiki/releases/latest/download/standalone-qwiki.zip
```

2. **Extract into your web directory (root or any subfolder)**:
```bash
unzip standalone-qwiki.zip -d /var/www/html/docs
```

3. **Set directory permissions**:
```bash
chmod -R 775 /var/www/html/docs
chown -R www-data:www-data /var/www/html/docs
```

> 💡 **Auto-Setup Engine**: When you open your deployment URL in any browser, Qwiki automatically detects the fresh installation, creates your `qwiki.json`, initializes the user store, and loads initial demo categories. Log in with `admin` / `admin` to start creating!

---

## 🎬 See Qwiki in Action

Qwiki includes native **single-line video auto-embedding**. Simply drop a standalone YouTube, Vimeo, Loom, or direct MP4 link on its own line in Markdown, and Qwiki renders a responsive, high-performance player with privacy safeguards:

[Quick Tour: 60-Second Installation, In-Browser Editing & Dynamic Theming](https://www.youtube.com/watch?v=dQw4w9WgXcQ)

---

<div style="margin: 2.5rem 0;">
<h2 style="margin-bottom: 0.5rem; border-bottom: none;">Why Qwiki? The 4 Core Pillars</h2>
<p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Most modern documentation tools force a painful tradeoff between over-engineered database apps and rigid static site generators. Qwiki delivers the best of both worlds.</p>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
<div style="font-size: 2rem;">🗂️</div>
<h3 style="margin: 0; font-size: 1.15rem; color: var(--text-primary);">Zero Database, Zero Lock-In</h3>
<p style="margin: 0; font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">Operates completely standalone with plain JSON configuration (<code style="font-size: 0.85em;">qwiki.json</code>) and raw Markdown/HTML files. Back up your entire knowledge base with a simple <code style="font-size: 0.85em;">zip</code>, sync with <code style="font-size: 0.85em;">rsync</code>, or track changes in Git.</p>
</div>
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
<div style="font-size: 2rem;">🌐</div>
<h3 style="margin: 0; font-size: 1.15rem; color: var(--text-primary);">100% Subfolder Agnostic</h3>
<p style="margin: 0; font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">Deploy in any folder (<code style="font-size: 0.85em;">/docs</code>, <code style="font-size: 0.85em;">/kb</code>, <code style="font-size: 0.85em;">/help</code>) or as a root domain. All navigation, asset URLs, and API endpoints use smart relative paths with zero hardcoded URLs and zero server rewrite headaches.</p>
</div>
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
<div style="font-size: 2rem;">📑</div>
<h3 style="margin: 0; font-size: 1.15rem; color: var(--text-primary);">Multi-Format Freedom</h3>
<p style="margin: 0; font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">Mix and match Markdown (<code style="font-size: 0.85em;">.md</code>) with Parsedown, sandboxed HTML (<code style="font-size: 0.85em;">.html</code>) with SunEditor, embedded PDF manuals, published Google Docs, and auto-embedded videos under a single navigation tree.</p>
</div>
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
<div style="font-size: 2rem;">🔒</div>
<h3 style="margin: 0; font-size: 1.15rem; color: var(--text-primary);">Collaborative Soft-Locking</h3>
<p style="margin: 0; font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6;">Multi-tab and multi-user advisory leases prevent overwrite collisions with background heartbeats and instant <code style="font-size: 0.85em;">BroadcastChannel</code> tab sync. Includes role-based access control (Admin vs. Viewer) and 1-click updates.</p>
</div>
</div>
</div>

---

<div style="margin: 2.5rem 0; overflow-x: auto;">
<h2 style="margin-bottom: 0.5rem; border-bottom: none;">How Does Qwiki Compare?</h2>
<p style="color: var(--text-secondary); margin-bottom: 1.5rem;">See how Standalone Qwiki compares with traditional wiki software, database-driven documentation tools, and static site generators:</p>
<table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
<thead>
<tr style="background: var(--bg-secondary);">
<th style="padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary); width: 26%;">Feature</th>
<th style="padding: 1rem; border-bottom: 1px solid var(--border-color); color: #818cf8; font-weight: 700; width: 22%; background: rgba(99, 102, 241, 0.1);">⚡ Standalone Qwiki</th>
<th style="padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary); width: 18%;">DokuWiki</th>
<th style="padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary); width: 18%;">BookStack</th>
<th style="padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary); width: 16%;">MkDocs / VitePress</th>
</tr>
</thead>
<tbody>
<tr>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 600;">Database Required</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); background: rgba(99, 102, 241, 0.05); color: #34d399; font-weight: 600;">❌ None (Zero-DB)</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #34d399;">❌ None</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #f87171;">⚠️ MySQL / MariaDB</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #34d399;">❌ None</td>
</tr>
<tr>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 600;">Subfolder Drop-In</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); background: rgba(99, 102, 241, 0.05); color: #34d399; font-weight: 600;">✅ 100% Native</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">⚠️ Config required</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #f87171;">❌ Complex proxying</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">⚠️ Base URL rebuild</td>
</tr>
<tr>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 600;">In-Browser Visual Editing</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); background: rgba(99, 102, 241, 0.05); color: #34d399; font-weight: 600;">✅ Markdown &amp; HTML</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">⚠️ Custom wikitext</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #34d399;">✅ Yes (WYSIWYG)</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #f87171;">❌ Git / Terminal only</td>
</tr>
<tr>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 600;">Multi-Format Embedding</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); background: rgba(99, 102, 241, 0.05); color: #34d399; font-weight: 600;">✅ MD + HTML + PDF + GDoc</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #f87171;">❌ Plugins required</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">⚠️ Limited</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">⚠️ Markdown only</td>
</tr>
<tr>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 600;">Menu Drag-and-Drop</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); background: rgba(99, 102, 241, 0.05); color: #34d399; font-weight: 600;">✅ Native Live DND</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #f87171;">❌ Manual namespaces</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #34d399;">✅ Yes (Shelf/Book)</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #f87171;">❌ Edit YAML config</td>
</tr>
<tr>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); font-weight: 600;">Setup Time</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); background: rgba(99, 102, 241, 0.05); color: #818cf8; font-weight: 700;">⏱️ &lt; 60 Seconds</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">⏱️ 5–10 Minutes</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: #f87171;">⏱️ 20–30 Minutes</td>
<td style="padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">⏱️ 15–30 Minutes (CI/CD)</td>
</tr>
<tr>
<td style="padding: 0.85rem 1rem; font-weight: 600;">Server Requirements</td>
<td style="padding: 0.85rem 1rem; background: rgba(99, 102, 241, 0.05); color: #34d399; font-weight: 600;">Any PHP 7.4–8.x host</td>
<td style="padding: 0.85rem 1rem; color: var(--text-secondary);">PHP 7.4+</td>
<td style="padding: 0.85rem 1rem; color: #f87171;">PHP + MySQL + Composer</td>
<td style="padding: 0.85rem 1rem; color: var(--text-secondary);">Node.js / Python build pipeline</td>
</tr>
</tbody>
</table>
</div>

---

## 🌟 Built-in Capabilities & Developer Experience

- **🌓 Zero-Flicker Theming**: Server-side cookie sync paired with synchronous head script execution completely eliminates Flash of Unstyled Theme (FOUC). Switch between Light and Dark mode with instantaneous persistence.
- **🔍 Full-Text Server-Side Search**: Instant real-time search across titles, descriptions, and file contents across all registered page types, with category visibility enforcement.
- **✨ Agentic Visual & Chart Generator**: Integrated utility producing vector Bar Charts, Line Trends, Pie/Donut Charts, Process Flows, and Status Badges. Generates standalone SVG files saved directly to `uploads/` with 1-click Markdown insertion.
- **🖐️ Drag-and-Drop Tree Management**: Admins can reorder documents, nest items into subfolders, and sort categories using intuitive visual drag handles (`⣿`) that sync immediately to `qwiki.json`.
- **↔️ Resizable Sidebar**: Drag the sidebar border to any preferred width (`200px` to `550px`), preserved automatically in `localStorage`.
- **📡 RSS Feed Syndication**: Category-level full-text RSS feeds (`/api/feed.php?category=blog`), ready for feed readers and automation webhooks.
- **🧩 Drop-In Extension Engine**: Add custom page types or admin tools in `assets/extensions/` without touching core code.

---

<div style="margin: 2.5rem 0;">
<h2 style="margin-bottom: 0.5rem; border-bottom: none;">Live Showcase: Test Drive Qwiki</h2>
<p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Explore live Qwiki deployments right now. Jump into our official product documentation or try out full admin capabilities in our interactive, self-resetting sandbox:</p>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
<a href="demo" style="text-decoration: none; color: inherit; display: block;">
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(99, 102, 241, 0.08) 100%); border: 1px solid rgba(16, 185, 129, 0.4); border-radius: var(--radius-md); padding: 1.25rem; height: 100%; transition: border-color var(--transition-fast);">
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
<span style="font-weight: 700; color: #34d399;">🎮 Live Admin Sandbox</span>
<span class="doc-badge badge-gdoc" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;">/demo</span>
</div>
<p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">Test drive full Admin powers! Edit Markdown &amp; HTML live, drag-and-drop tree items, and test theming with zero risk (periodically auto-reset).</p>
</div>
</a>
<a href="docs" style="text-decoration: none; color: inherit; display: block;">
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; height: 100%; transition: border-color var(--transition-fast);">
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
<span style="font-weight: 700; color: var(--text-primary);">📖 Documentation Portal</span>
<span class="doc-badge badge-md" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;">/docs</span>
</div>
<p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">Complete user manual, API references, configuration guides, and extension walkthroughs.</p>
</div>
</a>
<a href="docs/getting-started/features" style="text-decoration: none; color: inherit; display: block;">
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; height: 100%; transition: border-color var(--transition-fast);">
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
<span style="font-weight: 700; color: var(--text-primary);">📝 Markdown Parsing</span>
<span class="doc-badge badge-md" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;">MARKDOWN</span>
</div>
<p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">Clean Parsedown rendering with inline Toast UI editor, video embeds, and table-of-contents tracking.</p>
</div>
</a>
<a href="docs/getting-started/interactive-dashboard" style="text-decoration: none; color: inherit; display: block;">
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; height: 100%; transition: border-color var(--transition-fast);">
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
<span style="font-weight: 700; color: var(--text-primary);">🌐 Sandboxed HTML</span>
<span class="doc-badge badge-link" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;">HTML</span>
</div>
<p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">Interactive custom HTML dashboards with SunEditor WYSIWYG editing and raw code view toggle.</p>
</div>
</a>
<a href="docs/getting-started/sample-pdf" style="text-decoration: none; color: inherit; display: block;">
<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; height: 100%; transition: border-color var(--transition-fast);">
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
<span style="font-weight: 700; color: var(--text-primary);">📑 Embedded PDF Viewer</span>
<span class="doc-badge badge-pdf" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;">PDF</span>
</div>
<p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">Embedded PDF reader with download controls, responsive viewport scaling, and search integration.</p>
</div>
</a>
</div>
</div>

---

## 🎯 Who is Qwiki For?

- **Web Agencies & Freelancers**: Deliver modern documentation, client handoff wikis, and project manuals on existing hosting accounts without setting up additional databases or managing migrations.
- **Homelabbers & Self-Hosters**: Minimal RAM footprint, runs blazingly fast on low-spec VPS servers, shared cPanel hosts, or Raspberry Pi environments.
- **Open-Source Maintainers**: Eliminate heavy CI/CD build steps. Just update your Markdown files or edit directly in the browser.
- **SaaS & Product Teams**: Maintain public documentation or private internal knowledge bases with role-based access control and collaborative soft-locking.

---

<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem 2rem; margin: 3rem 0 1rem 0; text-align: center;">
<h2 style="margin: 0 0 0.75rem 0; font-size: 1.8rem; border-bottom: none;">Ready for documentation that just works?</h2>
<p style="color: var(--text-secondary); max-width: 600px; margin: 0 auto 1.75rem auto; font-size: 1.05rem;">Standalone Qwiki is 100% free, MIT-licensed open source software. Star the repository, download the ZIP, or test the full admin suite in our live sandbox.</p>
<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem;">
<a href="demo" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; font-weight: 600; background-color: #10b981; border-color: #059669;">🎮 Try Live Admin Demo (/demo)</a>
<a href="https://github.com/FrancoBenedetti/standalone-qwiki" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; font-weight: 600;">⭐ Star on GitHub</a>
<a href="docs" class="btn btn-outline" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; font-weight: 600;">📖 Read the Documentation (/docs)</a>
<a href="https://github.com/FrancoBenedetti/standalone-qwiki/releases/latest" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; font-weight: 600;">📦 Download Release (.zip)</a>
</div>
</div>
