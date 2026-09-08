<?php
// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

use YOURLS\RBAC\Rbac;

$action = in_array($_GET['action'] ?? '', ['edit', 'delete', ''], true) ? ($_GET['action'] ?? '') : '';
$role_id = 0;
$role_name = '';
$role_slug = '';
$role_desc = '';

if ($action === 'edit' && isset($_GET['id'])) {
    $role_id = (int) $_GET['id'];
    $role = Rbac::get_role_by_id($role_id);
    if ($role) {
        $role_name = $role->name;
        $role_slug = $role->slug;
        $role_desc = $role->description ?? '';
    } else {
        $action = '';
    }
}

if (isset($_POST['save_role'])) {
    yourls_verify_nonce('rbac_save_role');

    $name = trim($_POST['role_name'] ?? '');
    $slug = trim($_POST['role_slug'] ?? '');
    $desc = trim($_POST['role_desc'] ?? '');
    $id = (int) ($_POST['role_id'] ?? 0);
    $permission_slugs = is_array($_POST['permission_slugs'] ?? null) ? $_POST['permission_slugs'] : [];

    if ($id > 0) {
        try {
            Rbac::update_role($id, $name, $slug, $desc);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
            yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
            exit();
        }
    } else {
        try {
            $result = Rbac::create_role($name, $slug, $desc);
            $id = (int) $result;
        } catch (\InvalidArgumentException $e) {
            yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
            yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
            exit();
        }
    }

    if ($id > 0) {
        // A role you hold cannot be stripped of the very permissions that
        // let you manage roles — no self-privilege-revocation lockouts, and
        // the admin role always keeps every permission.
        $my_role = false;
        if (defined('YOURLS_USER')) {
            $me = Rbac::get_user_by_username(YOURLS_USER);
            if ($me) {
                foreach (Rbac::get_user_roles($me->id) as $r) {
                    if ((int) $r->id === $id) {
                        $my_role = true;
                        break;
                    }
                }
            }
        }
        $edited = Rbac::get_role_by_id($id);
        if ($edited && $edited->slug === 'admin') {
            // Administrator role always keeps all permissions.
            $permission_slugs = array_map(fn($p) => $p->slug, Rbac::get_all_permissions());
        } elseif ($my_role && !in_array('manage_roles', $permission_slugs, true)) {
            yourls_add_notice(yourls__('You cannot remove "manage_roles" from a role assigned to you.'));
            yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
            exit();
        }

        $perms = Rbac::get_all_permissions();
        foreach ($perms as $p) {
            if (in_array($p->slug, $permission_slugs)) {
                if (!Rbac::role_has_permission($id, $p->id)) {
                    Rbac::assign_permission_to_role($id, $p->id);
                }
            } else {
                if (Rbac::role_has_permission($id, $p->id)) {
                    Rbac::remove_permission_from_role($id, $p->id);
                }
            }
        }
    }

    yourls_add_notice($id > 0 ? yourls__('Role saved.') : yourls__('Failed to save role.'));
    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
    exit();
}

if ($action === 'delete' && isset($_REQUEST['id'])) {
    $role_id = (int) $_REQUEST['id'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Destructive actions must be POSTed, not fetched.
        yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
        exit();
    }
    yourls_verify_nonce('rbac_delete_role');
    if ($role_id > 0) {
        $role = Rbac::get_role_by_id($role_id);
        if ($role) {
            // Deleting a role you hold would demote you mid-session.
            $is_self = false;
            if (defined('YOURLS_USER')) {
                $me = Rbac::get_user_by_username(YOURLS_USER);
                if ($me) {
                    foreach (Rbac::get_user_roles($me->id) as $r) {
                        if ((int) $r->id === $role_id) {
                            $is_self = true;
                            break;
                        }
                    }
                }
            }
            if ($is_self) {
                yourls_add_notice(yourls__('You cannot delete a role assigned to you.'));
            } else {
                try {
                    $result = Rbac::delete_role($role_id);
                    yourls_add_notice($result ? yourls__('Role deleted.') : yourls__('Failed to delete role.'));
                } catch (\RuntimeException $e) {
                    yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
                }
            }
        }
    }
    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
    exit();
}

$roles = Rbac::get_all_roles();
$all_perms = Rbac::get_all_permissions();
?>

