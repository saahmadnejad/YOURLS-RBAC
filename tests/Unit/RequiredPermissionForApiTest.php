<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;

/**
 * Covers the API permission map used by the 'auth_successful' enforcement
 * hook: every core API write must map to a permission, harmless reads to
 * '' (ungated).
 */
class RequiredPermissionForApiTest extends TestCase
{
    public function testRequiredPermissionForApi_WithShorturlAction_ReturnsManageUrls(): void
    {
        // Given: the API action that creates short URLs
        $action = 'shorturl';

        // When: resolving the required permission
        $permission = Rbac::required_permission_for_api($action);

        // Then: it is gated by manage_urls
        $this->assertSame('manage_urls', $permission);
    }

    public function testRequiredPermissionForApi_WithStatsActions_ReturnsViewStats(): void
    {
        // Given: every read-only stats API action
        $actions = ['stats', 'db-stats', 'url-stats', 'expand'];

        // When: resolving the required permission for each
        // Then: each is gated by view_stats
        foreach ($actions as $action) {
            $this->assertSame('view_stats', Rbac::required_permission_for_api($action), "Action '$action' should require view_stats");
        }
    }

    public function testRequiredPermissionForApi_WithVersionAction_ReturnsEmptyString(): void
    {
        // Given: the version probe action (harmless, also used pre-auth by clients)
        $action = 'version';

        // When: resolving the required permission
        // Then: it is not gated
        $this->assertSame('', Rbac::required_permission_for_api($action));
    }

    public function testRequiredPermissionForApi_WithUnknownCustomAction_ReturnsEmptyString(): void
    {
        // Given: an action registered by a third-party plugin
        $action = 'my_custom_action';

        // When: resolving the required permission
        // Then: RBAC does not gate unknown actions
        $this->assertSame('', Rbac::required_permission_for_api($action));
    }

    public function testRequiredPermissionForApi_WithSqlInjectionLikeAction_ReturnsEmptyString(): void
    {
        // Given: a hostile action name
        $action = "shorturl'; DROP TABLE yourls_url";

        // When: resolving the required permission
        // Then: it is treated as unknown (and never interpolated anywhere)
        $this->assertSame('', Rbac::required_permission_for_api($action));
    }

    /**
     * Guards against accidental map regressions: every seeded permission slug
     * that gates an API action must exist in the map keys' vocabulary.
     */
    public function testRequiredPermissionForApi_EveryMappedPermissionIsASeededSlug(): void
    {
        // Given: the known vocabulary of RBAC permission slugs
        $seeded = ['access_admin', 'manage_urls', 'view_stats', 'manage_users',
                   'manage_roles', 'manage_permissions', 'manage_plugins', 'manage_tools'];

        // When: collecting every non-empty permission returned by the API map
        $required = [];
        foreach (['shorturl', 'stats', 'db-stats', 'url-stats', 'expand', 'version'] as $action) {
            $permission = Rbac::required_permission_for_api($action);
            if ($permission !== '') {
                $required[] = $permission;
            }
        }

        // Then: every required permission is a seeded slug
        foreach ($required as $permission) {
            $this->assertContains($permission, $seeded, "Permission '$permission' is not a seeded slug");
        }
    }
}
