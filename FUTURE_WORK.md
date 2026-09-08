# Future Work

## Testing

### UI Testing with Playwright — DONE
Covered by `tests/e2e/` (see README → End-to-end tests): RBAC admin pages,
full user workflow, field-collision regression, permission enforcement,
URL edit/delete guards, lockout guards, inactive-login refusal, role and
permission CRUD with permission sync, protected-slug guards. Remaining
ideas:
- API authentication paths (signature / userless modes) with JSON 403
  payload assertions against `yourls-api.php`
- Role/permission pages: negative validation (invalid slug characters,
  duplicate slug error notices) — client-side slug hint is covered

## UI Improvements — DONE (first pass)
Implemented in `admin/rbac-ui.css` + `admin/rbac-ui.js` (vanilla, no deps):
- Card/section layout (CSS Grid) replacing the old form tables
- Search/filter inputs on all three lists and the permission matrix
- Toggle switch for the user active state (hidden 0 + checkbox 1 pattern)
- Native `<dialog>` delete confirmation replacing `confirm()`
- Password strength meter on user creation
- Client-side slug pattern hint (roles / permissions)
- Permission matrix grid (roles × permissions) on the Roles page
- Dark mode via `prefers-color-scheme`, plugin pages only
- Responsive single-column forms on small screens

Remaining ideas:
- Pagination for large user/role lists (currently all rows render)
- Drag-and-drop role-to-user assignment
- Bulk actions (assign/delete multiple roles at once)
- Permission matrix: editable checkboxes with instant save (currently
  read-only overview; edits go through the role form)
- User search by email or role (current search is plain-text match)
- Export users to CSV
- Dark mode manual toggle (currently OS-setting only)

## Architecture Notes

### Nonce/Field Naming Collision
YOURLS's authentication system checks `$_REQUEST['username']` and
`$_REQUEST['password']` on every POST to detect login attempts. Any admin
form using these field names triggers the login nonce check, causing a 403.
All RBAC form fields use the `rbac_` prefix to avoid this collision.

### Password Hashing
The original code used `str_replace('$', '!', ...)` to escape phpass hashes,
which corrupted them. This was removed — PDO parameter binding does not
require escaping `$`.

### html_head context array
YOURLS's `html_head` action passes its context wrapped in an ARRAY, not a
string — a callback expecting `string $context` silently no-ops. Use
`yourls_get_html_context()` inside the callback instead (see
`yourls_rbac_html_head()` in plugin.php).
