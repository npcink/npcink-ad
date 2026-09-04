#!/usr/bin/env bash
set -euo pipefail

attempts="${NPCINK_AD_AUDIT_ATTEMPTS:-3}"
if ! [[ "$attempts" =~ ^[1-9][0-9]*$ ]]; then
	echo "[audit] NPCINK_AD_AUDIT_ATTEMPTS must be a positive integer" >&2
	exit 2
fi

for (( attempt = 1; attempt <= attempts; attempt++ )); do
	echo "[audit] pnpm audit attempt ${attempt}/${attempts}"
	if pnpm audit --audit-level=high; then
		exit 0
	fi
	if (( attempt < attempts )); then
		sleep 5
	fi
done

echo "[audit] pnpm audit did not complete successfully after ${attempts} attempts" >&2
exit 1
