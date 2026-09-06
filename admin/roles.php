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

    // Protected slugs cannot be renamed or repointed — guards core seeds.
    $protected_slugs = ['admin'];
    if ($id > 0) {
        $existing = Rbac::get_role_by_id($id);
        if ($existing && in_array($existing->slug, $protected_slugs, true) && $slug !== $existing->slug) {
            yourls_add_notice(yourls__('The Administrator role slug cannot be changed.'));
            yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
            exit();
        }
    }

    if ($id > 0) {
        try {
            Rbac::update_role($id, $name, $slug, $desc);
        } catch (\InvalidArgumentException $e) {
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
        if ($role && $role->slug === 'admin') {
            yourls_add_notice(yourls__('Cannot delete the Administrator role.'));
        } elseif ($role) {
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
                $result = Rbac::delete_role($role_id);
                yourls_add_notice($result ? yourls__('Role deleted.') : yourls__('Failed to delete role.'));
            }
        }
    }
    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_roles'), 302);
    exit();
}

$roles = Rbac::get_all_roles();
$all_perms = Rbac::get_all_permissions();
?>

<h2><?php yourls_e('RBAC Roles'); ?></h2>
<p><?php yourls_e('Roles group permissions together. Users are assigned roles to inherit their permissions.'); ?></p>

<?php
// Build the permission list for the form
$selected_perms = [];
if ($role_id > 0) {
    $role_perms = Rbac::get_role_permissions($role_id);
    foreach ($role_perms as $rp) {
        $selected_perms[$rp->slug] = true;
    }
}
?>

<h3><?php echo $action === 'edit' ? yourls_e('Edit Role') : yourls_e('Add New Role'); ?></h3>

<form method="post" action="">
    <?php yourls_nonce_field('rbac_save_role'); ?>
    <table class="tblSorter" cellpadding="0" cellspacing="1">
        <tbody>
            <tr>
                <th><?php yourls_e('Name'); ?></th>
                <td><input type="text" name="role_name" class="text" size="40" value="<?php echo yourls_esc_attr($role_name); ?>" required /></td>
            </tr>
            <tr>
                <th><?php yourls_e('Slug'); ?></th>
                <td><input type="text" name="role_slug" class="text" size="40" value="<?php echo yourls_esc_attr($role_slug); ?>" required /><br/><small><?php yourls_e('Lowercase, letters, numbers, underscores'); ?></small></td>
            </tr>
            <tr>
                <th><?php yourls_e('Description'); ?></th>
                <td><input type="text" name="role_desc" class="text" size="60" value="<?php echo yourls_esc_attr($role_desc); ?>" /></td>
            </tr>
            <tr>
                <th><?php yourls_e('Permissions'); ?></th>
                <td>
                    <?php if (empty($all_perms)): ?>
                        <p><?php yourls_e('No permissions defined yet.'); ?></p>
                    <?php else: ?>
                        <?php foreach ($all_perms as $p): ?>
                            <label style="display:inline-block;margin-right:10px;">
                                <input type="checkbox" name="permission_slugs[]" value="<?php echo yourls_esc_attr($p->slug); ?>" <?php echo in_array($p->slug, array_keys($selected_perms)) ? 'checked="checked"' : ''; ?> />
                                <?php echo yourls_esc_html($p->name); ?> <small>(<?php echo yourls_esc_html($p->slug); ?>)</small>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>&nbsp;</th>
                <td>
                    <input type="hidden" name="role_id" value="<?php echo $role_id; ?>" />
                    <input type="submit" name="save_role" value="<?php yourls_e('Save Role'); ?>" class="button primary" />
                    <input type="button" value="<?php yourls_e('Cancel'); ?>" class="button" onclick="window.location.href='<?php echo yourls_admin_url('plugins.php?page=rbac_roles'); ?>'" />
                </td>
            </tr>
        </tbody>
    </table>
</form>

<h3><?php yourls_e('Existing Roles'); ?></h3>

<?php if (empty($roles)): ?>
<p><?php yourls_e('No roles defined.'); ?></p>
<?php else: ?>
<table class="tblSorter" cellpadding="0" cellspacing="1">
    <thead>
        <tr>
            <th><?php yourls_e('Name'); ?></th>
            <th><?php yourls_e('Slug'); ?></th>
            <th><?php yourls_e('Permissions'); ?></th>
            <th><?php yourls_e('Actions'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($roles as $r): ?>
        <tr>
            <td><?php echo yourls_esc_html($r->name); ?></td>
            <td><?php echo yourls_esc_html($r->slug); ?></td>
            <td>
                <?php
                $role_perms = Rbac::get_role_permissions($r->id);
                $perm_names = [];
                foreach ($role_perms as $rp) {
                    $perm_names[] = yourls_esc_html($rp->slug);
                }
                echo implode(', ', $perm_names);
                ?>
            </td>
            <td>
                <a href="<?php echo yourls_admin_url('plugins.php?page=rbac_roles&action=edit&id=' . $r->id); ?>" class="button"><?php yourls_e('Edit'); ?></a>
                <?php if ($r->slug !== 'admin'): ?>
                <form method="post" action="<?php echo yourls_esc_attr(yourls_admin_url('plugins.php?page=rbac_roles&action=delete&id=' . $r->id)); ?>" style="display:inline;" onsubmit="return confirm('<?php yourls_e('Are you sure?'); ?>');">
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
<?php endif; ?>
