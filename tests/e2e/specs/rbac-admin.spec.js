// E2E: RBAC admin pages render, login gating, and the full user lifecycle:
// create → assign role → verify → edit → delete, plus regression tests for
// the field-name collision (rbac_ prefix) and lockout guards.
const { test, expect } = require('@playwright/test');
const { login, gotoRbacPage, itemRow, confirmDelete } = require('./helpers');

let page;

test.beforeAll(async ({ browser }) => {
  page = await browser.newPage();
  await login(page);
});

test.afterAll(async () => {
  await page.close();
});

test('admin menu shows User Management entry', async () => {
  await page.goto('/admin/index.php');
  await expect(page.locator('#admin_menu_rbac_link')).toBeVisible();
});

test('RBAC UI layer is enqueued on plugin pages', async () => {
  await gotoRbacPage(page, 'users');
  await expect(page.locator('link[href*="rbac-ui.css"]')).toHaveCount(1);
  await expect(page.locator('script[src*="rbac-ui.js"]')).toHaveCount(1);
});

test('RBAC Users page lists seeded admin user', async () => {
  await gotoRbacPage(page, 'users');
  await expect(itemRow(page, 'admin')).toBeVisible();
});

test('RBAC Roles page lists seeded roles in the matrix', async () => {
  await gotoRbacPage(page, 'roles');
  for (const role of ['admin', 'manager', 'editor', 'user']) {
    await expect(page.locator('table.rbac-matrix').getByText(`(${role})`)).toBeVisible();
  }
});

test('RBAC Permissions page lists seeded permissions', async () => {
  await gotoRbacPage(page, 'permissions');
  await expect(itemRow(page, 'Manage URLs')).toBeVisible();
  await expect(itemRow(page, 'Manage URLs')).toContainText('manage_urls');
});

test('search box filters the user list', async () => {
  await gotoRbacPage(page, 'users');
  await expect(itemRow(page, 'admin')).toBeVisible();
  await page.fill('input.rbac-search', 'zzz-no-such-user');
  await expect(itemRow(page, 'admin')).toBeHidden();
  await page.fill('input.rbac-search', '');
  await expect(itemRow(page, 'admin')).toBeVisible();
});

test('create user with role → verify row → edit → delete', async () => {
  const username = `e2e_user_${Date.now()}`;

  // Create (rbac_-prefixed fields must NOT trigger the login nonce check)
  await gotoRbacPage(page, 'users');
  await page.fill('input[name="rbac_username"]', username);
  await page.fill('input[name="rbac_password"]', 'e2ePassw0rd!');
  await page.fill('input[name="rbac_email"]', `${username}@example.com`);
  await page.locator('label', { hasText: '(user)' }).locator('input[name="rbac_role_ids[]"]').check();
  await page.click('input[name="save_user"]');
  await gotoRbacPage(page, 'users');
  const row = itemRow(page, username);
  await expect(row).toBeVisible();
  await expect(row).toContainText('user'); // role assigned

  // Edit: toggle inactive (hidden 0 + checkbox 1 pattern)
  await row.locator('a:has-text("Edit")').click();
  await expect(page.locator('input[name="rbac_username"]')).toHaveValue(username);
  await page.uncheck('input[name="rbac_active"][value="1"]');
  await page.click('input[name="save_user"]');
  await page.waitForLoadState('networkidle');
  await gotoRbacPage(page, 'users');
  await expect(itemRow(page, username)).toContainText('inactive');

  // Delete via the confirm dialog
  await itemRow(page, username).locator('input[value="Delete"]').click();
  await confirmDelete(page);
  await page.waitForLoadState('networkidle');
  await gotoRbacPage(page, 'users');
  await expect(itemRow(page, username)).toHaveCount(0);
});

test('password strength meter reacts to input', async () => {
  await gotoRbacPage(page, 'users');
  await page.fill('input[name="rbac_password"]', 'e2ePassw0rd!');
  await expect(page.locator('.rbac-meter')).toHaveClass(/rbac-meter-4/);
  await expect(page.locator('.rbac-meter-label')).toHaveText('strong');
  await page.fill('input[name="rbac_password"]', 'weak');
  await expect(page.locator('.rbac-meter-label')).toHaveText('too weak');
});

