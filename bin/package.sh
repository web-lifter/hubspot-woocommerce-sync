#!/usr/bin/env bash
set -euo pipefail

SLUG="xero-x-woocommerce"


PLUGIN_FILE="woo-xero-sync.php"

if [ ! -f "$PLUGIN_FILE" ]; then
  echo "ERROR: $PLUGIN_FILE not found in working directory $(pwd)" >&2
  exit 1
fi

# Ensure the plugin's declared slug matches the packaging slug
DECLARED_SLUG=$(grep -E "^\s*define\('WXL_PLUGIN_SLUG'" "$PLUGIN_FILE" | sed -E "s/.*'([^']+)'.*/\1/")
if [ "$DECLARED_SLUG" != "$SLUG" ]; then
  echo "ERROR: WXL_PLUGIN_SLUG in $PLUGIN_FILE is '$DECLARED_SLUG' but expected '$SLUG'" >&2
  exit 1
fi

# Set up temp build directory
TMP_DIR=$(mktemp -d)
PACKAGE_DIR="$TMP_DIR/$SLUG"
mkdir "$PACKAGE_DIR"

rsync -a --exclude '.git' --exclude '.github' --exclude 'bin' ./ "$PACKAGE_DIR/"

ZIP_FILE="$SLUG.zip"
rm -f "$ZIP_FILE"
(
  cd "$TMP_DIR"
  zip -rq "$OLDPWD/$ZIP_FILE" "$SLUG"
)
FIRST_ENTRY=$(zipinfo -1 "$ZIP_FILE" | head -n1 | cut -d/ -f1)
if [ "$FIRST_ENTRY" != "$SLUG" ]; then
  echo "ERROR: Zip root folder '$FIRST_ENTRY' does not match slug '$SLUG'" >&2
  exit 1
fi
rm -rf "$TMP_DIR"

echo "zip_file=$ZIP_FILE" >> "$GITHUB_OUTPUT"
