// E2E: Role CRUD with permission sync + Permission CRUD. Addresses review
// items 1 & 2: the save_role / save_permission POST paths were untested.
const { test, expect } = require('@playwright/test');
const { login, gotoRbacPage } = require('./helpers');

test.describe.serial('role + permission CRUD', () => {
  let page;

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    await login(page);
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('role create → edit (permission sync) → delete', async () => {
    const slug = `e2e_role_${Date.now()}`;

    // Create with a couple of permissions checked
    await gotoRbacPage(page, 'roles');
    await page.fill('input[name="role_name"]', 'E2E Role');
    await page.fill('input[name="role_slug"]', slug);
    await page.fill('input[name="role_desc"]', 'created by e2e');
    await page.check('input[name="permission_slugs[]"][value="access_admin"]');
    await page.check('input[name="permission_slugs[]"][value="view_stats"]');
    await page.click('input[name="save_role"]');
    await gotoRbacPage(page, 'roles');
    const row = page.locator('tr', { hasText: slug });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('view_stats'); // permission synced

    // Edit: add manage_urls, drop view_stats — sync must follow the checkboxes
    await row.locator('a:has-text("Edit")').click();
    await expect(page.locator('input[name="role_slug"]')).toHaveValue(slug);
    await page.check('input[name="permission_slugs[]"][value="manage_urls"]');
    await page.uncheck('input[name="permission_slugs[]"][value="view_stats"]');
    await page.click('input[name="save_role"]');
    await gotoRbacPage(page, 'roles');
    const edited = page.locator('tr', { hasText: slug });
    await expect(edited).toContainText('manage_urls');
    await expect(edited).not.toContainText('view_stats');

    // Delete
    page.once('dialog', (d) => d.accept());
    await edited.locator('input[value="Delete"]').click();
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'roles');
    await expect(page.locator('tr', { hasText: slug })).toHaveCount(0);
  });

  test('admin role has no delete button (protected slug)', async () => {
    await gotoRbacPage(page, 'roles');
    const adminRow = page.locator('tr', { has: page.locator('td:text-is("Administrator")') });
    await expect(adminRow.locator('input[value="Delete"]')).toHaveCount(0);
    // and the admin-role row keeps every permission listed
    await expect(adminRow).toContainText('manage_tools');
  });

  test('permission create → edit → delete', async () => {
    const slug = `e2e_perm_${Date.now()}`;

    // Create
    await gotoRbacPage(page, 'permissions');
    await page.fill('input[name="perm_name"]', 'E2E Perm');
    await page.fill('input[name="perm_slug"]', slug);
    await page.fill('input[name="perm_desc"]', 'created by e2e');
    await page.click('input[name="save_permission"]');
    await gotoRbacPage(page, 'permissions');
    const row = page.locator('tr', { hasText: slug });
    await expect(row).toHaveCount(1);

    // Edit
    await row.locator('a:has-text("Edit")').click();
    await expect(page.locator('input[name="perm_slug"]')).toHaveValue(slug);
    await page.fill('input[name="perm_name"]', 'E2E Perm Renamed');
    await page.click('input[name="save_permission"]');
    await gotoRbacPage(page, 'permissions');
    await expect(page.locator('tr', { hasText: slug })).toContainText('E2E Perm Renamed');

    // Delete
    page.once('dialog', (d) => d.accept());
    await page.locator('tr', { hasText: slug }).locator('input[value="Delete"]').click();
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'permissions');
    await expect(page.locator('tr', { hasText: slug })).toHaveCount(0);
  });

  test('protected permission has no delete button', async () => {
    await gotoRbacPage(page, 'permissions');
    const row = page.locator('tr', { has: page.locator('td:text-is("Access Admin")') });
    await expect(row.locator('input[value="Delete"]')).toHaveCount(0);
  });
});
