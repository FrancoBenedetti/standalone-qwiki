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
import subprocess
import shutil
from datetime import datetime, timezone
from pathlib import Path
import urllib.request
import urllib.error

VERSION = "1.1.0"

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


def prompt_profile_gui(profiles: dict) -> str:
    """Prompt user to choose a target profile using platform-native GUI dialogs."""
    if not profiles:
        return None
    if len(profiles) == 1:
        return list(profiles.keys())[0]

    # Platform 1: Linux (GNOME zenity -> KDE kdialog)
    if sys.platform.startswith("linux"):
        if shutil.which("zenity"):
            cmd = [
                "zenity", "--list",
                "--title=Qwiki Postbox",
                "--text=Select Destination Wiki Profile:",
                "--column=Profile", "--column=Destination URL",
                "--width=540", "--height=320"
            ]
            for name, pdata in profiles.items():
                cmd.extend([name, pdata.get("url", "")])
            try:
                proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                if proc.returncode == 0:
                    selected = proc.stdout.strip()
                    if selected:
                        pname = selected.split("|")[0].strip()
                        if pname in profiles:
                            return pname
                return None
            except Exception:
                pass

        if shutil.which("kdialog"):
            cmd = ["kdialog", "--menu", "Select Destination Wiki Profile:", "--title", "Qwiki Postbox"]
            for name, pdata in profiles.items():
                cmd.extend([name, f"{name} ({pdata.get('url', '')})"])
            try:
                proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                if proc.returncode == 0 and proc.stdout.strip() in profiles:
                    return proc.stdout.strip()
                return None
            except Exception:
                pass

    # Platform 2: macOS (osascript / AppleScript dialog)
    elif sys.platform == "darwin":
        if shutil.which("osascript"):
            item_list = [f"{name} — {pdata.get('url', '')}" for name, pdata in profiles.items()]
            items_str = ", ".join(f'"{it}"' for it in item_list)
            first_item = item_list[0]
            script = f'''
            tell application "System Events"
                activate
                set chosen to choose from list {{{items_str}}} with title "Qwiki Postbox" with prompt "Select Destination Wiki Profile:" default items {{"{first_item}"}}
                if chosen is false then
                    return ""
                else
                    return item 1 of chosen
                end if
            end tell
            '''
            try:
                proc = subprocess.run(["osascript", "-e", script], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                if proc.returncode == 0 and proc.stdout.strip():
                    pname = proc.stdout.strip().split(" — ")[0].strip()
                    if pname in profiles:
                        return pname
                return None
            except Exception:
                pass

    # Platform 3: Windows (PowerShell Out-GridView)
    elif os.name == "nt" or sys.platform == "win32":
        if shutil.which("powershell"):
            ps_lines = ["$items = @("]
            for name, pdata in profiles.items():
                safe_name = name.replace("'", "''")
                safe_url = pdata.get("url", "").replace("'", "''")
                ps_lines.append(f"  [PSCustomObject]@{{ Profile = '{safe_name}'; URL = '{safe_url}' }}")
            ps_lines.append(")")
            ps_lines.append("$chosen = $items | Out-GridView -Title 'Qwiki Postbox - Select Destination Wiki' -OutputMode Single")
            ps_lines.append("if ($chosen) { Write-Output $chosen.Profile }")
            try:
                proc = subprocess.run(
                    ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", "\n".join(ps_lines)],
                    stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True
                )
                if proc.returncode == 0 and proc.stdout.strip() in profiles:
                    return proc.stdout.strip()
                return None
            except Exception:
                pass

    # Fallback to interactive terminal if TTY
    if sys.stdin and sys.stdin.isatty():
        print("\nMultiple Qwiki profiles configured:")
        keys = list(profiles.keys())
        for i, k in enumerate(keys, 1):
            print(f"  [{i}] {k:15} -> {profiles[k].get('url', '')}")
        try:
            ans = input(f"Select target wiki [1-{len(keys)}] (default: 1): ").strip()
            if not ans:
                return keys[0]
            idx = int(ans) - 1
            if 0 <= idx < len(keys):
                return keys[idx]
        except (ValueError, KeyboardInterrupt, EOFError):
            return None

    return "default" if "default" in profiles else (list(profiles.keys())[0] if profiles else None)


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
        r'<(?:img|video|audio|source)\b[^>]+src=["\'](?!https?://|data:|mailto:|#)([^"\']+)["\']',
        r'<a\b[^>]+href=["\'](?!https?://|data:|mailto:|#)([^"\']+)["\']'
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
        try:
            with open(file_path, "r", encoding="utf-8-sig") as f:
                content = f.read()
        except UnicodeDecodeError:
            with open(file_path, "r", encoding="latin-1") as f:
                content = f.read()

        if not title:
            if doc_type == "html":
                m = re.search(r"<title[^>]*>(.*?)</title>", content, re.IGNORECASE | re.DOTALL)
                if m:
                    clean_t = re.sub(r"\s+", " ", m.group(1)).strip()
                    if clean_t:
                        doc_title = clean_t
            elif doc_type == "markdown":
                m = re.search(r"^#\s+(.+)$", content, re.MULTILINE)
                if m:
                    clean_t = m.group(1).strip()
                    if clean_t:
                        doc_title = clean_t

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


def cmd_profiles(args):
    cfg = load_config()
    profiles = cfg.get("profiles", {})
    if args.json:
        print(json.dumps(profiles, indent=2))
        return
    print(f"Configuration file: {get_config_file()}")
    if not profiles:
        print("No profiles configured yet.")
        print("Run 'qwiki-postbox config --url <URL> --token <TOKEN>' to add one.")
        return
    print(f"\nConfigured Profiles ({len(profiles)}):")
    for name, pdata in profiles.items():
        t = pdata.get("token", "")
        masked = (t[:6] + "..." + t[-4:]) if len(t) > 10 else "***"
        print(f"  • {name:16} -> {pdata.get('url', 'no-url'):35} (Token: {masked})")


def cmd_send(args):
    # Collect target paths
    input_paths = args.paths if isinstance(args.paths, list) else [args.paths]
    valid_paths = []
    for p in input_paths:
        tp = Path(p).resolve()
        if not tp.exists():
            print(f"[Warning] Path not found: {tp}", file=sys.stderr)
        else:
            valid_paths.append(tp)

    if not valid_paths:
        print("[Error] No valid file or folder paths provided.", file=sys.stderr)
        sys.exit(1)

    cfg = load_config()
    profiles = cfg.get("profiles", {})

    # Determine URL, Token, and Profile
    if args.url and args.token:
        url = args.url.rstrip("/")
        token = args.token.strip()
        profile_label = "custom URL"
    else:
        profile_name = args.profile
        if not profile_name:
            if args.gui:
                profile_name = prompt_profile_gui(profiles)
                if profile_name is None:
                    if not profiles:
                        print("[Error] No Qwiki profiles configured. Run 'qwiki-postbox config --url <URL> --token <TOKEN>' first.", file=sys.stderr)
                        sys.exit(1)
                    print("[Cancelled] Transfer aborted by user.")
                    sys.exit(0)
            elif len(profiles) > 1 and sys.stdin and sys.stdin.isatty() and not args.json and not args.dry_run:
                profile_name = prompt_profile_gui(profiles)
                if profile_name is None:
                    print("[Cancelled] Transfer aborted by user.")
                    sys.exit(0)
            else:
                profile_name = "default" if "default" in profiles else (list(profiles.keys())[0] if profiles else "default")

        prof_data = profiles.get(profile_name, {})
        url = args.url or prof_data.get("url")
        token = args.token or prof_data.get("token")
        profile_label = f"profile '{profile_name}'"

    if not args.dry_run and not args.json and (not url or not token):
        print(f"[Error] Missing wiki URL or Postbox token for {profile_label}.", file=sys.stderr)
        print("Configure it with 'qwiki-postbox config --url <URL> --token <TOKEN>' or specify --url and --token.", file=sys.stderr)
        sys.exit(1)

    category_hint = args.category
    docs = []

    for target_path in valid_paths:
        if target_path.is_file():
            if not args.json:
                print(f"📄 Packaging document: {target_path.name}...")
            try:
                single_title = args.title if len(valid_paths) == 1 else None
                doc = package_single_file(target_path, title=single_title, category_hint=category_hint)
                docs.append(doc)
            except Exception as e:
                print(f"[Error] Failed to package {target_path}: {e}", file=sys.stderr)
        elif target_path.is_dir():
            if not args.json:
                print(f"📁 Scanning folder: {target_path} (recursive={args.recursive})...")
            dir_docs = package_directory(target_path, recursive=args.recursive, category_hint=category_hint, all_assets=args.all_assets)
            if dir_docs:
                docs.extend(dir_docs)
                if not args.json:
                    print(f"   Found {len(dir_docs)} document(s) in {target_path.name}")
            else:
                if not args.json:
                    print(f"[Warning] No supported document files (.md, .html, .pdf) found in {target_path}", file=sys.stderr)

    if not docs:
        if not args.json:
            print("[Warning] No documents could be packaged from the provided paths.", file=sys.stderr)
        sys.exit(0)

    envelope = build_envelope(docs, category_hint=category_hint)
    total_assets = sum(len(d.get("assets", [])) for d in docs)
    batch_id = envelope["batch_id"]

    if not args.json:
        print(f"📦 Envelope prepared: Batch [{batch_id}] with {len(docs)} document(s) and {total_assets} embedded asset(s)")

    if args.json:
        print(json.dumps(envelope, indent=2))
        return

    if args.dry_run:
        print(f"\n--- DRY RUN SUMMARY (Target: {profile_label} -> {url or 'none'}) ---")
        for i, d in enumerate(docs, 1):
            print(f" {i}. [{d['type'].upper()}] {d['title']} (slug: {d['slug']}) - {len(d['assets'])} asset(s)")
            for a in d["assets"]:
                print(f"     └─ 🖼️ {a['basename']} ({a['size']} bytes, {a['mime']})")
        print("\n[Dry Run Completed] No data sent.")
        return

    print(f"🚀 Transmitting to {url} ({profile_label})...")
    res = send_envelope(url, token, envelope)

    if res["status"] == 200 and res["data"].get("success"):
        print(f"\n✅ SUCCESS! Documents delivered to Qwiki Postbox ({profile_label}).")
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

    # profiles command
    parser_prof = subparsers.add_parser("profiles", help="List configured target wiki profiles")
    parser_prof.add_argument("--json", action="store_true", help="Output profiles in JSON format")
    parser_prof.set_defaults(func=cmd_profiles)

    # send command
    parser_send = subparsers.add_parser("send", help="Send file(s) or directories to Qwiki Postbox")
    parser_send.add_argument("paths", nargs="+", metavar="PATH", help="Path(s) to file (.md, .html, .pdf) or directory")
    parser_send.add_argument("--url", help="Override destination Qwiki base URL")
    parser_send.add_argument("--token", help="Override destination Postbox token")
    parser_send.add_argument("--profile", "-p", default=None, help="Target profile name from config")
    parser_send.add_argument("--interactive", "--gui", "-i", action="store_true", dest="gui", help="Interactively choose destination profile if multiple exist")
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