test('theme toggle switches dark mode', async () => {
  await gotoRbacPage(page, 'users');
  const toggle = page.locator('button.rbac-theme-toggle');
  await expect(toggle).toHaveCount(1);
  // default: no explicit override attribute (follows OS)
  await expect(page.locator('html')).not.toHaveAttribute('data-rbac-theme', 'dark');
  await toggle.click();
  await expect(page.locator('html')).toHaveAttribute('data-rbac-theme', 'dark');
  const bg = await page.locator('.rbac-card').first().evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(bg).not.toBe('rgb(255, 255, 255)'); // dark card background applied
  await toggle.click();
  await expect(page.locator('html')).not.toHaveAttribute('data-rbac-theme', 'dark');
});

test('matrix scrolls inside its wrapper instead of stretching the wrap', async () => {
  await gotoRbacPage(page, 'roles');
  const wrap = page.locator('.rbac-matrix-wrap');
  const cardWidth = await wrap.evaluate((el) => el.closest('.rbac-card').clientWidth);
  const wrapWidth = await wrap.evaluate((el) => el.clientWidth);
  expect(wrapWidth).toBeLessThanOrEqual(cardWidth);
  const { tableWider, wrapperScrolls } = await wrap.evaluate((el) => ({
    tableWider: el.querySelector('table').offsetWidth > el.clientWidth,
    wrapperScrolls: el.scrollWidth > el.clientWidth,
  }));
  // if the table doesn't fit, the wrapper must be the scroll container
  expect(wrapperScrolls).toBe(tableWider);
});

test('delete dialog: Esc cancels without leaking into the next delete', async () => {
  // Regression: the dialog's yes/no handlers were only unbound on click —
  // after an Esc cancel, confirming a DIFFERENT row's delete would fire the
  // stale handler too, deleting both rows.
  await gotoRbacPage(page, 'users');
  const names = [`esc_a_${Date.now()}`, `esc_b_${Date.now()}`];
  for (const n of names) {
    await page.fill('input[name="rbac_username"]', n);
    await page.fill('input[name="rbac_password"]', 'escPass1!');
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'users');
  }

  // open A's delete dialog, dismiss with Esc
  await itemRow(page, names[0]).locator('input[value="Delete"]').click();
  const dlg = page.locator('#rbac-confirm-dialog');
  await expect(dlg).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(dlg).not.toBeVisible();
  await gotoRbacPage(page, 'users');
  await expect(itemRow(page, names[0])).toBeVisible(); // A still there

  // now confirm B's delete — A must NOT be touched
  await itemRow(page, names[1]).locator('input[value="Delete"]').click();
  await expect(dlg).toBeVisible();
  await dlg.locator('.rbac-confirm-yes').click();
  await page.waitForLoadState('networkidle');
  await gotoRbacPage(page, 'users');
  await expect(itemRow(page, names[1])).toHaveCount(0);
  await expect(itemRow(page, names[0])).toBeVisible();
});

test('weak password rejected with inline error', async () => {
  await gotoRbacPage(page, 'users');
  await page.fill('input[name="rbac_username"]', `weak_${Date.now()}`);
  await page.fill('input[name="rbac_password"]', 'short');
  await page.click('input[name="save_user"]');
  // validation errors re-render the form in the same request (no redirect)
  await expect(page.locator('.rbac-error')).toContainText(/at least 8 characters/i);
});

test('anonymous visitor gets login screen, not RBAC pages', async ({ browser }) => {
  const anon = await browser.newPage();
  const resp = await anon.goto('/admin/plugins.php?page=rbac_users');
  // YOURLS redirects to login — RBAC page content must not leak
  expect(await anon.locator('input[name="username"]').count()).toBeGreaterThan(0);
  expect(await anon.locator('h2:has-text("RBAC Users")').count()).toBe(0);
  await anon.close();
});
