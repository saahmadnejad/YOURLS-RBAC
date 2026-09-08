// E2E: URL write guards — shunt_edit_link, shunt_edit_link_title,
// shunt_delete_link_by_keyword (plugin.php:130-152). Review item 3: only
// shunt_add_new_link was exercised. Flow: admin creates a short URL, then a
// manage_urls-less user must be denied edit-title / edit / delete via AJAX.
const { test, expect } = require('@playwright/test');
const { login, logout } = require('./helpers');

test.describe.serial('URL edit/delete guards', () => {
  let page;
  let keyword;
  let viewer;

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    page.setDefaultTimeout(20_000);
    viewer = `urlguard_${Date.now()}`;
    await login(page);

    // Admin adds a short URL (manage_urls held)
    await page.goto('/admin/index.php');
    keyword = `guard${Date.now().toString(36)}`;
    await page.fill('#add-url', `https://example.com/${keyword}`);
    await page.fill('#add-keyword', keyword);
    await page.click('#add-button');
    // poll the listing until the keyword shows up (feedback box can be empty)
    let added = false;
    for (let i = 0; i < 10 && !added; i++) {
      await page.waitForTimeout(500);
      added = await page.evaluate(async (kw) => {
        const html = await (await fetch('/admin/index.php', { credentials: 'include' })).text();
        return html.includes(kw);
      }, keyword);
    }
    expect(added).toBe(true);

    // Create a viewer with the seeded 'user' role (access_admin only)
    await page.goto('/admin/plugins.php?page=rbac_users');
    await page.fill('input[name="rbac_username"]', viewer);
    await page.fill('input[name="rbac_password"]', 'viewerPass1!');
    await page.locator('label', { hasText: '(user)' }).locator('input[name="rbac_role_ids[]"]').check();
    await page.click('input[name="save_user"]');
    await page.waitForLoadState('networkidle');
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('viewer cannot save an edit (shunt_edit_link)', async () => {
    await logout(page);
    await login(page, viewer, 'viewerPass1!');
    const result = await page.evaluate(async (kw) => {
      const body = new URLSearchParams({
        action: 'edit_save',
        keyword: kw,
        url: `https://example.com/changed-${kw}`,
        title: 'hijacked',
      });
      const r = await fetch('/admin/admin-ajax.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      return { status: r.status, text: (await r.text()).slice(0, 300) };
    }, keyword);
    
    expect(result.status).toBe(403);
    expect(result.text).toContain('permission');
  });

  test('viewer cannot delete a link (shunt_delete_link_by_keyword)', async () => {
    const result = await page.evaluate(async (kw) => {
      const body = new URLSearchParams({
        action: 'delete',
        keyword: kw,
      });
      const r = await fetch('/admin/admin-ajax.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      return { status: r.status, text: (await r.text()).slice(0, 300) };
    }, keyword);
    expect(result.status).toBe(403);
    expect(result.text).toContain('permission');
  });
});
