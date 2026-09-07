// Shared helpers: YOURLS login (nonce-scraped) and RBAC admin navigation.
import { expect } from '@playwright/test';

export const ADMIN = '/admin/';

// Log in via the YOURLS admin login form (scrapes the login nonce).
export async function login(page, username = 'admin', password = 'password123') {
  await page.goto(ADMIN);
  if (!page.url().includes('index.php') || (await page.locator('input[name="username"]').count()) > 0) {
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    await page.click('input[name="submit"], button:has-text("Login")');
  }
  await expect(page.locator('#admin_menu_logout_link').first()).toBeVisible();
}

export async function gotoRbacPage(page, slug) {
  await page.goto(`/admin/plugins.php?page=rbac_${slug}`);
  await expect(page.locator('h2')).toBeVisible();
}

export async function logout(page) {
  // YOURLS logout is a nonce-protected GET link in the admin menu.
  const href = await page.locator('#admin_menu_logout_link a').first().getAttribute('href');
  if (href) await page.goto(href);
  await page.goto(ADMIN);
  await expect(page.locator('input[name="username"]')).toBeVisible();
}

export async function register(page, context) {
  await login(page);
}
