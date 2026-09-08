// E2E: permission enforcement — a user without manage_* permissions cannot
// reach RBAC pages or mutate URLs; lockout guards hold.
const { test, expect } = require('@playwright/test');
const { login, gotoRbacPage, logout } = require('./helpers');

test.describe.serial('permission enforcement', () => {
  let page;
  let username;

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    username = `viewer_${Date.now()}`;
    await login(page);

    // Create a user with ONLY the seeded "user" role (access_admin, no
    // manage_* permissions) — can log in but must be denied everywhere else.
    await gotoRbacPage(page, 'users');
    await page.fill('input[name="rbac_username"]', username);
    await page.fill('input[name="rbac_password"]', 'viewerPass1!');
    const userRoleBox = page.locator('label', { hasText: '(user)' }).locator('input[name="rbac_role_ids[]"]');
    await userRoleBox.check();
    await page.click('input[name="save_user"]');
    await gotoRbacPage(page, 'users');
    await expect(page.locator('tr', { hasText: username })).toHaveCount(1);
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('user without role has no permissions — 403 on RBAC pages', async () => {
    await logout(page);
    await login(page, username, 'viewerPass1!');
    const resp = await page.goto('/admin/plugins.php?page=rbac_users');
    expect(resp.status()).toBe(403);
    await expect(page.locator('body')).toContainText(/Forbidden|do not have permission/i);
  });

  test('user without manage_urls cannot add a URL', async () => {
    // viewer has access_admin only — the URL write guard must deny the add.
    await page.goto('/admin/index.php');
    await page.fill('#add-url', 'https://example.com/rbac-e2e');
    await page.click('#add-button');
    await expect(page.locator('body')).toContainText(/forbidden|permission|denied/i, { timeout: 10_000 });
  });

  test('viewer (access_admin only) sees no RBAC menu entry', async () => {
    // plugin.php:199 hides OUR menu entry for users without any manage_*
    // permission. Core's plugin-page links (admin_menu_rbac_users_link...)
    // are rendered by YOURLS core for every plugin page — they stay, but
    // following them is 403-gated by the load-<page> hook.
    await page.goto('/admin/index.php');
    await expect(page.locator('#admin_menu_rbac_link')).toHaveCount(0);
  });

  test('admin role deletion is refused (protected slug)', async () => {
    await logout(page);
    await login(page); // seeded admin
    await gotoRbacPage(page, 'roles');
    // Protected role: no Delete button rendered at all (admin/roles.php:232)
    const adminRow = page.locator('tr', { has: page.locator('td:text-is("Administrator")') });
    await expect(adminRow.locator('input[value="Delete"]')).toHaveCount(0);
    await expect(adminRow).toContainText('manage_tools'); // keeps all perms
  });
});
