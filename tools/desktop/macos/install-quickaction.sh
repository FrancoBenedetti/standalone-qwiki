#!/usr/bin/env bash
# ==============================================================================
# Standalone Qwiki - macOS Finder Quick Action Setup
# Adds "Send to Qwiki" to Finder context menu and Quick Actions.
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PYTHON_CLI="${SCRIPT_DIR}/qwiki-postbox.py"

if [ ! -f "${PYTHON_CLI}" ]; then
    echo "Error: Could not locate qwiki-postbox.py at ${PYTHON_CLI}"
    exit 1
fi

SERVICES_DIR="${HOME}/Library/Services"
WORKFLOW_NAME="Send to Qwiki.workflow"
WORKFLOW_DIR="${SERVICES_DIR}/${WORKFLOW_NAME}"

echo "Installing macOS Quick Action to ${WORKFLOW_DIR}..."

mkdir -p "${SERVICES_DIR}"
rm -rf "${WORKFLOW_DIR}"
mkdir -p "${WORKFLOW_DIR}/Contents"

cat <<EOF > "${WORKFLOW_DIR}/Contents/document.wflow"
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>AMApplicationBuild</key>
    <string>521.1</string>
    <key>AMApplicationVersion</key>
    <string>2.10</string>
    <key>AMDocumentVersion</key>
    <string>2</string>
    <key>actions</key>
    <array>
        <dict>
            <key>action</key>
            <dict>
                <key>AMAccepts</key>
                <dict>
                    <key>Container</key>
                    <string>List</string>
                    <key>Types</key>
                    <array>
                        <string>com.apple.cocoa.path</string>
                    </array>
                </dict>
                <key>AMActionVersion</key>
                <string>2.0.3</string>
                <key>AMParameterProperties</key>
                <dict>
                    <key>COMMAND_STRING</key>
                    <dict/>
                </dict>
                <key>ActionBundlePath</key>
                <string>/System/Library/Automator/Run Shell Script.action</string>
                <key>ActionName</key>
                <string>Run Shell Script</string>
                <key>ActionParameters</key>
                <dict>
                    <key>COMMAND_STRING</key>
                    <string>for f in "\$@"
do
    python3 "${PYTHON_CLI}" send "\$f"
    STATUS=\$?
    NAME=\$(basename "\$f")
    if [ \$STATUS -eq 0 ]; then
        osascript -e "display notification \"Delivered '\$NAME' to Qwiki Postbox\" with title \"Qwiki Postbox\""
    else
        osascript -e "display alert \"Qwiki Postbox Error\" message \"Failed to deliver '\$NAME' to Qwiki. Check terminal for details.\""
    fi
done</string>
                    <key>inputMethod</key>
                    <integer>1</integer>
                    <key>shell</key>
                    <string>/bin/bash</string>
                    <key>source</key>
                    <string></string>
                </dict>
            </dict>
        </dict>
    </array>
</dict>
</plist>
EOF

echo ""
echo "Installation complete!"
echo "You can now right-click any file or folder in Finder and select:"
echo "  Quick Actions -> Send to Qwiki"
