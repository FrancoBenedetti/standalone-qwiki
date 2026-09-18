#!/usr/bin/env bash
# ==============================================================================
# Standalone Qwiki - Linux Desktop Integration Installer
# Adds "Send to Qwiki" to GNOME Nautilus, Nemo, and Caja file managers.
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PYTHON_CLI="${SCRIPT_DIR}/qwiki-postbox.py"

if [ ! -f "${PYTHON_CLI}" ]; then
    echo "Error: Could not locate qwiki-postbox.py at ${PYTHON_CLI}"
    exit 1
fi

echo "Installing 'Send to Qwiki' for Linux File Managers..."

# Support Nautilus, Nemo, and Caja script directories
TARGET_DIRS=(
    "${HOME}/.local/share/nautilus/scripts"
    "${HOME}/.local/share/nemo/scripts"
    "${HOME}/.config/caja/scripts"
)

INSTALLED_COUNT=0

for DIR in "${TARGET_DIRS[@]}"; do
    mkdir -p "${DIR}"
    TARGET_SCRIPT="${DIR}/Send to Qwiki"

    cat <<'EOF' > "${TARGET_SCRIPT}"
#!/usr/bin/env bash
# Send selected files or folders to Qwiki Postbox

CLI_PATH="__CLI_PATH__"

if [ -z "${NAUTILUS_SCRIPT_SELECTED_FILE_PATHS}" ] && [ -z "${NEMO_SCRIPT_SELECTED_FILE_PATHS}" ]; then
    # Fallback to arguments
    SELECTED=("$@")
else
    # Read newline-delimited paths
    IFS=$'\n' read -d '' -r -a SELECTED <<< "${NAUTILUS_SCRIPT_SELECTED_FILE_PATHS:-$NEMO_SCRIPT_SELECTED_FILE_PATHS}"
fi

if [ ${#SELECTED[@]} -eq 0 ]; then
    exit 0
fi

# Pass all selected files with --gui for multi-profile selection dialog if needed
OUTPUT=$(python3 "${CLI_PATH}" send "${SELECTED[@]}" --gui 2>&1)
STATUS=$?

if [ ${STATUS} -eq 0 ]; then
    if [[ "${OUTPUT}" == *"[Cancelled]"* ]]; then
        exit 0
    fi
    if command -v notify-send >/dev/null 2>&1; then
        COUNT=${#SELECTED[@]}
        if [ ${COUNT} -eq 1 ]; then
            NAME="$(basename "${SELECTED[0]}")"
            notify-send -i document-send "Qwiki Postbox" "Delivered: ${NAME}"
        else
            notify-send -i document-send "Qwiki Postbox" "Delivered ${COUNT} document(s) to Qwiki"
        fi
    fi
else
    if command -v zenity >/dev/null 2>&1; then
        zenity --error --title="Qwiki Postbox Error" --text="Failed to send to Qwiki:\n\n${OUTPUT}"
    elif command -v notify-send >/dev/null 2>&1; then
        notify-send -u critical -i dialog-error "Qwiki Postbox Error" "${OUTPUT}"
    fi
fi
EOF

    # Replace placeholder with actual path
    sed -i "s|__CLI_PATH__|${PYTHON_CLI}|g" "${TARGET_SCRIPT}"
    chmod +x "${TARGET_SCRIPT}"
    INSTALLED_COUNT=$((INSTALLED_COUNT + 1))
    echo "Installed to: ${TARGET_SCRIPT}"
done

if command -v nautilus >/dev/null 2>&1; then
    echo "Reloading Nautilus to refresh context menu scripts..."
    nautilus -q >/dev/null 2>&1 || true
fi

echo ""
echo "Installation complete!"
echo ""
echo "How to use in your File Manager:"
echo "1. SELECT any document (e.g. .md, .html) or folder."
echo "   (Note: In GNOME/Nautilus, the 'Scripts' submenu only appears when an item is selected, not on empty space.)"
echo "2. Right-click the selected file or folder."
echo "3. Hover or click 'Scripts' in the context menu, then choose:"
echo "     'Send to Qwiki'"
echo ""
echo "Tip: If 'Scripts' is not visible after install, restart your file manager with: nautilus -q"
