<?php
// No direct call.
//
// Note: we intentionally do NOT use the documented YOURLS_UNINSTALL_PLUGIN
// guard here. Since YOURLS commit 8e57e1a (#3478, 2023-02) the constant is
// defined only AFTER uninstall.php has been sandbox-included, so the
// documented guard pattern die()s and kills the whole deactivation request.
// Guarding on YOURLS_ABSPATH is equivalent: it is always defined when YOURLS
// includes this file, and never defined on a direct web hit.
if( !defined( 'YOURLS_ABSPATH' ) ) die();

/**
 * This file is executed when a user deactivates the RBAC plugin.
 *
 * By default the RBAC data (users, roles, permissions) is KEPT so that
 * re-activating the plugin restores access seamlessly. If you want the
 * tables dropped on deactivation, define YOURLS_RBAC_DROP_DATA as true
 * in your config.php:
 *
 *   define( 'YOURLS_RBAC_DROP_DATA', true );
 *
 * WARNING: dropping the tables deletes every RBAC user. Make sure the
 * YOURLS config-file account (user/password in config.php) still works
 * before enabling this, or you will be locked out.
 */

if ( defined('YOURLS_RBAC_DROP_DATA') && YOURLS_RBAC_DROP_DATA ) {
    if (defined('YOURLS_RBAC_TABLE_USERS')) {
        $tables = [
            YOURLS_RBAC_TABLE_USERS,
            YOURLS_RBAC_TABLE_ROLES,
            YOURLS_RBAC_TABLE_PERMISSIONS,
            YOURLS_RBAC_TABLE_USER_ROLES,
            YOURLS_RBAC_TABLE_ROLE_PERMISSIONS,
        ];

        foreach ($tables as $table) {
            $sql = "DROP TABLE IF EXISTS `$table`";
            yourls_get_db('write-rbac_uninstall')->perform($sql);
        }
    }
}

yourls_do_action( 'rbac_uninstall' );
