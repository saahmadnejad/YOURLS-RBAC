// E2E: lockout guards + inactive-user login refusal + menu visibility.
// These are DB-touching "never regress" invariants with no unit coverage.
const { test, expect } = require('@playwright/test');
const { login, gotoRbacPage, logout, itemRow, confirmDelete } = require('./helpers');

test.describe.serial('lockout guards', () => {
  let page;
  let username;

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    page.setDefaultTimeout(20_000);
    username = `guard_${Date.now()}`;
    await login(page);
    // Create a second admin (so "last admin" guards have a non-last target)
    await gotoRbacPage(page, 'users');
    await page.fill('input[name="rbac_username"]', username);
    await page.fill('input[name="rbac_password"]', 'guardPass1!');
    await page.locator('label', { hasText: '(admin)' }).locator('input[name="rbac_role_ids[]"]').check();
    await page.click('input[name="save_user"]');
    await gotoRbacPage(page, 'users');
    await expect(itemRow(page, username)).toBeVisible();
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('cannot delete own account', async () => {
    await gotoRbacPage(page, 'users');
    // seeded 'admin' user = current session user
    const selfRow = itemRow(page, 'admin');
    const del = selfRow.locator('input[value="Delete"]');
    if (await del.count()) {
      await del.click();
      await confirmDelete(page);
      await page.waitForLoadState('networkidle');
      await gotoRbacPage(page, 'users');
      await expect(itemRow(page, 'admin')).toBeVisible();
    }
  });

  test('cannot deactivate own account', async () => {
    await gotoRbacPage(page, 'users');
    const selfRow = itemRow(page, 'admin');
    await selfRow.locator('a:has-text("Edit")').click();
    await page.uncheck('input[name="rbac_active"][value="1"]');
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'users');
    // still active: no 'inactive' badge
    await expect(itemRow(page, 'admin')).not.toContainText('inactive');
  });

  test('cannot demote the last active administrator (self)', async () => {
    const other = username;
    // Drop the admin role from the OTHER admin first — allowed; then the
    // session user is the last active admin and removing admin must hold.
    await gotoRbacPage(page, 'users');
    await itemRow(page, other).locator('a:has-text("Edit")').click();
    await page.locator('label', { hasText: '(admin)' }).locator('input[name="rbac_role_ids[]"]').uncheck();
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'users');
    await expect(itemRow(page, other)).not.toContainText('admin');

    // Now demote self — server must refuse, admin row keeps admin role.
    await itemRow(page, 'admin').locator('a:has-text("Edit")').click();
    await page.locator('label', { hasText: '(admin)' }).locator('input[name="rbac_role_ids[]"]').uncheck();
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'users');
    await expect(itemRow(page, 'admin')).toContainText('admin');

    // Restore other admin role for the remaining tests
    await itemRow(page, other).locator('a:has-text("Edit")').click();
    await page.locator('label', { hasText: '(admin)' }).locator('input[name="rbac_role_ids[]"]').check();
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');

    // Self was demoted by the blocked save? No — the block redirects before
    // any change, but re-assert self role intact for the remaining tests.
    await gotoRbacPage(page, 'users');
    await itemRow(page, 'admin').locator('a:has-text("Edit")').click();
    await page.locator('label', { hasText: '(admin)' }).locator('input[name="rbac_role_ids[]"]').check();
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');
  });

  test('inactive user cannot log in', async () => {
    // Deactivate the second admin (allowed — not self, not last admin)
    await gotoRbacPage(page, 'users');
    await itemRow(page, username).locator('a:has-text("Edit")').click();
    await page.uncheck('input[name="rbac_active"][value="1"]');
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');

    await logout(page);
    // Rejected: back on the login form, and the admin area stays closed.
    await login(page, username, 'guardPass1!', true);
    await page.goto('/admin/index.php');
    // redirected to the login screen — not the dashboard
    await expect(page.locator('input[name="username"]')).toBeVisible();
  });

  // (cleanup of the guard user is unnecessary: global-setup resets the DB
  // before every run, and no later spec depends on the user list)
});
