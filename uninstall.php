<?php
// No direct call.
if( !defined( 'YOURLS_UNINSTALL_PLUGIN' ) ) die();

/**
 * This file is executed when a user deactivates the RBAC plugin.
 *
 * By default it drops the RBAC tables. If you prefer to keep the data,
 * define YOURLS_RBAC_KEEP_DATA as true in your config.php:
 *
 *   define( 'YOURLS_RBAC_KEEP_DATA', true );
 */

if ( !defined('YOURLS_RBAC_KEEP_DATA') || !YOURLS_RBAC_KEEP_DATA ) {
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
            yourls_get_db('write-rbac-uninstall')->perform($sql);
        }
    }
}

// Clean up options
yourls_delete_option('rbac_needs_seed');

yourls_do_action( 'rbac_uninstall' );
