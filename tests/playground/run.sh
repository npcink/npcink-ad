#!/bin/sh

set -eu

PLAYGROUND_CLI_VERSION='3.1.44'
WP_VERSION="${WP_VERSION:-latest}"
PHP_VERSION="${PHP_VERSION:-8.5}"

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)
PLUGIN_VERSION=$(sed -n '/^[[:space:]]*\*[[:space:]]*Version:/ { s/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//; p; q; }' "$PROJECT_DIR/npcink-ad.php")

if [ -z "$PLUGIN_VERSION" ]; then
	echo 'Could not read the plugin Version header from npcink-ad.php.' >&2
	exit 1
fi

PLUGIN_ZIP="${PLUGIN_ZIP:-$PROJECT_DIR/dist/npcink-ad-$PLUGIN_VERSION.zip}"

for command_name in node npx jq; do
	if ! command -v "$command_name" >/dev/null 2>&1; then
		echo "Missing required command: $command_name" >&2
		exit 1
	fi
done

if [ ! -f "$PLUGIN_ZIP" ]; then
	echo "Plugin ZIP not found: $PLUGIN_ZIP" >&2
	exit 1
fi

TEMP_DIR=$(mktemp -d "${TMPDIR:-/tmp}/npcink-ad-playground.XXXXXX")
cleanup() {
	rm -rf "$TEMP_DIR"
}
trap cleanup EXIT HUP INT TERM

mkdir -p "$TEMP_DIR/results"
cp "$PLUGIN_ZIP" "$TEMP_DIR/npcink-ad.zip"
cp "$SCRIPT_DIR/smoke.php" "$TEMP_DIR/smoke.php"
mkdir -p "$TEMP_DIR/language-pack"
cp "$PROJECT_DIR/languages/npcink-ad-zh_CN.mo" "$TEMP_DIR/language-pack/npcink-ad-zh_CN.mo"
cp "$PROJECT_DIR/languages/npcink-ad-zh_CN-npcink-ad-block-editor.json" "$TEMP_DIR/language-pack/npcink-ad-zh_CN-f082745a2ca724d8e3a123193be09fbe.json"
cp "$PROJECT_DIR/languages/npcink-ad-zh_CN-npcink-ad-promotion-editor.json" "$TEMP_DIR/language-pack/npcink-ad-zh_CN-74e2c4c8df75775a490c1e153e6a09cd.json"
jq \
	--arg wordpress "$WP_VERSION" \
	--arg php "$PHP_VERSION" \
	'.preferredVersions = {php: $php, wp: $wordpress}' \
	"$SCRIPT_DIR/blueprint.json" > "$TEMP_DIR/blueprint.json"

npx -y "@wp-playground/cli@$PLAYGROUND_CLI_VERSION" run-blueprint \
	--blueprint="$TEMP_DIR/blueprint.json" \
	--blueprint-may-read-adjacent-files \
	--mount="$TEMP_DIR/results:/wordpress/wp-content/npcink-ad-smoke-results" \
	--wp="$WP_VERSION" \
	--verbosity=normal

RESULT_FILE="$TEMP_DIR/results/result.json"
if [ ! -f "$RESULT_FILE" ]; then
	echo 'Playground did not produce a smoke result.' >&2
	exit 1
fi

ACTUAL_STATUS=$(jq -r '.status' "$RESULT_FILE")
ACTUAL_MODEL=$(jq -r '.model' "$RESULT_FILE")
ACTUAL_WORDPRESS=$(jq -r '.wordpress' "$RESULT_FILE")
ACTUAL_PHP=$(jq -r '.php' "$RESULT_FILE")

if [ "$ACTUAL_STATUS" != 'NPCINK_AD_SMOKE_OK' ]; then
	echo "Unexpected smoke status: $ACTUAL_STATUS" >&2
	exit 1
fi

if [ "$ACTUAL_MODEL" != 'single-promotion' ]; then
	echo "Unexpected data model: $ACTUAL_MODEL" >&2
	exit 1
fi

case "$PHP_VERSION" in
	latest)
		;;
	*)
		case "$ACTUAL_PHP" in
			"$PHP_VERSION".*) ;;
			*)
				echo "Requested PHP $PHP_VERSION but Playground ran $ACTUAL_PHP" >&2
				exit 1
				;;
		esac
		;;
esac

case "$WP_VERSION" in
	latest|nightly|beta)
		;;
	*)
		case "$ACTUAL_WORDPRESS" in
			"$WP_VERSION"|"$WP_VERSION".*) ;;
			*)
				echo "Requested WordPress $WP_VERSION but Playground ran $ACTUAL_WORDPRESS" >&2
				exit 1
				;;
		esac
		;;
esac

cat "$RESULT_FILE"
