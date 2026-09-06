<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;

/**
 * Covers the AJAX permission map: admin-ajax.php add/edit/delete of URLs
 * must map to manage_urls; unknown actions are left ungated (not ours).
 */
class RequiredPermissionForAjaxTest extends TestCase
{
    public function testRequiredPermissionForAjax_WithUrlMutationActions_ReturnsManageUrls(): void
    {
        // Given: every core AJAX action that mutates short URLs
        $actions = ['add', 'edit_display', 'edit_save', 'delete'];

        // When: resolving the required permission for each
        // Then: each is gated by manage_urls
        foreach ($actions as $action) {
            $this->assertSame('manage_urls', Rbac::required_permission_for_ajax($action), "Action '$action' should require manage_urls");
        }
    }

    public function testRequiredPermissionForAjax_WithUnknownAction_ReturnsEmptyString(): void
    {
        // Given: an action dispatched to a plugin via 'yourls_ajax_*'
        $action = 'whatever_plugin_action';

        // When: resolving the required permission
        // Then: RBAC does not gate it (the plugin's callback must guard itself)
        $this->assertSame('', Rbac::required_permission_for_ajax($action));
    }

    public function testRequiredPermissionForAjax_WithEmptyAction_ReturnsEmptyString(): void
    {
        // Given: an empty action string
        // When: resolving the required permission
        // Then: no permission is required
        $this->assertSame('', Rbac::required_permission_for_ajax(''));
    }
}
