<?php
// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

use YOURLS\RBAC\Rbac;

// Handle form submissions
$action = in_array($_GET['action'] ?? '', ['edit', 'delete', ''], true) ? ($_GET['action'] ?? '') : '';
$perm_id = 0;
$perm_name = '';
$perm_slug = '';
$perm_desc = '';

if ($action === 'edit' && isset($_GET['id'])) {
    $perm_id = (int) $_GET['id'];
    $perm = Rbac::get_permission_by_id($perm_id);
    if ($perm) {
        $perm_name = $perm->name;
        $perm_slug = $perm->slug;
        $perm_desc = $perm->description ?? '';
    } else {
        $action = '';
    }
}

if (isset($_POST['save_permission'])) {
    yourls_verify_nonce('rbac_save_permission');

    $name = trim($_POST['perm_name'] ?? '');
    $slug = trim($_POST['perm_slug'] ?? '');
    $desc = trim($_POST['perm_desc'] ?? '');
    $id = (int) ($_POST['perm_id'] ?? 0);

    if ($id > 0) {
        try {
            $result = Rbac::update_permission($id, $name, $slug, $desc);
            $msg = $result ? yourls__('Permission updated.') : yourls__('Failed to update permission.');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
            yourls_redirect(yourls_admin_url('plugins.php?page=rbac_permissions'), 302);
            exit();
        }
    } else {
        try {
            $result = Rbac::create_permission($name, $slug, $desc);
            $msg = $result ? yourls__('Permission created.') : yourls__('Failed to create permission. (Slug may already exist.)');
        } catch (\InvalidArgumentException $e) {
            yourls_add_notice(yourls__('Error: ' . $e->getMessage()));
            yourls_redirect(yourls_admin_url('plugins.php?page=rbac_permissions'), 302);
            exit();
        }
    }
    yourls_add_notice($msg);
    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_permissions'), 302);
    exit();
}

if ($action === 'delete' && isset($_REQUEST['id'])) {
    $perm_id = (int) $_REQUEST['id'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Destructive actions must be POSTed, not fetched.
        yourls_redirect(yourls_admin_url('plugins.php?page=rbac_permissions'), 302);
        exit();
    }
    yourls_verify_nonce('rbac_delete_permission');
    if ($perm_id > 0) {
        $perm = Rbac::get_permission_by_id($perm_id);
        if ($perm) {
            try {
                $result = Rbac::delete_permission($perm_id);
                $msg = $result ? yourls_s('Permission "%s" deleted.', $perm->name) : yourls__('Failed to delete permission.');
            } catch (\RuntimeException $e) {
                $msg = yourls__('Error: ' . $e->getMessage());
            }
            yourls_add_notice($msg);
        }
    }
    yourls_redirect(yourls_admin_url('plugins.php?page=rbac_permissions'), 302);
    exit();
}

$permissions = Rbac::get_all_permissions();
?>

<h2><?php yourls_e('RBAC Permissions'); ?></h2>
<p><?php yourls_e('Permissions define granular capabilities. Assign them to roles to control what users can do.'); ?></p>

<?php if ($action === 'edit'): ?>
<h3><?php yourls_e('Edit Permission'); ?></h3>
<?php else: ?>
<h3><?php yourls_e('Add New Permission'); ?></h3>
<?php endif; ?>

<form method="post" action="">
    <?php yourls_nonce_field('rbac_save_permission'); ?>
    <table class="tblSorter" cellpadding="0" cellspacing="1">
        <tbody>
            <tr>
                <th><?php yourls_e('Name'); ?></th>
                <td><input type="text" name="perm_name" class="text" size="40" value="<?php echo yourls_esc_attr($perm_name); ?>" required /></td>
            </tr>
            <tr>
                <th><?php yourls_e('Slug'); ?></th>
                <td><input type="text" name="perm_slug" class="text" size="40" value="<?php echo yourls_esc_attr($perm_slug); ?>" required /><br/><small><?php yourls_e('Lowercase, letters, numbers, underscores'); ?></small></td>
            </tr>
            <tr>
                <th><?php yourls_e('Description'); ?></th>
                <td><input type="text" name="perm_desc" class="text" size="60" value="<?php echo yourls_esc_attr($perm_desc); ?>" /></td>
            </tr>
            <tr>
                <th>&nbsp;</th>
                <td>
                    <input type="hidden" name="perm_id" value="<?php echo $perm_id; ?>" />
                    <input type="submit" name="save_permission" value="<?php yourls_e('Save Permission'); ?>" class="button primary" />
                    <input type="button" value="<?php yourls_e('Cancel'); ?>" class="button" onclick="window.location.href='<?php echo yourls_admin_url('plugins.php?page=rbac_permissions'); ?>'" />
                </td>
            </tr>
        </tbody>
    </table>
</form>

<h3><?php yourls_e('Existing Permissions'); ?></h3>

<?php if (empty($permissions)): ?>
<p><?php yourls_e('No permissions defined.'); ?></p>
<?php else: ?>
<table class="tblSorter" cellpadding="0" cellspacing="1">
    <thead>
        <tr>
            <th><?php yourls_e('Name'); ?></th>
            <th><?php yourls_e('Slug'); ?></th>
            <th><?php yourls_e('Description'); ?></th>
            <th><?php yourls_e('Actions'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($permissions as $p): ?>
        <tr>
            <td><?php echo yourls_esc_html($p->name); ?></td>
            <td><?php echo yourls_esc_html($p->slug); ?></td>
            <td><?php echo yourls_esc_html($p->description ?? ''); ?></td>
            <td>
                <a href="<?php echo yourls_admin_url('plugins.php?page=rbac_permissions&action=edit&id=' . $p->id); ?>" class="button"><?php yourls_e('Edit'); ?></a>
                <form method="post" action="<?php echo yourls_esc_attr(yourls_admin_url('plugins.php?page=rbac_permissions&action=delete&id=' . $p->id)); ?>" style="display:inline;" onsubmit="return confirm('<?php yourls_e('Are you sure?'); ?>');">
                    <?php yourls_nonce_field('rbac_delete_permission'); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $p->id; ?>" />
                    <input type="hidden" name="action" value="delete" />
                    <input type="submit" value="<?php yourls_e('Delete'); ?>" class="button" style="background:#e74c3c;color:#fff;" />
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
