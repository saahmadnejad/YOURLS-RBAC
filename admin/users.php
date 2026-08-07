<?php
// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

use YOURLS\RBAC\Rbac;

$action = in_array($_GET['action'] ?? '', ['edit', 'delete', ''], true) ? ($_GET['action'] ?? '') : '';
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

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $active = (int) ($_POST['active'] ?? 1);
    $id = (int) ($_POST['user_id'] ?? 0);
    $role_ids = array_map('intval', $_POST['role_ids'] ?? []);

    if ($id > 0) {
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
        } catch (\InvalidArgumentException $e) {
            yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
            yourls_redirect(yourls_admin_url('plugins.php?page=rbac_users'), 302);
            exit();
        }

        // Sync roles
        $current_roles = Rbac::get_user_roles($id);
        $current_role_ids = array_map(fn($r) => $r->id, $current_roles);

        foreach ($role_ids as $rid) {
            if (!in_array($rid, $current_role_ids)) {
                Rbac::assign_role_to_user($id, $rid);
            }
        }
        foreach ($current_role_ids as $current_id) {
            if (!in_array($current_id, $role_ids)) {
                Rbac::remove_role_from_user($id, $current_id);
            }
        }

        yourls_add_notice(yourls__('User updated.'));
    } else {
        if (empty($password)) {
            yourls_add_notice(yourls__('Password is required for new users.'));
        } else {
            try {
                $result = Rbac::create_user($username, $password, $email, $active, $role_ids);
                if ($result) {
                    yourls_add_notice(yourls__('User created.'));
                } else {
                    yourls_add_notice(yourls__('Failed to create user. (Username may already exist.)'));
                }
            } catch (\InvalidArgumentException $e) {
                yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
            }
        }
    }

    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_users'), 302);
    exit();
}

if ($action === 'delete' && isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    if ($user_id > 0 && $user_id !== 1) {
        yourls_verify_nonce('rbac_delete_user');

        $user = Rbac::get_user_by_id($user_id);
        if ($user) {
            $result = Rbac::delete_user($user_id);
            yourls_add_notice($result ? yourls_s('User "%s" deleted.', $user->username) : yourls__('Failed to delete user.'));
        }
    } elseif ($user_id <= 0 || $user_id === 1) {
        yourls_add_notice(yourls__('Cannot delete this user.'));
    }
    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_users'), 302);
    exit();
}

$users = Rbac::get_all_users();
$all_roles = Rbac::get_all_roles();
?>

<h2><?php yourls_e('RBAC Users'); ?></h2>
<p><?php yourls_e('Users are stored in the database and authenticated via the RBAC plugin.'); ?></p>

<h3><?php echo $action === 'edit' ? yourls_e('Edit User') : yourls_e('Add New User'); ?></h3>

<form method="post" action="">
    <?php yourls_nonce_field('rbac_save_user'); ?>
    <table class="tblSorter" cellpadding="0" cellspacing="1">
        <tbody>
            <tr>
                <th><?php yourls_e('Username'); ?></th>
                <td><input type="text" name="username" class="text" size="40" value="<?php echo yourls_esc_attr($user_username); ?>" required /></td>
            </tr>
            <tr>
                <th><?php yourls_e('Password'); ?></th>
                <td>
                    <input type="password" name="password" class="text" size="40" autocomplete="new-password" />
                    <?php if ($action === 'edit'): ?>
                        <br/><small><?php yourls_e('Leave blank to keep current password.'); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php yourls_e('Email'); ?></th>
                <td><input type="email" name="email" class="text" size="60" value="<?php echo yourls_esc_attr($user_email); ?>" /></td>
            </tr>
            <tr>
                <th><?php yourls_e('Active'); ?></th>
                <td>
                    <label><input type="radio" name="active" value="1" <?php echo $user_active == 1 ? 'checked="checked"' : ''; ?> /> <?php yourls_e('Yes'); ?></label>
                    <label><input type="radio" name="active" value="0" <?php echo $user_active == 0 ? 'checked="checked"' : ''; ?> /> <?php yourls_e('No'); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php yourls_e('Roles'); ?></th>
                <td>
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
                                <input type="checkbox" name="role_ids[]" value="<?php echo $r->id; ?>" <?php echo in_array($r->id, $assigned_role_ids) ? 'checked="checked"' : ''; ?> />
                                <?php echo yourls_esc_html($r->name); ?> <small>(<?php echo yourls_esc_html($r->slug); ?>)</small>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>&nbsp;</th>
                <td>
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>" />
                    <input type="submit" name="save_user" value="<?php yourls_e('Save User'); ?>" class="button primary" />
                    <input type="button" value="<?php yourls_e('Cancel'); ?>" class="button" onclick="window.location.href='<?php echo yourls_admin_url('plugins.php?page=rbac_users'); ?>'" />
                </td>
            </tr>
        </tbody>
    </table>
</form>

<h3><?php yourls_e('Existing Users'); ?></h3>

<?php if (empty($users)): ?>
<p><?php yourls_e('No users defined.'); ?></p>
<?php else: ?>
<table class="tblSorter" cellpadding="0" cellspacing="1">
    <thead>
        <tr>
            <th><?php yourls_e('Username'); ?></th>
            <th><?php yourls_e('Email'); ?></th>
            <th><?php yourls_e('Active'); ?></th>
            <th><?php yourls_e('Roles'); ?></th>
            <th><?php yourls_e('Actions'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?php echo yourls_esc_html($u->username); ?></td>
            <td><?php echo yourls_esc_html($u->email ?? ''); ?></td>
            <td><?php echo $u->active ? yourls_e('Yes') : yourls_e('No'); ?></td>
            <td>
                <?php
                $user_roles = Rbac::get_user_roles($u->id);
                $role_names = [];
                foreach ($user_roles as $ur) {
                    $role_names[] = yourls_esc_html($ur->slug);
                }
                echo implode(', ', $role_names);
                ?>
            </td>
            <td>
                <a href="<?php echo yourls_admin_url('plugins.php?page=rbac_users&action=edit&id=' . $u->id); ?>" class="button"><?php yourls_e('Edit'); ?></a>
                <a href="<?php echo yourls_nonce_url('rbac_delete_user', yourls_add_query_arg(['page' => 'rbac_users', 'action' => 'delete', 'id' => $u->id], yourls_admin_url('plugins.php'))); ?>" class="button" onclick="return confirm('<?php yourls_e('Are you sure?'); ?>');" style="background:#e74c3c;color:#fff;"><?php yourls_e('Delete'); ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
