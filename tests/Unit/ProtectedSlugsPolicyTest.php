<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;

/**
 * Covers the system-managed slug policy owned by the Rbac data layer:
 * protected role/permission slugs and the seed schema version contract.
 * (DB-touching behavior is guarded in live tests; here we pin the policy
 * vocabulary itself so nothing silently drops a slug from protection.)
 */
class ProtectedSlugsPolicyTest extends TestCase
{
    public function testProtectedRoleSlugs_AlwaysContainAdminSlug(): void
    {
        // Given: the role the plugin keys its admin guards on
        // When: reading the protected list
        // Then: 'admin' is protected from rename/rename-to/delete
        $this->assertContains('admin', Rbac::PROTECTED_ROLE_SLUGS);
    }

    public function testProtectedRoleSlugs_ContainOnlyLowercaseValidSlugs(): void
    {
        // Given: the protected role slug list
        foreach (Rbac::PROTECTED_ROLE_SLUGS as $slug) {
            // When: validating each slug
            // Then: each is a slug the DB layer would accept
            $this->assertSame($slug, Rbac::validate_slug($slug));
        }
    }

    public function testProtectedPermissionSlugs_ContainEverySlugGatingRbacPages(): void
    {
        // Given: the permissions RBAC's own admin pages are gated by
        $gating = ['access_admin', 'manage_users', 'manage_roles', 'manage_permissions'];

        // When: comparing against the protected list
        // Then: every gating slug is protected from delete/rename
        foreach ($gating as $slug) {
            $this->assertContains($slug, Rbac::PROTECTED_PERMISSION_SLUGS,
                "Permission '$slug' gates an RBAC page but is not protected");
        }
    }

    public function testProtectedPermissionSlugs_ContainOnlyLowercaseValidSlugs(): void
    {
        // Given: the protected permission slug list
        foreach (Rbac::PROTECTED_PERMISSION_SLUGS as $slug) {
            // When: validating each slug
            // Then: each is a slug the DB layer would accept
            $this->assertSame($slug, Rbac::validate_slug($slug));
        }
    }

    public function testSeedVersion_IsPositiveInteger(): void
    {
        // Given: the seed schema version used by maybe_seed_defaults()
        // When: reading the constant
        // Then: it is a positive int (option stored as (int), compare <)
        $this->assertIsInt(Rbac::SEED_VERSION);
        $this->assertGreaterThan(0, Rbac::SEED_VERSION);
    }

    public function testProtectedPermissionSlugs_AllSeededPermutationsStayCoherent(): void
    {
        // Given: a permission slug is protected
        // Then: it is also part of the default seed vocabulary so fresh
        // installs actually contain it (otherwise the guard protects
        // a slug that does not exist)
        $seeded = ['access_admin', 'manage_urls', 'view_stats', 'manage_users',
                   'manage_roles', 'manage_permissions', 'manage_plugins', 'manage_tools'];
        foreach (Rbac::PROTECTED_PERMISSION_SLUGS as $slug) {
            $this->assertContains($slug, $seeded, "Protected slug '$slug' is not in the seed vocabulary");
        }
    }
}
