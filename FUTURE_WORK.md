# Future Work

## Testing

### UI Testing with Playwright — DONE
Covered by `tests/e2e/` (see README → End-to-end tests): RBAC admin pages,
full user workflow, field-collision regression, permission enforcement,
protected-slug guards. Remaining ideas:
- Role and permission CRUD workflows (currently user-focused)
- API authentication paths (signature / userless modes)

### UI Testing with Electron
- Package a desktop app that loads YOURLS admin in an Electron wrapper
- Use this to manually test UI flows that require visual inspection (drag-drop, keyboard navigation)
- Capture screenshots for documentation

## UI Improvements

### Current State
- Admin pages use YOURLS's default table-based layout with minimal styling
- Form fields use generic HTML inputs

### Planned Improvements
- Replace tables with a modern card/list layout using CSS Grid or Flexbox
- Add search/filter for users, roles, and permissions
- Add pagination for large user/role lists
- Add a dark mode toggle
- Use YOURLS's existing CSS variables for consistency
- Add client-side form validation (before submit to server)
- Replace radio buttons with toggle switches for active/inactive states
- Use a modal dialog for delete confirmation instead of `confirm()`

### Role Management UI
- Drag-and-drop role-to-user assignment
- Permission matrix grid (roles as rows, permissions as columns)
- Bulk actions (assign/delete multiple roles at once)

### User Management UI
- Password strength meter
- Inline role assignment with autocomplete
- User search by username, email, or role
- Export users to CSV

## Architecture Notes

### Nonce/Field Naming Collision
YOURLS's authentication system checks `$_REQUEST['username']` and `$_REQUEST['password']` on every POST to detect login attempts. Any admin form using these field names triggers the login nonce check, causing a 403. All RBAC form fields use the `rbac_` prefix to avoid this collision. This should be documented in a developer guide.

### Password Hashing
The original code used `str_replace('$', '!', ...)` to escape phpass hashes, which corrupted them. This was removed — PDO parameter binding does not require escaping `$`.
