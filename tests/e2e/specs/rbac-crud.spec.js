// E2E: Role CRUD with permission sync + Permission CRUD. Addresses review
// items 1 & 2: the save_role / save_permission POST paths were untested.
const { test, expect } = require('@playwright/test');
const { login, gotoRbacPage, itemRow, confirmDelete } = require('./helpers');

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

    // Helper: the matrix row for a role slug (th text contains the slug)
    const matrixRow = () => page.locator('table.rbac-matrix tbody tr', { hasText: slug });
    // Helper: the row's checkbox for a permission slug (column header match)
    const permBox = (row, perm) => row.locator(`td input[aria-label*="${perm}"]`);

    // Create with a couple of permissions checked
    await gotoRbacPage(page, 'roles');
    await page.fill('input[name="role_name"]', 'E2E Role');
    await page.fill('input[name="role_slug"]', slug);
    await page.fill('input[name="role_desc"]', 'created by e2e');
    await page.check('input[name="permission_slugs[]"][value="access_admin"]');
    await page.check('input[name="permission_slugs[]"][value="view_stats"]');
    await page.click('input[name="save_role"]');
    await gotoRbacPage(page, 'roles');
    const row = matrixRow();
    await expect(row).toHaveCount(1);
    await expect(permBox(row, 'view_stats')).toBeChecked(); // synced

    // Edit: add manage_urls, drop view_stats — sync must follow the checkboxes
    await row.locator('a:has-text("Edit")').click();
    await expect(page.locator('input[name="role_slug"]')).toHaveValue(slug);
    await page.check('input[name="permission_slugs[]"][value="manage_urls"]');
    await page.uncheck('input[name="permission_slugs[]"][value="view_stats"]');
    await page.click('input[name="save_role"]');
    await gotoRbacPage(page, 'roles');
    const edited = matrixRow();
    await expect(permBox(edited, 'manage_urls')).toBeChecked();
    await expect(permBox(edited, 'view_stats')).not.toBeChecked();

    // Delete via the confirm dialog
    await edited.locator('input[value="Delete"]').click();
    await confirmDelete(page);
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'roles');
    await expect(matrixRow()).toHaveCount(0);
  });

  test('admin role row is protected in the matrix', async () => {
    await gotoRbacPage(page, 'roles');
    const adminRow = page.locator('table.rbac-matrix tbody tr.is-protected', { hasText: '(admin)' });
    await expect(adminRow).toHaveCount(1);
    await expect(adminRow.locator('input[value="Delete"]')).toHaveCount(0);
    // every permission checkbox ticked
    await expect(adminRow.locator('input:not(:checked)')).toHaveCount(0);
  });

  test('matrix shows the seeded permission columns', async () => {
    await gotoRbacPage(page, 'roles');
    const head = page.locator('table.rbac-matrix thead');
    for (const slug of ['access_admin', 'manage_urls', 'view_stats', 'manage_tools']) {
      await expect(head.getByText(slug, { exact: true })).toBeVisible();
    }
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
    const row = itemRow(page, 'E2E Perm');
    await expect(row).toBeVisible();
    await expect(row).toContainText(slug);

    // Edit
    await row.locator('a:has-text("Edit")').click();
    await expect(page.locator('input[name="perm_slug"]')).toHaveValue(slug);
    await page.fill('input[name="perm_name"]', 'E2E Perm Renamed');
    await page.click('input[name="save_permission"]');
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'permissions');
    await expect(itemRow(page, 'E2E Perm Renamed')).toBeVisible();

    // Delete
    await itemRow(page, 'E2E Perm Renamed').locator('input[value="Delete"]').click();
    await confirmDelete(page);
    await page.waitForLoadState('networkidle');
    await gotoRbacPage(page, 'permissions');
    await expect(itemRow(page, 'E2E Perm Renamed')).toHaveCount(0);
  });

  test('protected permission has no delete button', async () => {
    await gotoRbacPage(page, 'permissions');
    const row = itemRow(page, 'Access Admin');
    await expect(row).toContainText('protected');
    await expect(row.locator('input[value="Delete"]')).toHaveCount(0);
  });

  test('slug hint flags invalid characters', async () => {
    await gotoRbacPage(page, 'roles');
    await page.fill('input[name="role_slug"]', 'Bad Slug!');
    await expect(page.locator('.rbac-slug-hint')).toBeVisible();
    await page.fill('input[name="role_slug"]', 'good_slug');
    await expect(page.locator('.rbac-slug-hint')).toBeHidden();
  });
});
