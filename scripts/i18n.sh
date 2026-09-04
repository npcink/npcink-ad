#!/usr/bin/env bash
set -euo pipefail
export LC_ALL=C

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="npcink-ad"
LOCALE="zh_CN"
LANGUAGES_DIR="$ROOT_DIR/languages"
POT_FILE="$LANGUAGES_DIR/${DOMAIN}.pot"
PO_FILE="$LANGUAGES_DIR/${DOMAIN}-${LOCALE}.po"
MO_FILE="$LANGUAGES_DIR/${DOMAIN}-${LOCALE}.mo"
SCRIPT_HANDLES=(
	"npcink-ad-block-editor"
	"npcink-ad-promotion-editor"
)
SCRIPT_SOURCES=(
	"build/block-editor.js"
	"build/promotion-editor.js"
)
JSON_FILES=(
	"$LANGUAGES_DIR/${DOMAIN}-${LOCALE}-${SCRIPT_HANDLES[0]}.json"
	"$LANGUAGES_DIR/${DOMAIN}-${LOCALE}-${SCRIPT_HANDLES[1]}.json"
)
MODE="${1:-check}"

cd "$ROOT_DIR"

for required_command in php pnpm msgcmp msgfmt wp; do
	if ! command -v "$required_command" >/dev/null 2>&1; then
		echo "[i18n] Required command not found: ${required_command}" >&2
		exit 1
	fi
done

WP_CLI_BIN="${WP_CLI_BIN:-$(command -v wp)}"
PHP_BIN="${PHP_BIN:-$(command -v php)}"

run_wp() {
	"$WP_CLI_BIN" "$@"
}

make_pot() {
	local destination="$1"
	run_wp i18n make-pot . "$destination" \
		--slug="$DOMAIN" \
		--domain="$DOMAIN" \
		--headers='{"Report-Msgid-Bugs-To":"https://github.com/npcink/npcink-ad/issues","POT-Creation-Date":""}' \
		--skip-block-json \
		--exclude=.github,assets/js,dist,node_modules,playwright-report,test-results,tests,vendor
}

TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/npcink-ad-i18n.XXXXXX")"
trap 'rm -rf "$TEMP_DIR"' EXIT

assert_generated_catalog_count() {
	local directory="$1"
	local generated_count
	generated_count="$(find "$directory" -maxdepth 1 -type f -name "${DOMAIN}-${LOCALE}-*.json" -print | wc -l | tr -d '[:space:]')"
	if [[ "$generated_count" -ne "${#SCRIPT_HANDLES[@]}" ]]; then
		echo "[i18n] Expected ${#SCRIPT_HANDLES[@]} JavaScript catalogs, found ${generated_count}." >&2
		exit 1
	fi
}

generated_catalog_for_source() {
	local directory="$1"
	local source="$2"
	"$PHP_BIN" -r '
		$matches = array();
		foreach (glob($argv[1] . "/*.json") ?: array() as $file) {
			$data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
			if (($data["source"] ?? "") === $argv[2]) {
				$matches[] = $file;
			}
		}
		if (1 !== count($matches)) {
			exit(1);
		}
		echo $matches[0];
	' "$directory" "$source"
}

refresh_catalogs() {
	pnpm run build
	mkdir -p "$LANGUAGES_DIR"
	make_pot "$POT_FILE"

	if [[ ! -f "$PO_FILE" ]]; then
		echo "[i18n] Missing translation source: ${PO_FILE}" >&2
		exit 1
	fi

	run_wp i18n update-po "$POT_FILE" "$PO_FILE"
	msgfmt --check --check-format -o "$MO_FILE" "$PO_FILE"

	local generated_dir="$TEMP_DIR/refresh-json"
	mkdir -p "$generated_dir"
	run_wp i18n make-json "$PO_FILE" "$generated_dir" --no-purge --pretty-print
	assert_generated_catalog_count "$generated_dir"

	local index
	for index in "${!SCRIPT_HANDLES[@]}"; do
		local generated_json
		generated_json="$(generated_catalog_for_source "$generated_dir" "${SCRIPT_SOURCES[$index]}")" || {
			echo "[i18n] Missing catalog for ${SCRIPT_SOURCES[$index]}." >&2
			exit 1
		}
		mv "$generated_json" "${JSON_FILES[$index]}"
	done
}

check_catalogs() {
	for required_file in "$POT_FILE" "$PO_FILE" "$MO_FILE" "${JSON_FILES[@]}"; do
		if [[ ! -f "$required_file" ]]; then
			echo "[i18n] Missing catalog: ${required_file}" >&2
			exit 1
		fi
	done

	make_pot "$TEMP_DIR/${DOMAIN}.pot"
	msgcmp --use-untranslated "$POT_FILE" "$TEMP_DIR/${DOMAIN}.pot"
	msgcmp --use-untranslated "$TEMP_DIR/${DOMAIN}.pot" "$POT_FILE"
	msgcmp "$PO_FILE" "$POT_FILE"

	local statistics
	statistics="$(msgfmt --check --check-format --statistics -o "$TEMP_DIR/${DOMAIN}-${LOCALE}.mo" "$PO_FILE" 2>&1)"
	echo "[i18n] ${statistics}"
	if [[ "$statistics" == *untranslated* || "$statistics" == *fuzzy* ]]; then
		echo "[i18n] The Simplified Chinese catalog is incomplete." >&2
		exit 1
	fi
	cmp "$MO_FILE" "$TEMP_DIR/${DOMAIN}-${LOCALE}.mo"

	local generated_dir="$TEMP_DIR/check-json"
	mkdir -p "$generated_dir"
	run_wp i18n make-json "$PO_FILE" "$generated_dir" --no-purge --pretty-print
	assert_generated_catalog_count "$generated_dir"

	local index
	for index in "${!SCRIPT_HANDLES[@]}"; do
		local generated_json
		generated_json="$(generated_catalog_for_source "$generated_dir" "${SCRIPT_SOURCES[$index]}")" || {
			echo "[i18n] Missing generated catalog for ${SCRIPT_SOURCES[$index]}." >&2
			exit 1
		}
		"$PHP_BIN" -r '
			$committed = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
			$generated = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
			unset($committed["generator"], $generated["generator"]);
			if ($committed != $generated) {
				fwrite(STDERR, "[i18n] The JavaScript translation catalog is stale: " . $argv[1] . "\n");
				exit(1);
			}
		' "${JSON_FILES[$index]}" "$generated_json"
	done

	echo "[i18n] OK: complete ${LOCALE} PHP and JavaScript catalogs"
}

case "$MODE" in
	refresh)
		refresh_catalogs
		check_catalogs
		;;
	check)
		check_catalogs
		;;
	*)
		echo "Usage: scripts/i18n.sh [refresh|check]" >&2
		exit 2
		;;
esac
