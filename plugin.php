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
 * Require a permission or die (fail closed in every context: admin, AJAX, API).
 * @param string $permission Permission slug
 * @return void
 */
function yourls_rbac_require(string $permission): void {
    if (!yourls_rbac_can($permission)) {
        \YOURLS\RBAC\Rbac::deny_request();
    }
}

// --- Enforcement ---

/**
 * Map a request to the permission it requires. Returns '' when RBAC does not
 * gate this request (asset, login, API version probe, RBAC's own pages...).
 *
 * @return string Permission slug or ''
 */
function yourls_rbac_required_permission(): string {
    // API actions
    if (\yourls_is_API() && isset($_REQUEST['action'])) {
        return \YOURLS\RBAC\Rbac::required_permission_for_api((string) $_REQUEST['action']);
    }

    // AJAX actions
    if (\yourls_is_Ajax() && isset($_REQUEST['action'])) {
        return \YOURLS\RBAC\Rbac::required_permission_for_ajax((string) $_REQUEST['action']);
    }

    // Admin pages (basename of the script). RBAC's own plugin pages are gated
    // individually by their page callbacks (see yourls_rbac_page_* below).
    if (\yourls_is_admin()) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script === 'plugins.php' && isset($_GET['page']) && str_starts_with((string) $_GET['page'], 'rbac_')) {
            return '';
        }
        return \YOURLS\RBAC\Rbac::required_permission_for_page($script);
    }

    return '';
}

/**
 * Enforce RBAC permissions on every authenticated request.
 * Hooked into 'auth_successful' (fires after YOURLS_USER is set, in every
 * private context: admin pages, admin-ajax.php and yourls-api.php).
 *
 * Note: plugin pages (plugins.php?page=rbac_*) are additionally gated by the
 * individual page callbacks below — the map only needs the coarse page level.
 *
 * @return void
 */
function yourls_rbac_enforce() {
    // Plugin (de)activation is a 'manage_plugins' action in disguise.
    if (\yourls_is_admin() && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'plugins.php'
        && isset($_GET['action'], $_GET['plugin'])
        && in_array($_GET['action'], ['activate', 'deactivate'], true)) {
        yourls_rbac_require('manage_plugins');
    }

    $permission = yourls_rbac_required_permission();
    if ($permission !== '' && !yourls_rbac_can($permission)) {
        \YOURLS\RBAC\Rbac::deny_request();
    }
}
yourls_add_action('auth_successful', 'yourls_rbac_enforce');

/**
 * Write guards: YOURLS core (admin form, AJAX and API alike) funnels every
 * URL mutation through these functions, so shunting them enforces
 * 'manage_urls' everywhere in one place each.
 */

function yourls_rbac_guard_add_new_link($pre, $url, $keyword = '', $title = '') {
    if (!yourls_rbac_can('manage_urls')) {
        return \YOURLS\RBAC\Rbac::url_write_denied();
    }
    return $pre;
}
yourls_add_filter('shunt_add_new_link', 'yourls_rbac_guard_add_new_link', 10, 4);

function yourls_rbac_guard_edit_link($pre, $keyword, $url, $keyword2 = '', $newkeyword = '', $title = '') {
    if (!yourls_rbac_can('manage_urls')) {
        return \YOURLS\RBAC\Rbac::url_write_denied();
    }
    return $pre;
}
yourls_add_filter('shunt_edit_link', 'yourls_rbac_guard_edit_link', 10, 6);

function yourls_rbac_guard_edit_link_title($pre, $keyword, $title) {
    if (!yourls_rbac_can('manage_urls')) {
        return \YOURLS\RBAC\Rbac::url_write_denied();
    }
    return $pre;
}
yourls_add_filter('shunt_edit_link_title', 'yourls_rbac_guard_edit_link_title', 10, 3);

function yourls_rbac_guard_delete_link_by_keyword($pre, $keyword) {
    if (!yourls_rbac_can('manage_urls')) {
        return \YOURLS\RBAC\Rbac::url_write_denied();
    }
    return $pre;
}
yourls_add_filter('shunt_delete_link_by_keyword', 'yourls_rbac_guard_delete_link_by_keyword', 10, 2);

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
 * On admin_init: ensure tables exist and defaults are seeded (idempotent —
 * also backfills new permissions/roles on upgrades of pre-existing installs).
 */
function yourls_rbac_maybe_init() {
    if (!\YOURLS\RBAC\Rbac::tables_exist()) {
        \YOURLS\RBAC\Rbac::create_tables();
    }
    \YOURLS\RBAC\Rbac::seed_defaults();
}
yourls_add_action('admin_init', 'yourls_rbac_maybe_init');

/**
 * Add admin menu entries.
 */
function yourls_rbac_admin_menu() {
    if (!defined('YOURLS_USER')) {
        return;
    }

    // Only render the menu entry for users who can reach at least one RBAC page.
    if (!yourls_rbac_can('manage_roles') && !yourls_rbac_can('manage_users') && !yourls_rbac_can('manage_permissions')) {
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

    // Gate the pages before YOURLS renders the admin HTML head, so a denial
    // sends a real 403 status (yourls_die() after html_head cannot change it).
    yourls_add_action('load-rbac_users',       fn() => yourls_rbac_require('manage_users'));
    yourls_add_action('load-rbac_roles',       fn() => yourls_rbac_require('manage_roles'));
    yourls_add_action('load-rbac_permissions', fn() => yourls_rbac_require('manage_permissions'));
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
