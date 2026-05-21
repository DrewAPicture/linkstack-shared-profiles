# Contributing

## Prerequisites

- PHP 8.2+
- Composer

No database server is required. Tests run against an in-memory SQLite database.

---

## Setup

```bash
git clone <repo-url>
cd web
composer install
```

That is the entire setup. The package has no external service dependencies in tests.

---

## Running the test suite

```bash
composer test
```

Tests use [Orchestra Testbench](https://github.com/orchestral/testbench) to bootstrap a minimal Laravel application. Each test class creates its own in-memory SQLite schema, so tests are fully isolated and order-independent.

---

## Static analysis

```bash
composer analyse
```

PHPStan runs at max level with the Larastan extension. If it crashes with a memory error, raise the limit:

```bash
./vendor/bin/phpstan analyse --memory-limit=512M
```

PHPStan only analyses `src/` — test support classes are excluded.

---

## Code style

```bash
composer format          # fix in place
./vendor/bin/pint --test # dry run (exits non-zero if anything needs changing)
```

The project uses the Laravel Pint preset with camelCase test method names. Run `composer format` before committing.

---

## Running all checks at once

```bash
composer ci
```

This runs `test`, `analyse`, and `format` in sequence, the same as CI.

---

## Continuous integration

CI runs on PHP 8.2, 8.3, 8.4, and 8.5 via GitHub Actions on every push to `main`. The workflow runs `composer test`, `composer analyse`, and `./vendor/bin/pint --test` in separate jobs. All three must pass before a PR is merged.

---

## Project structure

```
src/                    Package source (the only path PHPStan analyses)
  Http/Controllers/
  Models/
  ServiceProvider.php
database/migrations/    Auto-loaded by the service provider
routes/
  api.php
  web.php
resources/views/
config/
tests/
  Feature/
  Unit/
  Support/              Test-only models and middleware stubs
```

See [`.agent/context/architecture.md`](.agent/context/architecture.md) for a full breakdown of the data model, route table, and service provider hooks.

---

## Making changes

- Keep PHPStan at max level. Use `/** @var Type $var */` docblocks to narrow `mixed` rather than casts — see the patterns documented in [`.agent/context/testing.md`](.agent/context/testing.md).
- Add or update feature tests for any behaviour change. Tests live in `tests/Feature/` and use the inline schema pattern rather than loading package migrations.
- Follow the commit message style in [`.agent/context/best-practices/commit-messages.md`](.agent/context/best-practices/commit-messages.md): imperative tense, no emoji, concise subject line.
