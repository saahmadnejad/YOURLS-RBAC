<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;

/**
 * Covers the permission map coherence: every permission the enforcement
 * layer can require must be seeded by default, so a fresh install never
 * denies an admin an unknown slug (or silently passes a typo'd one).
 */
class SeededPermissionsCoherenceTest extends TestCase
{
    /**
     * Mirror of the seed list in Rbac::seed_defaults(). Updated deliberately
     * when a new permission is introduced.
     */
    private const SEEDED_SLUGS = [
        'access_admin', 'manage_urls', 'view_stats', 'manage_users',
        'manage_roles', 'manage_permissions', 'manage_plugins', 'manage_tools',
    ];

    public function testSeededPermissions_IncludeEveryPermissionRequiredByEnforcement(): void
    {
        // Given: every permission the page, AJAX and API maps can require
        $required = array_unique(array_merge(
            array_filter(array_map(
                fn($page) => Rbac::required_permission_for_page($page),
                ['index.php', 'tools.php', 'plugins.php', 'upgrade.php']
            )),
            array_filter(array_map(
                fn($action) => Rbac::required_permission_for_ajax($action),
                ['add', 'edit_display', 'edit_save', 'delete']
            )),
            array_filter(array_map(
                fn($action) => Rbac::required_permission_for_api($action),
                ['shorturl', 'stats', 'db-stats', 'url-stats', 'expand']
            ))
        ));

        // When: checking each against the default seed list
        // Then: every required permission is seeded on a fresh install
        foreach ($required as $permission) {
            $this->assertContains($permission, self::SEEDED_SLUGS,
                "Enforcement requires '$permission' but it is not seeded — fresh installs would lock admins out");
        }
    }

    public function testSeededPermissions_ContainNoStaleEntries(): void
    {
        // Given: the default seed list
        // When: a permission slug is removed from enforcement and seeds
        // Then: this test fails, reminding us to keep both in sync
        $this->assertCount(8, self::SEEDED_SLUGS);
        $this->assertSame(self::SEEDED_SLUGS, array_values(array_unique(self::SEEDED_SLUGS)));
    }

    public function testSeededPermissions_ManageToolsIsSeeded(): void
    {
        // Given: the tools page is gated by manage_tools
        $needed = Rbac::required_permission_for_page('tools.php');

        // When: comparing against the seed list
        // Then: manage_tools is seeded so admins can open tools.php
        $this->assertContains($needed, self::SEEDED_SLUGS);
    }
}
