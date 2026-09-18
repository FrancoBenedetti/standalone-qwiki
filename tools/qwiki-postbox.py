#!/usr/bin/env python3
"""
Standalone Qwiki - Desktop Postbox CLI Tool
Cross-platform tool (Linux, Windows, macOS) to send single files or entire folders
into a Qwiki Postbox staging inbox.

Zero external dependencies - uses strictly the Python 3 standard library.
"""

import sys
import os
import re
import json
import base64
import socket
import argparse
import mimetypes
import hashlib
from datetime import datetime, timezone
from pathlib import Path
import urllib.request
import urllib.error

VERSION = "1.0.0"

SUPPORTED_DOC_EXTS = {
    ".md": "markdown",
    ".markdown": "markdown",
    ".html": "html",
    ".htm": "html",
    ".pdf": "pdf"
}

SUPPORTED_ASSET_EXTS = {
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".svg",
    ".mp4", ".webm", ".pdf", ".txt", ".csv"
}

MAX_ASSET_SIZE = 25 * 1024 * 1024  # 25 MB max per asset


def get_config_dir() -> Path:
    """Return the platform-appropriate configuration directory."""
    if os.name == "nt":
        base = os.environ.get("USERPROFILE") or os.environ.get("APPDATA") or "."
        conf_dir = Path(base) / ".qwiki"
    else:
        xdg = os.environ.get("XDG_CONFIG_HOME")
        if xdg:
            conf_dir = Path(xdg) / "qwiki"
        else:
            conf_dir = Path.home() / ".config" / "qwiki"
    return conf_dir


def get_config_file() -> Path:
    return get_config_dir() / "config.json"


