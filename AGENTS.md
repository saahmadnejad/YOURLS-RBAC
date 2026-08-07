# AGENTS.md

Development guidance for the YOURLS-RBAC project.

## Project overview

YOURLS-RBAC is a YOURLS plugin that adds Role-Based Access Control (RBAC) with
database-backed users, roles, and permissions.

## Key file structure

| Path | Purpose |
|---|---|
| `plugin.php` | Plugin entry point — hooks, permission checks, page registration |
| `includes/rbac.php` | Core `Rbac` class — all DB queries, validation, table management |
| `admin/users.php` | User management admin page |
| `admin/roles.php` | Role management admin page |
| `admin/permissions.php` | Permission management admin page |
| `uninstall.php` | Table cleanup on plugin deactivation |
| `tests/Unit/` | PHPUnit unit tests |

## Testing conventions

- Tests use PHPUnit, run with `vendor/bin/phpunit --testdox`.
- All tests follow the **Arrange / Act / Assert** pattern.
- Test method naming convention: `testMethodName_when_Condition_then_ExpectedResult`.
- Tests for the validation methods in `includes/rbac.php` live in `tests/Unit/`.
- The test bootstrap (`tests/bootstrap.php`) provides YOURLS stubs so tests run
  without a full YOURLS installation.

## Coding conventions

- PHP 8.1+ (uses `str_starts_with`, named returns `int|bool`, `readonly` props).
- All database values are passed through PDO parameter binding — never interpolate.
- Input validation methods (`validate_username`, `validate_email`, `validate_slug`,
  `validate_password`, `validate_name`, `validate_description`) are called by every
  CRUD method in the `Rbac` class.
- Admin page `action` parameters are whitelisted (e.g. `['edit', 'delete', '']`).
- Rbac CRUD calls in admin pages are wrapped in `try/catch(InvalidArgumentException)`
  to surface validation errors to the user.
- All public-facing strings use `yourls__()`, `yourls_e()`, `yourls_s()` for i18n.
- Nonce fields used for all form submissions (`yourls_nonce_field` / `yourls_verify_nonce`).
- HTML output escaped via `yourls_esc_attr()`, `yourls_esc_html()`.

## Commands

| Command | Action |
|---|---|
| `php -l <file>` | Lint a PHP file |
| `vendor/bin/phpunit` | Run the test suite |
| `composer install` | Install dev dependencies (PHPUnit) |

## Commit conventions

Use [Conventional Commits](https://www.conventionalcommits.org/) format:

```
<type>: a short description

[optional body]
```

Commit types used in this project:

| Type | When to use |
|---|---|
| `feat` | New feature or functionality |
| `fix` | Bug fix or security hardening |
| `test` | Adding or updating tests |
| `docs` | Documentation changes (README, AGENTS.md, etc.) |
| `chore` | Build tooling, dependencies, CI, gitignore |
| `refactor` | Code restructuring without behavior change |
| `style` | Formatting, whitespace, cosmetic changes |

Examples:
```
feat: implement RBAC plugin core (entry point, database class, uninstall)
fix: whitelist admin action params and handle validation errors
test: add 80 unit tests for input validation methods
docs: add project documentation and testing guide
chore: update .gitignore for development artifacts
```
