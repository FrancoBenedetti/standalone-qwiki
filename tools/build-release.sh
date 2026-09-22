#!/usr/bin/env bash
# tools/build-release.sh
# Packages a clean, production-ready release zip for Standalone Qwiki
# Excludes test suites, agent guidelines, dev dependencies, and internal tools via .gitattributes

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "${SCRIPT_DIR}")"
cd "${REPO_ROOT}"

# 1. Resolve current version
VERSION=$(php -r "require 'lib/Core/Config.php'; echo Qwiki\Core\Config::VERSION;")
DIST_DIR="${REPO_ROOT}/dist"
ZIP_NAME="standalone-qwiki-v${VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"

echo "===================================================="
echo "  Building Clean Release: Standalone Qwiki v${VERSION}"
echo "===================================================="

# 2. Verify git attributes exists
if [ ! -f ".gitattributes" ]; then
    echo "❌ ERROR: .gitattributes file not found. Aborting."
    exit 1
fi

# 3. Create dist directory
mkdir -p "${DIST_DIR}"

# 4. Generate clean production zip archive using worktree attributes
echo "📦 Packaging archive with git archive..."
git archive --worktree-attributes --format=zip --prefix="standalone-qwiki/" -o "${ZIP_PATH}" HEAD

# 5. Verify exclusions
echo "🔍 Verifying archive cleanliness..."
FORBIDDEN_MATCHES=$(unzip -l "${ZIP_PATH}" | grep -E "standalone-qwiki/(tests/|node_modules/|AGENTS\.md|demo-reload\.php|package\.json|package-lock\.json|\.git|\.github|tools/build-release\.sh)" || true)

if [ -n "${FORBIDDEN_MATCHES}" ]; then
    echo "❌ ERROR: Forbidden files found in release archive:"
    echo "${FORBIDDEN_MATCHES}"
    rm -f "${ZIP_PATH}"
    exit 1
fi

# 6. Display statistics and checksum
FILE_COUNT=$(unzip -l "${ZIP_PATH}" | tail -n 1 | awk '{print $2}')
ZIP_SIZE=$(du -h "${ZIP_PATH}" | cut -f1)
SHA256=$(sha256sum "${ZIP_PATH}" | cut -d' ' -f1)

echo "✅ Clean release package built successfully!"
echo "----------------------------------------------------"
echo "  Artifact:    ${ZIP_PATH}"
echo "  File count:  ${FILE_COUNT} files"
echo "  Size:        ${ZIP_SIZE}"
echo "  SHA256:      ${SHA256}"
echo "----------------------------------------------------"
echo "Ready for upload to GitHub Releases or distribution."
