#!/usr/bin/env bash
#
# Builds the exact release artifact CI/release.yml ships to wp.org, locally.
# Useful for a pre-flight sanity check, or for running
# WordPress/plugin-check-action against a real package before it's tagged.
#
# Output: build/zoviz-ai-studio.zip (a top-level "zoviz-ai-studio/" folder,
# as WordPress.org expects) plus a scratch copy at build/zoviz-ai-studio/
# left behind for inspection.
#
# Usage: bin/build-zip.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SLUG="zoviz-ai-studio"
STAGE_DIR="build/${SLUG}"
ZIP_PATH="build/${SLUG}.zip"

echo "==> Installing production PHP dependencies (no dev, optimized autoloader)"
composer install --no-dev --classmap-authoritative --optimize-autoloader --quiet

echo "==> Building JS/CSS assets"
npm run build --silent

echo "==> Restoring dev PHP dependencies for local work"
trap 'composer install --quiet' EXIT

echo "==> Staging release contents"
rm -rf "${STAGE_DIR}" "${ZIP_PATH}"
mkdir -p "${STAGE_DIR}"

# rsync respects .distignore-listed paths by excluding them explicitly —
# this list must stay in sync with .distignore (both exclude the same dev
# files from what ships).
#
# Note: `build/` itself must NOT be excluded — `npm run build` (above) puts
# the compiled JS/CSS the plugin loads at runtime there (see webpack.config.js
# and Kernel/Assets.php), and that's exactly what needs to ship. Only this
# script's own scratch output inside build/ (the staging dir and the zip)
# has to be excluded, so it doesn't get copied into itself. Both exclusions
# are anchored to the repo root so they don't accidentally match anything
# else named "zoviz-ai-studio*".
rsync -a . "${STAGE_DIR}/" \
	--exclude-from=.distignore \
	--exclude "/${STAGE_DIR}" \
	--exclude "/${ZIP_PATH}" \
	--exclude ".git" \
	--exclude "vendor/bin"

echo "==> Zipping"
(cd build && zip -rq "${SLUG}.zip" "${SLUG}")

echo "==> Done: ${ZIP_PATH}"
unzip -l "${ZIP_PATH}" | tail -20
