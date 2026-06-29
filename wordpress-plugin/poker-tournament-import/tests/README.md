# Test Harness — Poker Tournament Import

A **no-database** PHPUnit harness. It exercises plugin classes against a minimal
WordPress stub layer (an in-memory `$wpdb` fake + function stubs), so it needs
**no MySQL, svn, composer, or WordPress install** and runs fully offline — on a
developer machine and on a self-hosted runner alike.

## Run it

```bash
cd wordpress-plugin/poker-tournament-import
./run-tests.sh                 # downloads the PHPUnit PHAR on first run, then runs
./run-tests.sh --testdox       # readable output
./run-tests.sh --filter StatsBridge
```

With composer instead (optional):

```bash
composer install
composer test
```

## Layout

| Path | Purpose |
|---|---|
| `phpunit.xml.dist` | PHPUnit 12 config (bootstrap, `tests/unit` suite) |
| `tests/bootstrap.php` | Defines `ABSPATH`/plugin constants, loads stubs, installs `$wpdb`, requires classes under test |
| `tests/stubs/wp-stubs.php` | WP function stubs + `TDWP_Fake_WPDB` (models the two player tables) |
| `tests/unit/` | Test cases |
| `run-tests.sh` | Self-bootstrapping offline runner |

## What's covered

- **`TDWP_Stats_Bridge`** (the live→stats-mart Option-A bridge): per-player
  projection, re-entry aggregation, idempotency, UUID join-key mapping + reuse,
  `live-` prefix on generated UUIDs, points fallback, async refresh scheduling,
  and all the safety guards (missing tables, empty rows, invalid id).

### Adding a test

Drop a `*Test.php` file in `tests/unit/`, `require` the class under test from
`tests/bootstrap.php`, and use `tdwp_test_reset()` in `setUp()`. Add only the WP
stubs a test actually needs — keep the stub layer honest.

## Self-hosted runner

The CI workflow (`.github/workflows/php-tests.yml`) targets `[self-hosted,
macOS]`. A **personal-account** self-hosted runner is bound to a single repo. The
existing runner (`~/dev/actions-runner`) is registered to `hkhard/HandRecorder`,
so to run this repo's jobs, register a runner for `hkhard/tdwpimport`:

```bash
# 1. Get a registration token
gh api -X POST repos/hkhard/tdwpimport/actions/runners/registration-token --jq .token

# 2. Configure a SECOND runner instance (separate folder) with that token
mkdir -p ~/dev/actions-runner-tdwp && cd ~/dev/actions-runner-tdwp
# download the runner package, then:
./config.sh --url https://github.com/hkhard/tdwpimport --token <TOKEN> --labels macOS
./run.sh        # or install as a service: ./svc.sh install && ./svc.sh start
```

Until then, run the suite locally with `./run-tests.sh`.
