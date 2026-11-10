#!/bin/bash
# Bygger resan-inleed.zip: det som laddas upp till jarnlo.se/resan/ på Inleed.
# config.php, meta.php, uploadade bilder, git och projektfiler följer aldrig med.
set -euo pipefail
cd "$(dirname "$0")"
tmp=$(mktemp -d)
mkdir -p "$tmp/uploads"
cp index.html manifest.json icon-180.png icon-192.png icon-512.png api.php config.sample.php .htaccess "$tmp/"
cp uploads/.htaccess "$tmp/uploads/"
rm -f resan-inleed.zip
(cd "$tmp" && zip -qr "$OLDPWD/resan-inleed.zip" .)
rm -rf "$tmp"
unzip -l resan-inleed.zip
