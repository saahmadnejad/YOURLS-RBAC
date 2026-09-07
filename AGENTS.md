# AGENTS.md

Development guidance for the YOURLS-RBAC project.

## Project

YOURLS plugin adding Role-Based Access Control: DB-backed users, roles,
permissions. No core YOURLS files modified — everything hooks YOURLS seams.

## Commands

| Command | Action |
|---|---|
| `vendor/bin/phpunit --testdox` | Run all tests (no YOURLS install or DB needed) |
| `vendor/bin/phpunit --filter ValidateSlug` | Run one test class / method |
| `composer install` | Install dev deps (PHPUnit) |
| `php -l <file>` | Lint (only lint tool — no CI, run before commit) |
| `docker compose up --build` | Dev env: YOURLS + MariaDB at http://localhost:8080, login `admin` / `password123` |

No typecheck/lint toolchain beyond `php -l`. Verify changes with phpunit.

## Architecture (not obvious from filenames)

- `plugin.php` — entry point. Enforcement wiring:
  - `auth_successful` hook → permission map for admin pages / AJAX / API.
  - `shunt_add_new_link`, `shunt_edit_link`, `shunt_edit_link_title`,
    `shunt_delete_link_by_keyword` filters → `manage_urls` on every URL write.
  - `plugins_loaded` → merges RBAC DB users into `$yourls_user_passwords`
    (YOURLS native auth/cookies then work unchanged).
- `includes/rbac.php` — `Rbac` class, all DB access, validation, permission
  maps, table creation, seeding. ~1100 lines, single class.
- `admin/*.php` — rendered via `yourls_include_file_sandbox()` from page
  callbacks in `plugin.php`.
- New admin page: `yourls_register_plugin_page()` + a `load-<page>` hook
  calling `yourls_rbac_require()` (denial must fire before `html_head` or the
  403 status can't send) + permission re-check inside the callback.

## Non-obvious gotchas

- **Form field names must be `rbac_`-prefixed.** YOURLS inspects
  `$_REQUEST['username']` / `$_REQUEST['password']` on every POST; using
  those names triggers the login nonce check → 403.
- **Never escape `$` in password hashes.** phpass hashes contain `$`;
  a past `str_replace('$', '!')` corrupted them. PDO binding needs no escaping.
- **DB context naming:** `Rbac::db()` calls `yourls_get_db($context)` where
  context must match `/^(read|write)-[a-z0-9_]+$/` or it silently falls back.
- **Table names:** always via `Rbac::table_users()` etc. (respects
  `YOURLS_DB_PREFIX`); identifiers via `Rbac::quote_identifier()`.
- **Autoload is classmap** on `includes/` — after adding a class there, run
  `composer dump-autoload` or tests won't find it.
- **Deactivation keeps data** (re-activate restores everything). Tables drop
  only if `YOURLS_RBAC_DROP_DATA` is defined in config.
- **Docker PHP is 8.4, local dev 8.1+** — code must run on both.
- Docker mounts plugin source read-only; code changes apply without rebuild,
  but `composer` deps inside the image need `--build`.

## Security invariants (never regress)

- All DB values through PDO parameter binding; never interpolate.
- Destructive actions POST-only with nonce (`yourls_nonce_field` /
  `yourls_verify_nonce`); admin `action` params whitelisted.
- Rbac CRUD calls in admin pages wrapped in `try/catch(InvalidArgumentException)`.
- Validation methods (`validate_username`, `validate_email`, `validate_slug`,
  `validate_password`, `validate_name`, `validate_description`) called by every
  CRUD method.
- Lockout guards in `includes/rbac.php`: last active admin can't be
  deleted/demoted; no self-deletion; `PROTECTED_ROLE_SLUGS` /
  `PROTECTED_PERMISSION_SLUGS` can't be renamed/deleted.
- Security fixes ship with a regression test.
- Output escaping via `yourls_esc_attr()` / `yourls_esc_html()`; i18n via
  `yourls__()` / `yourls_e()` / `yourls_s()`.

## Testing conventions

- PHPUnit, tests in `tests/Unit/` for pure logic in `includes/rbac.php`
  (validation, permission maps, denial payloads, slug policy). Bootstrap
  (`tests/bootstrap.php`) stubs YOURLS — no installation or DB required.
- Arrange/Act/Assert with `// Given / When / Then` comments.
- Naming: `testMethodName_WithCondition_ExpectedResult`.

## Commit conventions

Conventional Commits (`feat:`, `fix:`, `test:`, `docs:`, `chore:`,
`refactor:`, `style:`). Detail and examples in README → Contributing.
