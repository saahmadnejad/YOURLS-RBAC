<?php
// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

use YOURLS\RBAC\Rbac;

$action = in_array($_GET['action'] ?? '', ['edit', 'delete', ''], true) ? ($_GET['action'] ?? '') : '';
$form_error = '';
$user_id = 0;
$user_username = '';
$user_password = '';
$user_email = '';
$user_active = 1;

if ($action === 'edit' && isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    $user = Rbac::get_user_by_id($user_id);
    if ($user) {
        $user_username = $user->username;
        $user_email = $user->email ?? '';
        $user_active = $user->active;
    } else {
        $action = '';
    }
}

if (isset($_POST['save_user'])) {
    yourls_verify_nonce('rbac_save_user');

    $username = trim($_POST['rbac_username'] ?? '');
    $password = $_POST['rbac_password'] ?? '';
    $email = trim($_POST['rbac_email'] ?? '');
    $active = (int) ($_POST['rbac_active'] ?? 1);
    $id = (int) ($_POST['rbac_user_id'] ?? 0);
    $role_ids = array_map('intval', $_POST['rbac_role_ids'] ?? []);
    $role_ids = array_values(array_unique(array_filter($role_ids, fn($r) => $r > 0)));

    if ($id > 0) {
        // Editing your own account? Deactivating or demoting yourself (or the
        // last active admin) is refused to prevent lockouts.
        // Validation/guard errors render inline in the form — YOURLS notices
        // (admin_notices hook) fire before the plugin page body, so a notice
        // registered during this POST would never display.
        $target = Rbac::get_user_by_id($id);
        $is_self = $target && defined('YOURLS_USER') && $target->username === YOURLS_USER;
        if ($is_self && $active === 0) {
            $form_error = yourls__('You cannot deactivate your own account.');
            // keep $action = 'edit' so the form re-renders as "Edit User"
            goto rbac_render_users;
        }

        $current_roles = Rbac::get_user_roles($id);
        $current_role_ids = array_map(fn($r) => $r->id, $current_roles);
        $current_slugs = array_map(fn($r) => $r->slug, $current_roles);
        $new_slugs = [];
        foreach ($role_ids as $rid) {
            $role = Rbac::get_role_by_id($rid);
            if ($role) {
                $new_slugs[] = $role->slug;
            }
        }

        $loses_admin = in_array('admin', $current_slugs, true) && !in_array('admin', $new_slugs, true);
        if ($loses_admin && Rbac::count_active_users_with_role('admin') <= 1) {
            $form_error = yourls__('Cannot remove the administrator role from the last active administrator.');
            $user_password = '';
            goto rbac_render_users;
        }

        $update_data = [
            'username' => $username,
            'email'    => $email,
            'active'   => $active,
        ];
        if (!empty($password)) {
            $update_data['password'] = $password;
        }
        try {
            Rbac::update_user($id, $update_data);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $form_error = yourls__('Error: ' . $e->getMessage());
            goto rbac_render_users;
        }

        // Sync roles
        foreach ($role_ids as $rid) {
            if (!in_array($rid, $current_role_ids)) {
                Rbac::assign_role_to_user($id, $rid);
            }
        }
        foreach ($current_role_ids as $current_id) {
            if (!in_array($current_id, $role_ids)) {
                try {
                    Rbac::remove_role_from_user($id, $current_id);
                } catch (\RuntimeException $e) {
                    $form_error = yourls__('Error: ' . $e->getMessage());
                    goto rbac_render_users;
                }
            }
        }

        yourls_add_notice(yourls__('User updated.'));
        yourls_redirect(yourls_admin_url('plugins.php?page=rbac_users'), 302); // PRG on success
        exit();
    } else {
        if (empty($password)) {
            $form_error = yourls__('Password is required for new users.');
            $action = '';
        } else {
            try {
                $result = Rbac::create_user($username, $password, $email, $active, $role_ids);
                if ($result) {
                    yourls_add_notice(yourls__('User created.'));
                    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_users'), 302); // PRG on success
                    exit();
                }
                $form_error = yourls__('Failed to create user. (Username may already exist.)');
            } catch (\InvalidArgumentException $e) {
                $form_error = yourls__('Error: ' . $e->getMessage());
            }
            $action = ''; // error: re-render form with the notice in this request
        }
    }

    rbac_render_users:
}

if ($action === 'delete' && isset($_REQUEST['id'])) {
    $user_id = (int) $_REQUEST['id'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Destructive actions must be POSTed, not fetched.
        yourls_redirect(yourls_admin_url('plugins.php?page=rbac_users'), 302);
        exit();
    }
    yourls_verify_nonce('rbac_delete_user');

    if ($user_id <= 0) {
        yourls_add_notice(yourls__('Cannot delete this user.'));
    } else {
        $user = Rbac::get_user_by_id($user_id);
        $is_self = $user && defined('YOURLS_USER') && $user->username === YOURLS_USER;
        if ($is_self) {
            yourls_add_notice(yourls__('You cannot delete your own account.'));
        } elseif ($user) {
            try {
                $result = Rbac::delete_user($user_id);
                yourls_add_notice($result ? yourls_s('User "%s" deleted.', $user->username) : yourls__('Failed to delete user.'));
            } catch (\RuntimeException $e) {
                yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
            }
        }
    }
    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_users'), 302);
    exit();
}

$users = Rbac::get_all_users();
$all_roles = Rbac::get_all_roles();
?>

