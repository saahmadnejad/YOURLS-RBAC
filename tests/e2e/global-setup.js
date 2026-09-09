// Global setup: reset the shared Docker DB to a deterministic RBAC state
// before the suite — tests share DB state and prior runs (or manual use)
// would otherwise break "last active admin" isolation.
const { execFileSync } = require('child_process');

const SQL = [
  // every non-seed user loses roles first (FK-ish hygiene), then goes away
  "DELETE ur FROM yourls_rbac_user_roles ur JOIN yourls_rbac_users u ON u.id = ur.user_id WHERE u.username <> 'admin'",
  "DELETE FROM yourls_rbac_users WHERE username <> 'admin'",
  // seeded admin keeps/gets the admin role
  "INSERT IGNORE INTO yourls_rbac_user_roles (user_id, role_id) SELECT u.id, r.id FROM yourls_rbac_users u, yourls_rbac_roles r WHERE u.username = 'admin' AND r.slug = 'admin'",
  // drop test-created roles/permissions/urls
  "DELETE FROM yourls_rbac_role_permissions WHERE role_id NOT IN (SELECT id FROM yourls_rbac_roles WHERE slug IN ('admin','manager','editor','user'))",
  "DELETE FROM yourls_rbac_roles WHERE slug NOT IN ('admin','manager','editor','user')",
  "DELETE FROM yourls_rbac_permissions WHERE slug IN ('access_admin','manage_users','manage_roles','manage_permissions','manage_urls','view_stats','manage_plugins','manage_tools') = FALSE",
  "DELETE FROM yourls_url WHERE keyword LIKE 'guard%'",
].join('; ');

module.exports = async () => {
  // COMPOSE_PROJECT_NAME lets a local scratch stack coexist with the main
  // one; CI uses the default project. Tables may not exist on a brand-new
  // stack until ci-bootstrap.sh activates the plugin — a "table doesn't
  // exist" failure here means the bootstrap step was skipped or failed.
  try {
    execFileSync('docker', [
      'compose', 'exec', '-T', 'db',
      'mariadb', '-uyourls', '-pyourls', 'yourls',
      '-e', SQL,
    ], {
      stdio: 'pipe',
      cwd: require('path').resolve(__dirname, '../..'),
      env: { ...process.env },
    });
  } catch (e) {
    throw new Error(`DB reset failed — is the Docker dev env up (and the RBAC plugin activated)? (${e.message})`);
  }
};
