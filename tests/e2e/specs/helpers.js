// Shared helpers: YOURLS login (nonce-scraped) and RBAC admin navigation.
import { expect } from '@playwright/test';

export const ADMIN = '/admin/';

// Log in via the YOURLS admin login form. Returns true on success, false
// when the credentials were rejected (e.g. inactive user) and
// `expectReject` is false.
export async function login(page, username = 'admin', password = 'password123', expectReject = false) {
  const attempt = async () => {
    await page.goto(ADMIN);
    if ((await page.locator('input[name="username"]').count()) > 0) {
      await page.fill('input[name="username"]', username);
      await page.fill('input[name="password"]', password);
      await page.click('input[name="submit"], button:has-text("Login")');
    }
    if (expectReject) {
      // rejected credentials land back on the login form
      await expect(page.locator('input[name="username"]')).toBeVisible({ timeout: 15_000 });
      return false;
    }
    await expect(page.locator('#admin_menu_logout_link').first()).toBeVisible({ timeout: 15_000 });
    return true;
  };
  try {
    return await attempt();
  } catch {
    if (expectReject) throw new Error('login unexpectedly succeeded');
    return await attempt(); // one retry — first login after container start can be slow
  }
}

export async function gotoRbacPage(page, slug) {
  await page.goto(`/admin/plugins.php?page=rbac_${slug}`);
  await expect(page.locator('h2')).toBeVisible();
}

// Row locator scoped by exact first-column (username/name/slug) value.
// Roles/permission cells elsewhere in the row can repeat the same text, so
// match on the first td only.
export function rowByFirstCell(page, value) {
  return page.locator(`xpath=//tbody/tr[td[1][normalize-space(text())='${value}']]`);
}

export async function logout(page) {
  // YOURLS logout is a nonce-protected GET link in the admin menu.
  // No-op when already logged out (login form showing).
  const loginForm = await page.locator('input[name="username"]').count();
  if (loginForm === 0) {
    const href = await page.locator('#admin_menu_logout_link a').first().getAttribute('href');
    if (href) await page.goto(href);
  }
  await page.goto(ADMIN);
  await expect(page.locator('input[name="username"]')).toBeVisible();
}
