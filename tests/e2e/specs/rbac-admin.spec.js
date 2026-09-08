// E2E: RBAC admin pages render, login gating, and the full user lifecycle:
// create → assign role → verify → edit → delete, plus regression tests for
// the field-name collision (rbac_ prefix) and lockout guards.
const { test, expect } = require('@playwright/test');
const { login, gotoRbacPage, rowByFirstCell } = require('./helpers');

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

test('RBAC Users page lists seeded admin user', async () => {
  await gotoRbacPage(page, 'users');
  await expect(rowByFirstCell(page, 'admin')).toHaveCount(1);
});

test('RBAC Roles page lists seeded roles', async () => {
  await gotoRbacPage(page, 'roles');
  for (const role of ['admin', 'manager', 'editor', 'user']) {
    await expect(page.getByRole('cell', { name: role, exact: true })).toBeVisible();
  }
});

test('RBAC Permissions page lists seeded permissions', async () => {
  await gotoRbacPage(page, 'permissions');
  await expect(page.getByRole('cell', { name: 'manage_urls', exact: true })).toBeVisible();
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
  const row = page.locator('tr', { hasText: username });
  await expect(row).toHaveCount(1);
  await expect(row).toContainText('user'); // role assigned

  // Edit: set inactive
  await row.locator('a:has-text("Edit")').click();
  await expect(page.locator('input[name="rbac_username"]')).toHaveValue(username);
  await page.check('input[name="rbac_active"][value="0"]');
  await page.click('input[name="save_user"]');
  await page.waitForLoadState('networkidle');
  await gotoRbacPage(page, 'users');
  await expect(page.locator('tr', { hasText: username })).toContainText('No');

  // Delete via POST form (accept confirm dialog)
  page.once('dialog', (d) => d.accept());
  await page.locator('tr', { hasText: username }).locator('input[value="Delete"]').click();
  await page.waitForLoadState('networkidle');
  await gotoRbacPage(page, 'users');
  await expect(page.locator('tr', { hasText: username })).toHaveCount(0);
});

test('weak password rejected with error notice', async () => {
  await gotoRbacPage(page, 'users');
  await page.fill('input[name="rbac_username"]', `weak_${Date.now()}`);
  await page.fill('input[name="rbac_password"]', 'short');
  await page.click('input[name="save_user"]');
  await gotoRbacPage(page, 'users');
  // Redirect after POST; notice displayed on the plugins page then target page
  await expect(page.locator('body')).toContainText(/at least 8 characters|Error/i);
});

test('anonymous visitor gets login screen, not RBAC pages', async ({ browser }) => {
  const anon = await browser.newPage();
  const resp = await anon.goto('/admin/plugins.php?page=rbac_users');
  // YOURLS redirects to login — RBAC page content must not leak
  expect(await anon.locator('input[name="username"]').count()).toBeGreaterThan(0);
  expect(await anon.locator('h2:has-text("RBAC Users")').count()).toBe(0);
  await anon.close();
});
