#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

for command_name in curl php; do
	if ! command -v "$command_name" >/dev/null 2>&1; then
		echo "[theme-matrix] Required command not found: ${command_name}" >&2
		exit 1
	fi
done

TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/npcink-ad-theme-matrix.XXXXXX")"
cleanup() {
	rm -rf "$TEMP_DIR"
}
trap cleanup EXIT HUP INT TERM

download_theme() {
	local slug="$1"
	local version="$2"
	local expected_sha256="$3"
	local destination="$TEMP_DIR/${slug}.zip"

	curl --fail --location --silent --show-error \
		--retry 3 --retry-delay 1 --retry-all-errors \
		"https://downloads.wordpress.org/theme/${slug}.${version}.zip" \
		--output "$destination"
	local actual_sha256
	actual_sha256="$(php -r 'echo hash_file("sha256", $argv[1]);' "$destination")"
	if [[ "$actual_sha256" != "$expected_sha256" ]]; then
		echo "[theme-matrix] ${slug} ${version} checksum mismatch" >&2
		exit 1
	fi

	echo "$destination"
}

run_theme() {
	local slug="$1"
	local zip_path="$2"
	local wordpress_version="$3"
	local php_version="$4"

	echo "[theme-matrix] ${slug} on WordPress ${wordpress_version} / PHP ${php_version}"
	WP_VERSION="$wordpress_version" \
	PHP_VERSION="$php_version" \
	NPCINK_AD_E2E_FIXTURE_MODE='theme-bars' \
	NPCINK_AD_E2E_THEME_SLUG="$slug" \
	NPCINK_AD_E2E_THEME_ZIP="$zip_path" \
		"$SCRIPT_DIR/run-with-playground-startup-retry.sh" \
		"$SCRIPT_DIR/run.sh" tests/e2e/theme-page-bars.spec.ts
}

TWENTY_TWENTY_ONE_ZIP="$(download_theme \
	'twentytwentyone' \
	'2.8' \
	'6ff2fbf396f878eba4c2997c931b36476407c9da5f73d9f36a1b3995237afd1e')"
TWENTY_TWENTY_FIVE_ZIP="$(download_theme \
	'twentytwentyfive' \
	'1.5' \
	'f333ce53aaa4049639298247d03480a4bd9b56fa8f0fea0440da262d7d595ba4')"

run_theme 'twentytwentyone' "$TWENTY_TWENTY_ONE_ZIP" '6.5' '8.1'
run_theme 'twentytwentyfive' "$TWENTY_TWENTY_FIVE_ZIP" '7.1' '8.5'

echo '[theme-matrix] OK: classic and block theme page-bar delivery'
