#!/usr/bin/env bash
#
# Rewrites every file that carries the plugin version so they always agree.
# Called by semantic-release's `exec` plugin during a release
# (see docs/release-process.md) — never run this by hand outside that flow
# unless you're deliberately fixing a drift.
#
# Usage: bin/bump-version.sh <version>   (e.g. 1.2.3 — no leading "v")

set -euo pipefail

if [ $# -ne 1 ]; then
	echo "Usage: $0 <version>" >&2
	exit 1
fi

VERSION="$1"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]; then
	echo "Refusing to bump to a non-semver version: '$VERSION'" >&2
	exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Portable in-place sed (GNU and BSD/macOS sed differ on -i).
sed_inplace() {
	if sed --version >/dev/null 2>&1; then
		sed -i "$1" "$2"
	else
		sed -i '' "$1" "$2"
	fi
}

# 1. Main plugin file header: "Version:           X.Y.Z"
sed_inplace "s/^\( \* Version:[[:space:]]*\).*/\1${VERSION}/" zoviz-ai-studio.php

# 2. readme.txt: "Stable tag: X.Y.Z" — must always equal the header above.
sed_inplace "s/^Stable tag:.*/Stable tag: ${VERSION}/" readme.txt

# 3. Kernel\Plugin::VERSION
sed_inplace "s/const VERSION = '[^']*';/const VERSION = '${VERSION}';/" src/Kernel/Plugin.php

# 4. package.json "version" field (top-level only).
sed_inplace "0,/\"version\": \"[^\"]*\"/s//\"version\": \"${VERSION}\"/" package.json

# 5. composer.json "version" field (top-level only).
sed_inplace "0,/\"version\": \"[^\"]*\"/s//\"version\": \"${VERSION}\"/" composer.json

echo "Bumped to ${VERSION}:"
grep -n "Version:" zoviz-ai-studio.php | head -1
grep -n "Stable tag:" readme.txt
grep -n "const VERSION" src/Kernel/Plugin.php
grep -n "\"version\":" package.json | head -1
grep -n "\"version\":" composer.json | head -1