<div class="rbac-wrap">
    <h2><?php yourls_e('RBAC Users'); ?></h2>
    <p><?php yourls_e('Users are stored in the database and authenticated via the RBAC plugin.'); ?></p>

    <?php if ($form_error !== ''): ?>
    <div class="rbac-error" role="alert"><?php echo yourls_esc_html($form_error); ?></div>
    <?php endif; ?>

    <div class="rbac-card">
        <h3><?php echo $action === 'edit' ? yourls_e('Edit User') : yourls_e('Add New User'); ?></h3>
        <form method="post" action="" class="rbac-form">
            <?php yourls_nonce_field('rbac_save_user'); ?>
            <label for="rbac-username"><?php yourls_e('Username'); ?></label>
            <div class="rbac-field">
                <input type="text" id="rbac-username" name="rbac_username" value="<?php echo yourls_esc_attr($user_username); ?>" required />
            </div>
            <label for="rbac-password"><?php yourls_e('Password'); ?></label>
            <div class="rbac-field">
                <input type="password" id="rbac-password" name="rbac_password" autocomplete="new-password" data-rbac-keep="<?php echo $action === 'edit' ? '1' : '0'; ?>" />
                <?php if ($action === 'edit'): ?>
                    <span class="rbac-hint" style="margin-top:2px;"><?php yourls_e('Leave blank to keep current password.'); ?></span>
                <?php else: ?>
                    <span class="rbac-meter"><span></span></span><span class="rbac-meter-label"></span>
                <?php endif; ?>
            </div>
            <label for="rbac-email"><?php yourls_e('Email'); ?></label>
            <div class="rbac-field">
                <input type="email" id="rbac-email" name="rbac_email" value="<?php echo yourls_esc_attr($user_email); ?>" />
            </div>
            <span class="rbac-field-label"><?php yourls_e('Active'); ?></span>
            <div class="rbac-field">
                <?php // hidden 0 + checkbox 1: unchecked still posts 0 ?>
                <input type="hidden" name="rbac_active" value="0" />
                <label class="rbac-toggle">
                    <input type="checkbox" name="rbac_active" value="1" <?php echo $user_active == 1 ? 'checked="checked"' : ''; ?> />
                    <span class="rbac-track"></span>
                    <span class="rbac-toggle-label"><?php yourls_e('Active'); ?></span>
                </label>
            </div>
            <span class="rbac-field-label"><?php yourls_e('Roles'); ?></span>
            <div class="rbac-field">
                <?php if (empty($all_roles)): ?>
                    <p><?php yourls_e('No roles defined yet.'); ?></p>
                <?php else: ?>
                    <?php
                    $assigned_role_ids = [];
                    if ($action === 'edit' && $user_id > 0) {
                        $assigned_roles = Rbac::get_user_roles($user_id);
                        foreach ($assigned_roles as $ar) {
                            $assigned_role_ids[] = $ar->id;
                        }
                    }
                    ?>
                    <?php foreach ($all_roles as $r): ?>
                        <label style="display:inline-block;margin-right:10px;">
                            <input type="checkbox" name="rbac_role_ids[]" value="<?php echo $r->id; ?>" <?php echo in_array($r->id, $assigned_role_ids) ? 'checked="checked"' : ''; ?> />
                            <?php echo yourls_esc_html($r->name); ?> <small>(<?php echo yourls_esc_html($r->slug); ?>)</small>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <span></span>
            <div class="rbac-field">
                <input type="hidden" name="rbac_user_id" value="<?php echo $user_id; ?>" />
                <input type="submit" name="save_user" value="<?php yourls_e('Save User'); ?>" class="button primary" />
                <input type="button" value="<?php yourls_e('Cancel'); ?>" class="button" onclick="window.location.href='<?php echo yourls_admin_url('plugins.php?page=rbac_users'); ?>'" />
            </div>
        </form>
    </div>

    <div class="rbac-card">
        <div class="rbac-toolbar">
            <h3 style="border:none;margin:0;"><?php yourls_e('Existing Users'); ?></h3>
            <input type="search" class="rbac-search" placeholder="<?php yourls_e('Search users…'); ?>" data-rbac-target="#rbac-user-list" />
        </div>
        <?php if (empty($users)): ?>
            <p><?php yourls_e('No users defined.'); ?></p>
        <?php else: ?>
            <div class="rbac-list" id="rbac-user-list">
                <?php foreach ($users as $u): ?>
                    <?php
                    $user_roles = Rbac::get_user_roles($u->id);
                    $role_names = [];
                    foreach ($user_roles as $ur) {
                        $role_names[] = yourls_esc_html($ur->slug);
                    }
                    ?>
                    <div class="rbac-item">
                        <div class="rbac-item-main">
                            <span class="rbac-item-title"><?php echo yourls_esc_html($u->username); ?></span>
                            <span class="rbac-item-meta"><?php echo yourls_esc_html($u->email ?? ''); ?></span>
                            <?php foreach ($role_names as $rn): ?>
                                <span class="rbac-badge"><?php echo $rn; ?></span>
                            <?php endforeach; ?>
                            <?php if (!$u->active): ?>
                                <span class="rbac-badge rbac-badge-off"><?php yourls_e('inactive'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rbac-item-actions">
                            <a href="<?php echo yourls_admin_url('plugins.php?page=rbac_users&action=edit&id=' . $u->id); ?>" class="button"><?php yourls_e('Edit'); ?></a>
                            <form method="post" action="<?php echo yourls_esc_attr(yourls_admin_url('plugins.php?page=rbac_users&action=delete&id=' . $u->id)); ?>" data-rbac-confirm="<?php echo yourls_esc_attr(yourls__('Delete this user? This cannot be undone.')); ?>">
                                <?php yourls_nonce_field('rbac_delete_user'); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $u->id; ?>" />
                                <input type="hidden" name="action" value="delete" />
                                <input type="submit" value="<?php yourls_e('Delete'); ?>" class="button" style="background:#e74c3c;color:#fff;" />
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
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
