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
  `access_admin`).
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
   - **Administrator** — full access (all permissions)
   - **Manager** — `access_admin`, `manage_urls`, `view_stats`
   - **Editor** — `access_admin`, `manage_urls`, `view_stats`
   - **User** — `access_admin`
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

When the plugin is deactivated, `uninstall.php` drops all five tables.
To preserve data on deactivation, add to your `config.php`:

```php
define('YOURLS_RBAC_KEEP_DATA', true);
```

## Testing

Unit tests use [PHPUnit](https://phpunit.de/) and cover the input validation
methods in `includes/rbac.php`.

### Setup

```bash
composer install
```

### Run tests

```bash
vendor/bin/phpunit --testdox
```

Tests follow the Arrange-Act-Assert (AAA) pattern. Each test method name
documents the condition under test and the expected outcome:

| Naming pattern | Example |
|---|---|
| `testMethod_whenCondition_thenResult` | `testValidateUsername_withSqlInjectionAttempt_throwsException` |

## References

<a id="r-1">[1]</a>
R.S. Sandhu, E.J. Coyne, H.L. Feinstein, C.E. Youman (1996),
Role-Based Access Control Models,
IEEE Computer 29(2),
(February 1996).
[[DOI](https://doi.org/10.1109/2.485845)] ·
[Preprint](https://csrc.nist.gov/CSRC/media/Projects/Role-Based-Access-Control/documents/sandhu96.pdf)
