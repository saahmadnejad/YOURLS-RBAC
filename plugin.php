<?php
/*
Plugin Name: YOURLS-RBAC
Plugin URI: https://github.com/saahmadnejad/YOURLS-RBAC
Description: Role-Based Access Control (RBAC) user management for YOURLS: user accounts, roles, and permissions stored in the database.
Version: 0.2
Author: Ali
Author URI: https://ali.ahmadnejad.ir/
*/

// No direct call
if (!defined('YOURLS_ABSPATH')) die();

// Define RBAC table names (respecting YOURLS_DB_PREFIX)
if (!defined('YOURLS_RBAC_TABLE_USERS'))
    define('YOURLS_RBAC_TABLE_USERS', YOURLS_DB_PREFIX . 'rbac_users');
if (!defined('YOURLS_RBAC_TABLE_ROLES'))
    define('YOURLS_RBAC_TABLE_ROLES', YOURLS_DB_PREFIX . 'rbac_roles');
if (!defined('YOURLS_RBAC_TABLE_PERMISSIONS'))
    define('YOURLS_RBAC_TABLE_PERMISSIONS', YOURLS_DB_PREFIX . 'rbac_permissions');
if (!defined('YOURLS_RBAC_TABLE_USER_ROLES'))
    define('YOURLS_RBAC_TABLE_USER_ROLES', YOURLS_DB_PREFIX . 'rbac_user_roles');
if (!defined('YOURLS_RBAC_TABLE_ROLE_PERMISSIONS'))
    define('YOURLS_RBAC_TABLE_ROLE_PERMISSIONS', YOURLS_DB_PREFIX . 'rbac_role_permissions');

// Include the core RBAC class
require_once __DIR__ . '/includes/rbac.php';

// --- Helper functions ---

/**
 * Check if the current user has a specific permission.
 * @param string $permission Permission slug
 * @return bool
 */
function yourls_rbac_can(string $permission): bool {
    if (!\YOURLS\RBAC\Rbac::tables_exist()) {
        return false;
    }
    return \YOURLS\RBAC\Rbac::current_user_can($permission);
}

/**
 * Check if the current user has a specific role.
 * @param string $role Role slug
 * @return bool
 */
function yourls_rbac_has_role(string $role): bool {
    if (!\YOURLS\RBAC\Rbac::tables_exist()) {
        return false;
    }
    return \YOURLS\RBAC\Rbac::current_user_has_role($role);
}

/**
 * Require a permission or die.
 * @param string $permission Permission slug
 * @return void
 */
function yourls_rbac_require(string $permission): void {
    if (!yourls_rbac_can($permission)) {
        if (yourls_is_admin()) {
            yourls_die(
                yourls__('You do not have permission to access this page.'),
                yourls__('Forbidden'),
                403
            );
        }
    }
}

// --- Hooks ---

/**
 * On plugins_loaded: load DB users into YOURLS's global auth array.
 * This lets YOURLS's native auth flow handle login/cookies seamlessly.
 */
function yourls_rbac_load_users() {
    \YOURLS\RBAC\Rbac::load_users_into_globals();
}
yourls_add_action('plugins_loaded', 'yourls_rbac_load_users');

/**
 * On plugin activation: create tables and seed defaults.
 */
function yourls_rbac_activate($plugin) {
    if ($plugin !== 'rbac/plugin.php') {
        return;
    }
    if (!\YOURLS\RBAC\Rbac::tables_exist()) {
        \YOURLS\RBAC\Rbac::create_tables();
    }
    \YOURLS\RBAC\Rbac::seed_defaults();
    yourls_add_notice(yourls__('RBAC tables installed and default roles/permissions seeded.'));
}
yourls_add_action('activated_plugin', 'yourls_rbac_activate');

/**
 * On admin_init: ensure tables exist on every admin page load (lazy init).
 */
function yourls_rbac_maybe_init() {
    if (!\YOURLS\RBAC\Rbac::tables_exist()) {
        \YOURLS\RBAC\Rbac::create_tables();
        \YOURLS\RBAC\Rbac::seed_defaults();
    } elseif (yourls_get_option('rbac_needs_seed', false)) {
        \YOURLS\RBAC\Rbac::seed_defaults();
        yourls_delete_option('rbac_needs_seed');
    }
}
yourls_add_action('admin_init', 'yourls_rbac_maybe_init');

/**
 * Add admin menu entries.
 */
function yourls_rbac_admin_menu() {
    if (!defined('YOURLS_USER')) {
        return;
    }

    $admin_class = yourls_rbac_can('manage_roles') || yourls_rbac_can('manage_users') || yourls_rbac_can('manage_permissions')
        ? ' class="active"' : '';

    echo '<li id="admin_menu_rbac_link"' . $admin_class . '>';
    echo '<a href="' . yourls_admin_url('plugins.php?page=rbac_users') . '">User Management</a></li>';
}
yourls_add_action('admin_menu', 'yourls_rbac_admin_menu');

/**
 * Load tablesorter CSS/JS for RBAC admin pages.
 */
function yourls_rbac_html_head($context) {
    if (!is_string($context)) {
        return;
    }
    if (strpos($context, 'plugin_page_rbac_') === 0) {
        echo '<link rel="stylesheet" href="' . yourls_site_url() . '/css/tablesorter.css?v=' . YOURLS_VERSION . '" type="text/css" media="screen" />';
        echo '<script src="' . yourls_site_url() . '/js/jquery-3.tablesorter.min.js?v=' . YOURLS_VERSION . '"></script>';
        echo '<script src="' . yourls_site_url() . '/js/tablesorte.js?v=' . YOURLS_VERSION . '"></script>';
    }
}
yourls_add_action('html_head', 'yourls_rbac_html_head');

/**
 * Register plugin admin pages.
 */
function yourls_rbac_register_pages() {
    yourls_register_plugin_page('rbac_users',       'Users',       'yourls_rbac_page_users');
    yourls_register_plugin_page('rbac_roles',       'Roles',       'yourls_rbac_page_roles');
    yourls_register_plugin_page('rbac_permissions', 'Permissions', 'yourls_rbac_page_permissions');
}
yourls_add_action('plugins_loaded', 'yourls_rbac_register_pages');

function yourls_rbac_page_users() {
    if (!yourls_rbac_can('manage_users')) {
        yourls_die(
            yourls__('You do not have permission to manage users.'),
            yourls__('Forbidden'),
            403
        );
    }
    yourls_include_file_sandbox(__DIR__ . '/admin/users.php');
}

function yourls_rbac_page_roles() {
    if (!yourls_rbac_can('manage_roles')) {
        yourls_die(
            yourls__('You do not have permission to manage roles.'),
            yourls__('Forbidden'),
            403
        );
    }
    yourls_include_file_sandbox(__DIR__ . '/admin/roles.php');
}

function yourls_rbac_page_permissions() {
    if (!yourls_rbac_can('manage_permissions')) {
        yourls_die(
            yourls__('You do not have permission to manage permissions.'),
            yourls__('Forbidden'),
            403
        );
    }
    yourls_include_file_sandbox(__DIR__ . '/admin/permissions.php');
}
