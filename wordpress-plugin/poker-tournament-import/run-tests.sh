#!/usr/bin/env bash
#
# Offline test runner for the Poker Tournament Import plugin.
#
# Self-bootstrapping: downloads a pinned PHPUnit PHAR on first run, then runs the
# no-database unit suite. No composer, MySQL, svn, or WordPress install required,
# so it works on a developer machine and on a self-hosted GitHub Actions runner.
#
# Usage:
#   ./run-tests.sh              # run the unit suite
#   ./run-tests.sh --testdox    # readable output
#   ./run-tests.sh --filter StatsBridge
#
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHAR="${DIR}/tests/bin/phpunit.phar"
PHPUNIT_VERSION="12"
PHAR_URL="https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar"

if ! command -v php >/dev/null 2>&1; then
	echo "error: php not found on PATH" >&2
	exit 127
fi

if [ ! -f "${PHAR}" ]; then
	echo "PHPUnit PHAR not found; downloading ${PHAR_URL} ..."
	mkdir -p "${DIR}/tests/bin"
	curl -sSL -m 120 -o "${PHAR}" "${PHAR_URL}"
	chmod +x "${PHAR}"
fi

cd "${DIR}"
exec php "${PHAR}" -c phpunit.xml.dist --no-coverage "$@"
