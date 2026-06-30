#!/usr/bin/env bash
#
# Self-bootstrapping PHP_CodeSniffer + WordPress Coding Standards runner.
#
# On first run it installs PHPCS + WPCS into tests/tools/ via composer, then runs
# phpcs against phpcs.xml.dist. If composer is unavailable it skips gracefully and
# exits 0 — `php -l` (run separately) remains the mandatory syntax gate, while
# PHPCS-WPCS is the "run it if available" style/security check.
#
# Usage:
#   ./run-phpcs.sh                 # check the whole plugin per phpcs.xml.dist
#   ./run-phpcs.sh includes/foo.php  # check specific paths
#   ./run-phpcs.sh -- -n           # pass extra flags through to phpcs
#
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLS="${DIR}/tests/tools"
PHPCS="${TOOLS}/vendor/bin/phpcs"
INSTALLER_PLUGIN="dealerdirect/phpcodesniffer-composer-installer"

if ! command -v php >/dev/null 2>&1; then
	echo "error: php not found on PATH" >&2
	exit 127
fi

if [ ! -x "${PHPCS}" ]; then
	if ! command -v composer >/dev/null 2>&1; then
		echo "note: composer not found — skipping PHPCS-WPCS (php -l is the hard gate)." >&2
		echo "      install composer, then re-run, to enable WordPress Coding Standards." >&2
		exit 0
	fi

	echo "Installing PHPCS + WPCS into ${TOOLS} (first run) ..."
	mkdir -p "${TOOLS}"
	# The phpcodesniffer-composer-installer is a composer plugin and must be
	# explicitly allowed for a non-interactive install to register the standard.
	composer --working-dir="${TOOLS}" --no-interaction config "allow-plugins.${INSTALLER_PLUGIN}" true
	composer --working-dir="${TOOLS}" --no-interaction --quiet require \
		"squizlabs/php_codesniffer:^3.9" \
		"wp-coding-standards/wpcs:^3.1" \
		"${INSTALLER_PLUGIN}:^1.0"
fi

cd "${DIR}"
exec "${PHPCS}" --standard=phpcs.xml.dist "$@"
