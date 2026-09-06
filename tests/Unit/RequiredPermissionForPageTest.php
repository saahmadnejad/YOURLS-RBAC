<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;

/**
 * Covers the admin page permission map: each core admin page maps to the
 * permission needed to open it; unknown scripts are ungated.
 */
class RequiredPermissionForPageTest extends TestCase
{
    public function testRequiredPermissionForPage_WithAdminDashboard_ReturnsAccessAdmin(): void
    {
        // Given: the main admin dashboard
        $script = 'index.php';

        // When: resolving the required permission
        // Then: only access_admin is needed
        $this->assertSame('access_admin', Rbac::required_permission_for_page($script));
    }

    public function testRequiredPermissionForPage_WithToolsPage_ReturnsManageTools(): void
    {
        // Given: the tools page (bookmarklets, maintenance queries)
        $script = 'tools.php';

        // When: resolving the required permission
        // Then: it is gated by the manage_tools permission
        $this->assertSame('manage_tools', Rbac::required_permission_for_page($script));
    }

    public function testRequiredPermissionForPage_WithPluginsAndUpgradePages_ReturnsManagePlugins(): void
    {
        // Given: plugin management and the upgrade routine
        $scripts = ['plugins.php', 'upgrade.php'];

        // When: resolving the required permission for each
        // Then: both are gated by manage_plugins
        foreach ($scripts as $script) {
            $this->assertSame('manage_plugins', Rbac::required_permission_for_page($script), "Page '$script' should require manage_plugins");
        }
    }

    public function testRequiredPermissionForPage_WithUnknownScript_ReturnsEmptyString(): void
    {
        // Given: a page RBAC does not know (login page, install page...)
        $script = 'install.php';

        // When: resolving the required permission
        // Then: it is not gated
        $this->assertSame('', Rbac::required_permission_for_page($script));
    }

    public function testRequiredPermissionForPage_WithPathTraversalLikeScript_ReturnsEmptyString(): void
    {
        // Given: a hostile script name
        $script = '../../config.php';

        // When: resolving the required permission
        // Then: it is treated as unknown, never interpolated
        $this->assertSame('', Rbac::required_permission_for_page($script));
    }
}