def load_config() -> dict:
    cfile = get_config_file()
    if cfile.is_file():
        try:
            with open(cfile, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            pass
    return {}


def save_config(data: dict):
    cdir = get_config_dir()
    cdir.mkdir(parents=True, exist_ok=True)
    cfile = get_config_file()
    with open(cfile, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2)


def slugify(text: str) -> str:
    text = text.lower().strip()
    text = re.sub(r"[^a-z0-9\-]+", "-", text)
    text = re.sub(r"-+", "-", text)
    return text.strip("-") or "doc"


def extract_referenced_assets(doc_path: Path, content: str) -> list:
    """Find and encode local assets referenced in Markdown or HTML."""
    assets = []
    seen = set()

    # Match Markdown: ![alt](path) and HTML: src="path" / href="path"
    patterns = [
        r'!\[.*?\]\((?!https?://|data:|mailto:|#)([^)\s]+)\)',
        r'<img[^>]+src=["\'](?!https?://|data:|mailto:|#)([^"\']+)["\']'
    ]

    doc_dir = doc_path.parent

    for pattern in patterns:
        for match in re.finditer(pattern, content, re.IGNORECASE):
            raw_path = match.group(1).split("?")[0].split("#")[0].strip()
            if not raw_path or raw_path in seen:
                continue

            # Resolve relative to document directory
            asset_path = (doc_dir / raw_path).resolve()
            if not asset_path.is_file():
                continue

            ext = asset_path.suffix.lower()
            if ext not in SUPPORTED_ASSET_EXTS:
                continue

            try:
                size = asset_path.stat().st_size
                if size > MAX_ASSET_SIZE:
                    continue

                seen.add(raw_path)
                mime, _ = mimetypes.guess_type(str(asset_path))
                if not mime:
                    mime = "application/octet-stream"

                with open(asset_path, "rb") as af:
                    asset_bytes = af.read()

                assets.append({
                    "rel_path": raw_path.replace("\\", "/"),
                    "basename": asset_path.name,
                    "mime": mime,
                    "size": size,
                    "sha1": hashlib.sha1(asset_bytes).hexdigest(),
                    "data": base64.b64encode(asset_bytes).decode("ascii")
                })
            except Exception as e:
                print(f"[Warning] Could not read asset {asset_path}: {e}", file=sys.stderr)

    return assets


def package_single_file(file_path: Path, title: str = None, category_hint: str = None) -> dict:
    """Package a single document file into postbox document format."""
    ext = file_path.suffix.lower()
    if ext not in SUPPORTED_DOC_EXTS:
        raise ValueError(f"Unsupported document format '{ext}'. Supported formats: {', '.join(SUPPORTED_DOC_EXTS.keys())}")

    doc_type = SUPPORTED_DOC_EXTS[ext]
    doc_title = title or file_path.stem.replace("-", " ").replace("_", " ").title()
    doc_slug = slugify(file_path.stem)

    if doc_type == "pdf":
        with open(file_path, "rb") as f:
            content = base64.b64encode(f.read()).decode("ascii")
        assets = []
    else:
        with open(file_path, "r", encoding="utf-8", errors="replace") as f:
            content = f.read()
        assets = extract_referenced_assets(file_path, content)

    doc_data = {
        "id": doc_slug,
        "slug": doc_slug,
        "title": doc_title,
        "type": doc_type,
        "category_hint": category_hint or "",
        "content": content,
        "assets": assets
    }
    return doc_data


def package_directory(dir_path: Path, recursive: bool = True, category_hint: str = None, all_assets: bool = False) -> list:
    """Scan a directory and package all supported documents."""
    docs = []
    cat = category_hint or dir_path.name.replace("-", " ").replace("_", " ").title()

    pattern = "**/*" if recursive else "*"
    for item in sorted(dir_path.glob(pattern)):
        if item.is_file():
            ext = item.suffix.lower()
            if ext in SUPPORTED_DOC_EXTS:
                try:
                    doc = package_single_file(item, category_hint=cat)
                    docs.append(doc)
                except Exception as e:
                    print(f"[Warning] Skipping {item}: {e}", file=sys.stderr)

    # Optional: collect standalone unreferenced images if --all-assets specified
    if all_assets and docs:
        standalone_assets = []
        for item in sorted(dir_path.glob(pattern)):
            if item.is_file() and item.suffix.lower() in SUPPORTED_ASSET_EXTS:
                if item.suffix.lower() not in SUPPORTED_DOC_EXTS:
                    try:
                        size = item.stat().st_size
                        if size <= MAX_ASSET_SIZE:
                            with open(item, "rb") as af:
                                raw = af.read()
                            mime, _ = mimetypes.guess_type(str(item))
                            standalone_assets.append({
                                "rel_path": str(item.relative_to(dir_path)).replace("\\", "/"),
                                "basename": item.name,
                                "mime": mime or "application/octet-stream",
                                "size": size,
                                "sha1": hashlib.sha1(raw).hexdigest(),
                                "data": base64.b64encode(raw).decode("ascii")
                            })
                    except Exception:
                        pass
        if standalone_assets:
            # Attach to first document
            docs[0]["assets"].extend(standalone_assets)

    return docs


def build_envelope(docs: list, category_hint: str = None, sender_name: str = None) -> dict:
    """Wrap documents in a Protocol 1.0 JSON envelope."""
    hostname = socket.gethostname()
    sender_title = sender_name or f"Desktop ({os.environ.get('USER') or os.environ.get('USERNAME') or 'User'}@{hostname})"
    now_iso = datetime.now(timezone.utc).isoformat()
    batch_id = "cli_" + hashlib.md5(f"{now_iso}_{hostname}".encode()).hexdigest()[:12]

    return {
        "version": "1.0",
        "batch_id": batch_id,
        "created_at": now_iso,
        "category_hint": category_hint or "",
        "sender": {
            "title": sender_title,
            "type": "desktop",
            "hostname": hostname
        },
        "documents": docs
    }


def send_envelope(url: str, token: str, envelope: dict) -> dict:
    """Transmit envelope to the wiki's Postbox webhook endpoint."""
    url = url.rstrip("/")
    if not url.endswith("/api/admin.php"):
        endpoint = f"{url}/api/admin.php?action=ext_postbox_receive"
    else:
        endpoint = f"{url}?action=ext_postbox_receive"

    payload_bytes = json.dumps(envelope).encode("utf-8")

    req = urllib.request.Request(
        endpoint,
        data=payload_bytes,
        headers={
            "Content-Type": "application/json; charset=utf-8",
            "X-Postbox-Token": token,
            "User-Agent": f"QwikiPostboxDesktopCLI/{VERSION}"
        },
        method="POST"
    )

    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            status_code = resp.status
            body = resp.read().decode("utf-8")
            data = json.loads(body)
            return {"status": status_code, "data": data}
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8", errors="replace")
        try:
            data = json.loads(err_body)
        except Exception:
            data = {"error": f"HTTP {e.code}: {e.reason}"}
        return {"status": e.code, "data": data}
    except urllib.error.URLError as e:
        return {"status": 0, "data": {"error": f"Connection error: {e.reason}"}}


# -------------------------------------------------------------
# CLI Commands
# -------------------------------------------------------------

def cmd_config(args):
    cfg = load_config()
    profile = args.profile or "default"
    prof_data = cfg.get("profiles", {}).get(profile, {})

    if args.url:
        prof_data["url"] = args.url.rstrip("/")
    if args.token:
        prof_data["token"] = args.token.strip()

    if "profiles" not in cfg:
        cfg["profiles"] = {}
    cfg["profiles"][profile] = prof_data

    if args.url or args.token:
        save_config(cfg)
        print(f"[OK] Saved profile '{profile}' ({prof_data.get('url', 'no-url')})")
    else:
        # Display current config
        print(f"Configuration file: {get_config_file()}")
        print("\nActive Profiles:")
        profiles = cfg.get("profiles", {})
        if not profiles:
            print("  (No profiles configured yet. Run 'qwiki-postbox config --url <URL> --token <TOKEN>')")
        for p, d in profiles.items():
            t = d.get('token', '')
            masked = (t[:6] + "..." + t[-4:]) if len(t) > 10 else "***"
            print(f"  • {p}: {d.get('url', '')} (Token: {masked})")


def cmd_send(args):
    target_path = Path(args.path).resolve()
    if not target_path.exists():
        print(f"[Error] Path not found: {target_path}", file=sys.stderr)
        sys.exit(1)

    # Determine URL and Token
    cfg = load_config()
    profile = args.profile or "default"
    prof_data = cfg.get("profiles", {}).get(profile, {})

    url = args.url or prof_data.get("url")
    token = args.token or prof_data.get("token")

    if not args.dry_run and not args.json and (not url or not token):
        print("[Error] Missing wiki URL or Postbox token.", file=sys.stderr)
        print("Provide them with --url and --token, or save them with 'qwiki-postbox config --url <URL> --token <TOKEN>'", file=sys.stderr)
        sys.exit(1)

    category_hint = args.category
    docs = []

    if target_path.is_file():
        if not args.json:
            print(f"📄 Packaging document: {target_path.name}...")
        try:
            doc = package_single_file(target_path, title=args.title, category_hint=category_hint)
            docs.append(doc)
        except Exception as e:
            print(f"[Error] Failed to package {target_path}: {e}", file=sys.stderr)
            sys.exit(1)
    elif target_path.is_dir():
        if not args.json:
            print(f"📁 Scanning folder: {target_path} (recursive={args.recursive})...")
        docs = package_directory(target_path, recursive=args.recursive, category_hint=category_hint, all_assets=args.all_assets)
        if not docs:
            if not args.json:
                print(f"[Warning] No supported document files (.md, .html, .pdf) found in {target_path}", file=sys.stderr)
            sys.exit(0)
        if not args.json:
            print(f"   Found {len(docs)} document(s)")

    envelope = build_envelope(docs, category_hint=category_hint)

    total_assets = sum(len(d.get("assets", [])) for d in docs)
    batch_id = envelope["batch_id"]

    if not args.json:
        print(f"📦 Envelope prepared: Batch [{batch_id}] with {len(docs)} document(s) and {total_assets} embedded asset(s)")

    if args.json:
        print(json.dumps(envelope, indent=2))
        return

    if args.dry_run:
        print("\n--- DRY RUN SUMMARY ---")
        for i, d in enumerate(docs, 1):
            print(f" {i}. [{d['type'].upper()}] {d['title']} (slug: {d['slug']}) - {len(d['assets'])} asset(s)")
            for a in d["assets"]:
                print(f"     └─ 🖼️ {a['basename']} ({a['size']} bytes, {a['mime']})")
        print("\n[Dry Run Completed] No data sent.")
        return

    print(f"🚀 Transmitting to {url}...")
    res = send_envelope(url, token, envelope)

    if res["status"] == 200 and res["data"].get("success"):
        print(f"\n✅ SUCCESS! Documents delivered to Qwiki Postbox.")
        print(f"   Batch ID: {res['data'].get('batch_id', batch_id)}")
        print(f"   Destination: {url}")
        print("   The wiki administrator will review and file these into your category.")
    else:
        err_msg = res["data"].get("error") or f"HTTP {res['status']}"
        print(f"\n❌ FAILED: {err_msg}", file=sys.stderr)
        sys.exit(1)


def main():
    parser = argparse.ArgumentParser(
        prog="qwiki-postbox",
        description="Standalone Qwiki Desktop Postbox CLI Tool"
    )
    parser.add_argument("-v", "--version", action="version", version=f"%(prog)s {VERSION}")

    subparsers = parser.add_subparsers(dest="command", help="Subcommand to execute")

    # config command
    parser_cfg = subparsers.add_parser("config", help="Manage Qwiki Postbox connection settings")
    parser_cfg.add_argument("--url", help="Base URL of target Qwiki instance (e.g. https://wiki.example.com/)")
    parser_cfg.add_argument("--token", help="Receiving Postbox Token (32-character hex secret)")
    parser_cfg.add_argument("--profile", default="default", help="Profile name (default: 'default')")
    parser_cfg.set_defaults(func=cmd_config)

    # send command
    parser_send = subparsers.add_parser("send", help="Send a file or directory to Qwiki Postbox")
    parser_send.add_argument("path", help="Path to file (.md, .html, .pdf) or directory")
    parser_send.add_argument("--url", help="Override destination Qwiki base URL")
    parser_send.add_argument("--token", help="Override destination Postbox token")
    parser_send.add_argument("--profile", default="default", help="Profile to use from config")
    parser_send.add_argument("--category", "-c", help="Category / book name hint for the recipient wiki")
    parser_send.add_argument("--title", "-t", help="Override document title (single file only)")
    parser_send.add_argument("--recursive", "-r", action="store_true", default=True, help="Scan directories recursively (default: True)")
    parser_send.add_argument("--no-recursive", action="store_false", dest="recursive", help="Do not scan subdirectories")
    parser_send.add_argument("--all-assets", action="store_true", help="Also attach unreferenced image files in directory")
    parser_send.add_argument("--dry-run", action="store_true", help="Inspect and preview payload without sending")
    parser_send.add_argument("--json", action="store_true", help="Output envelope JSON directly to stdout")
    parser_send.set_defaults(func=cmd_send)

    args = parser.parse_args()
    if hasattr(args, "func"):
        args.func(args)
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