<div class="rbac-wrap">
    <h2><?php yourls_e('RBAC Roles'); ?></h2>
    <p><?php yourls_e('Roles group permissions together. Users are assigned roles to inherit their permissions.'); ?></p>

    <div class="rbac-card">
        <h3><?php echo $action === 'edit' ? yourls_e('Edit Role') : yourls_e('Add New Role'); ?></h3>
        <form method="post" action="" class="rbac-form">
            <?php yourls_nonce_field('rbac_save_role'); ?>
            <label for="role-name"><?php yourls_e('Name'); ?></label>
            <div class="rbac-field">
                <input type="text" id="role-name" name="role_name" value="<?php echo yourls_esc_attr($role_name); ?>" required />
            </div>
            <label for="role-slug"><?php yourls_e('Slug'); ?></label>
            <div class="rbac-field">
                <input type="text" id="role-slug" name="role_slug" value="<?php echo yourls_esc_attr($role_slug); ?>" required data-rbac-slug="1" />
                <span class="rbac-hint rbac-slug-hint" style="display:none;color:#e74c3c;"><?php yourls_e('Lowercase letters, numbers and underscores only.'); ?></span>
            </div>
            <label for="role-desc"><?php yourls_e('Description'); ?></label>
            <div class="rbac-field">
                <input type="text" id="role-desc" name="role_desc" value="<?php echo yourls_esc_attr($role_desc); ?>" />
            </div>
            <span class="rbac-field-label"><?php yourls_e('Permissions'); ?></span>
            <div class="rbac-field">
                <?php if (empty($all_perms)): ?>
                    <p><?php yourls_e('No permissions defined yet.'); ?></p>
                <?php else: ?>
                    <?php
                    $selected_perms = [];
                    if ($role_id > 0) {
                        $role_perms = Rbac::get_role_permissions($role_id);
                        foreach ($role_perms as $rp) {
                            $selected_perms[$rp->slug] = true;
                        }
                    }
                    $is_admin_role = false;
                    if ($role_id > 0) {
                        $edited_role = Rbac::get_role_by_id($role_id);
                        $is_admin_role = $edited_role && $edited_role->slug === 'admin';
                    }
                    ?>
                    <?php foreach ($all_perms as $p): ?>
                        <label style="display:inline-block;margin-right:10px;">
                            <input type="checkbox" name="permission_slugs[]" value="<?php echo yourls_esc_attr($p->slug); ?>"
                                <?php echo in_array($p->slug, array_keys($selected_perms)) ? 'checked="checked"' : ''; ?>
                                <?php echo $is_admin_role ? 'disabled="disabled" checked="checked"' : ''; ?> />
                            <?php echo yourls_esc_html($p->name); ?> <small>(<?php echo yourls_esc_html($p->slug); ?>)</small>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($is_admin_role): ?>
                        <p class="rbac-hint"><?php yourls_e('The administrator role always keeps every permission.'); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <span></span>
            <div class="rbac-field">
                <input type="hidden" name="role_id" value="<?php echo $role_id; ?>" />
                <input type="submit" name="save_role" value="<?php yourls_e('Save Role'); ?>" class="button primary" />
                <input type="button" value="<?php yourls_e('Cancel'); ?>" class="button" onclick="window.location.href='<?php echo yourls_admin_url('plugins.php?page=rbac_roles'); ?>'" />
            </div>
        </form>
    </div>

    <div class="rbac-card">
        <div class="rbac-toolbar">
            <h3 style="border:none;margin:0;"><?php yourls_e('Permission Matrix'); ?></h3>
            <input type="search" class="rbac-search" placeholder="<?php yourls_e('Search roles or permissions…'); ?>" data-rbac-target="#rbac-matrix" />
        </div>
        <?php if (empty($roles) || empty($all_perms)): ?>
            <p><?php yourls_e('No roles or permissions defined.'); ?></p>
        <?php else: ?>
            <div class="rbac-matrix-wrap">
                <table class="rbac-matrix" id="rbac-matrix">
                    <thead>
                        <tr>
                            <th><?php yourls_e('Role'); ?></th>
                            <?php foreach ($all_perms as $p): ?>
                                <th title="<?php echo yourls_esc_attr($p->description ?? $p->name); ?>"><?php echo yourls_esc_html($p->slug); ?></th>
                            <?php endforeach; ?>
                            <th><?php yourls_e('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $r): ?>
                            <?php
                            $role_perms = Rbac::get_role_permissions($r->id);
                            $perm_slugs = [];
                            foreach ($role_perms as $rp) {
                                $perm_slugs[$rp->slug] = true;
                            }
                            $protected = in_array($r->slug, Rbac::PROTECTED_ROLE_SLUGS, true);
                            ?>
                            <tr class="<?php echo $protected ? 'is-protected' : ''; ?>">
                                <th>
                                    <?php echo yourls_esc_html($r->name); ?>
                                    <small>(<?php echo yourls_esc_html($r->slug); ?>)</small>
                                </th>
                                <?php foreach ($all_perms as $p): ?>
                                    <td>
                                        <?php if ($protected): ?>
                                            <input type="checkbox" checked="checked" disabled="disabled" aria-label="<?php echo yourls_esc_attr($r->slug . ' / ' . $p->slug); ?>" />
                                        <?php else: ?>
                                            <input type="checkbox" disabled="disabled" aria-label="<?php echo yourls_esc_attr($r->slug . ' / ' . $p->slug); ?>" <?php echo isset($perm_slugs[$p->slug]) ? 'checked="checked"' : ''; ?> />
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <a href="<?php echo yourls_admin_url('plugins.php?page=rbac_roles&action=edit&id=' . $r->id); ?>" class="button"><?php yourls_e('Edit'); ?></a>
                                    <?php if (!$protected): ?>
                                        <form method="post" action="<?php echo yourls_esc_attr(yourls_admin_url('plugins.php?page=rbac_roles&action=delete&id=' . $r->id)); ?>" style="display:inline;" data-rbac-confirm="<?php yourls_e('Delete this role? Users holding it lose its permissions.'); ?>">
                                            <?php yourls_nonce_field('rbac_delete_role'); ?>
                                            <input type="hidden" name="id" value="<?php echo (int) $r->id; ?>" />
                                            <input type="hidden" name="action" value="delete" />
                                            <input type="submit" value="<?php yourls_e('Delete'); ?>" class="button" style="background:#e74c3c;color:#fff;" />
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<dialog id="rbac-confirm-dialog">
    <div class="rbac-confirm-title"><?php yourls_e('Confirm'); ?></div>
    <div class="rbac-confirm-message"></div>
    <div class="rbac-confirm-actions">
        <button type="button" class="button rbac-confirm-no"><?php yourls_e('Cancel'); ?></button>
        <button type="button" class="button primary rbac-confirm-yes"><?php yourls_e('Delete'); ?></button>
    </div>
</dialog>
