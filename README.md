# YOURLS-RBAC

Role-Based Access Control (RBAC) user management for [YOURLS](https://yourls.org/) URL shortener.

Users, roles, and permissions are stored in the database, giving you a full admin
interface for managing who can do what within YOURLS — rather than the default
single-user password in `user/config.php`.

Based on the RBAC model[[1]](#r-1).

## Features

- **Database-backed user accounts** — users are no longer limited to the single
  `$yourls_user_passwords` entry in `config.php`.
- **Roles** — group users into logical roles (Administrator, Manager, Editor, User).
- **Permissions** — granular capability checks (`manage_urls`, `view_stats`,
  `manage_users`, `manage_roles`, `manage_permissions`, `manage_plugins`,
  `manage_tools`, `access_admin`).
- **Enforced everywhere** — permission checks gate core admin pages, AJAX
  actions, the API, and plugin (de)activation, not just this plugin's own
  pages (see [Permission enforcement](#permission-enforcement)).
- **Admin UI** — built-in admin pages for managing users, roles, and permissions
  from the YOURLS admin area.
- **Backward compatible** — existing `config.php` users still work alongside
  database users.

## Installation

1. In `/user/plugins/`, create a directory `rbac` and drop these files in it.
2. Go to **Admin → Plugins** and activate **YOURLS-RBAC**.
3. The plugin automatically creates five tables (using your `YOURLS_DB_PREFIX`):
   - `yourls_rbac_users`
   - `yourls_rbac_roles`
   - `yourls_rbac_permissions`
   - `yourls_rbac_user_roles`
   - `yourls_rbac_role_permissions`
4. Default roles and permissions are seeded on activation:
   - **Administrator** — full access (all permissions, including `manage_tools`)
   - **Manager** — `access_admin`, `manage_urls`, `view_stats`, `manage_tools`
   - **Editor** — `access_admin`, `manage_urls`, `view_stats`
   - **User** — `access_admin`

   A first admin user is created from `YOURLS_USER` / `YOURLS_PASSWD` **only
   if** `YOURLS_PASSWD` is set to a strong password (8+ characters). If it is
   empty, no user is seeded — set the config credentials or create the first
   admin manually, then log in via **Admin → User Management**.
5. Create users from **Admin → Plugins → your plugin admin pages → Users**.

## Usage

### Admin interface

After activation, a **User Management** entry appears in the admin menu. It
contains three sub-pages:

| Page | Permission required |
|---|---|
| Users | `manage_users` |
| Roles | `manage_roles` |
| Permissions | `manage_permissions` |

### Programmatic permission checks

```php
// Check if the current user can manage URLs
if (yourls_rbac_can('manage_urls')) {
    // do something
}

// Check if the current user has a specific role
if (yourls_rbac_has_role('admin')) {
    // full access
}

// Require a permission — dies with 403 if unauthorized
yourls_rbac_require('manage_users');
```

### How authentication works

1. On `plugins_loaded`, the plugin pulls all active users from the
   `yourls_rbac_users` table and merges them into YOURLS's global
   `$yourls_user_passwords` array.
2. YOURLS's native auth flow — login form, cookies, API signatures — then
   works unchanged, using the database-stored password hashes.
3. After authentication, `YOURLS_USER` is set to the logged-in username.
4. Permission/role checks look up that username in the RBAC tables.

This means login, logout, cookie expiry, and "remember me" all behave exactly
as in stock YOURLS.

> RBAC enforcement only applies to authenticated requests, so your install
> must be private (`YOURLS_PRIVATE` defaults to `true`). On a public install
> there is nothing to enforce — anyone can hit the admin area anonymously.

## Permission enforcement

Permissions are enforced at the YOURLS seams — no core files are modified:

| Surface | Hook | Enforced permission |
|---|---|---|
| Core admin pages (`index.php`, `tools.php`, `plugins.php`, `upgrade.php`) | `auth_successful` + page map | `access_admin` / `manage_tools` / `manage_plugins` |
| Plugin (de)activation (`plugins.php?action=activate\|deactivate`) | `auth_successful` | `manage_plugins` |
| AJAX URL mutations (`add`, `edit_display`, `edit_save`, `delete`) | `auth_successful` + AJAX map | `manage_urls` |
| API writes (`shorturl`) | `auth_successful` + API map | `manage_urls` |
| API reads (`stats`, `db-stats`, `url-stats`, `expand`) | `auth_successful` + API map | `view_stats` |
| All URL writes, any entry point | `shunt_add_new_link`, `shunt_edit_link`, `shunt_edit_link_title`, `shunt_delete_link_by_keyword` | `manage_urls` |
| RBAC's own admin pages | page callbacks | `manage_users` / `manage_roles` / `manage_permissions` |

Denials are fail-closed: API gets a JSON 403, AJAX a JSON 403, HTML pages a
`yourls_die()` 403. A user not found in the RBAC tables has no permissions —
add them (with a role) from the Users page before they can do anything.

Additional lockout guards (all enforced server-side):

- The last active administrator cannot be deleted or demoted.
- You cannot deactivate or delete your own account.
- The `admin` role slug cannot be renamed or deleted; its permission set is
  always every permission.
- Core permission slugs (`access_admin`, `manage_users`, `manage_roles`,
  `manage_permissions`) cannot be deleted or renamed.
- You cannot remove `manage_roles` from a role assigned to yourself.
- Destructive actions (delete user/role/permission) are POST-only with
  nonces — no state changes via GET links.

## Database schema

```
yourls_rbac_users
  id, username, password, email, active, created_at, updated_at

yourls_rbac_roles
  id, name, slug, description, created_at

yourls_rbac_permissions
  id, name, slug, description, created_at

yourls_rbac_user_roles        (many-to-many: users ↔ roles)
  user_id, role_id

yourls_rbac_role_permissions  (many-to-many: roles ↔ permissions)
  role_id, permission_id
```

## Uninstall

When the plugin is deactivated, RBAC data is **kept by default** —
re-activating the plugin restores users, roles, and permissions seamlessly.

To drop all five tables on deactivation instead, add to your `config.php`:

```php
define('YOURLS_RBAC_DROP_DATA', true);
```

> **Warning**: dropping the tables deletes every RBAC user. Make sure the
> `config.php` account (`YOURLS_USER` / `YOURLS_PASSWD`) still works before
> enabling this, or you will be locked out.

## Contributing

### Commit messages

Commits follow the [Conventional Commits](https://www.conventionalcommits.org/)
standard:

```
<type>[optional scope]: <short description in lowercase, imperative mood>

[optional body: motivation and what changed, wrapped at 72 chars]

[optional footer: BREAKING CHANGE: <...>, Refs: #<issue>]
```

Types used in this project:

| Type | When to use |
|---|---|
| `feat` | New feature or functionality |
| `fix` | Bug fix or security hardening |
| `test` | Adding or updating tests |
| `docs` | Documentation changes (README, AGENTS.md, etc.) |
| `chore` | Build tooling, dependencies, CI, gitignore |
| `refactor` | Code restructuring without behavior change |
| `style` | Formatting, whitespace, cosmetic changes |

A commit that introduces a breaking change (e.g. a permission slug is
renamed) must note it in the footer: `BREAKING CHANGE: manage_tools renamed to ...`.

### Testing conventions

- Tests use PHPUnit, run with `vendor/bin/phpunit --testdox`.
- Every test follows the **Arrange / Act / Assert** (AAA) principle — one
  behavior per test, no test logic in the Act phase, exactly one logical
  assertion group.
- Use **Given / When / Then** comments to make each phase explicit:

```php
public function testValidatePassword_WithLength7_ThrowsInvalidArgumentException(): void
{
    // Given: a password one character below the minimum length
    // When: validated
    // Then: an InvalidArgumentException with a helpful message is thrown
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Password must be at least 8 characters');
    Rbac::validate_password('abcdefg');
}
```

- Test method naming convention: `testMethodName_WithCondition_ExpectedResult`
  (e.g. `testValidateUsername_WithSqlInjectionAttempt_ThrowsInvalidArgumentException`).
- Tests for the pure logic in `includes/rbac.php` (validation, permission
  maps, denial payloads) live in `tests/Unit/`. The test bootstrap
  (`tests/bootstrap.php`) provides YOURLS stubs so tests run without a full
  YOURLS installation.
- Security-relevant behavior (injection attempts, permission maps, lockout
  guards) always gets a test — a fix without a regression test is incomplete.

## Development

### Docker (quick start)

```bash
docker compose up --build
# Visit http://localhost:8080
# Login: admin / password123 (dev convenience only — RBAC accepts it
# because it is 11 chars; production installs must use strong passwords)
```

The Docker environment runs YOURLS (latest from GitHub), a MariaDB container,
and mounts the RBAC plugin source as read-only volumes so code changes are
reflected immediately without rebuilding.

### Local testing (without Docker)

You can also run the unit tests without a full YOURLS installation:

```bash
composer install
vendor/bin/phpunit --testdox
```

## References

<a id="r-1">[1]</a>
R.S. Sandhu, E.J. Coyne, H.L. Feinstein, C.E. Youman (1996),
Role-Based Access Control Models,
IEEE Computer 29(2),
(February 1996).
[[DOI](https://doi.org/10.1109/2.485845)] ·
[Preprint](https://csrc.nist.gov/CSRC/media/Projects/Role-Based-Access-Control/documents/sandhu96.pdf)
