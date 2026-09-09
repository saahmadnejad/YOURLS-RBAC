<?php
// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

use YOURLS\RBAC\Rbac;

$action = in_array($_GET['action'] ?? '', ['edit', 'delete', ''], true) ? ($_GET['action'] ?? '') : '';
$form_error = '';
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
            $form_error = yourls__('Error: ' . $e->getMessage());
            goto rbac_render_permissions;
        }
    } else {
        try {
            $result = Rbac::create_permission($name, $slug, $desc);
            $msg = $result ? yourls__('Permission created.') : yourls__('Failed to create permission. (Slug may already exist.)');
        } catch (\InvalidArgumentException $e) {
            $form_error = yourls__('Error: ' . $e->getMessage());
            goto rbac_render_permissions;
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

rbac_render_permissions:
$permissions = Rbac::get_all_permissions();
?>

<div class="rbac-wrap">
    <h2><?php yourls_e('RBAC Permissions'); ?></h2>

    <?php if ($form_error !== ''): ?>
    <div class="rbac-error" role="alert"><?php echo yourls_esc_html($form_error); ?></div>
    <?php endif; ?>
    <p><?php yourls_e('Permissions define granular capabilities. Assign them to roles to control what users can do.'); ?></p>

    <div class="rbac-card">
        <h3><?php echo $action === 'edit' ? yourls_e('Edit Permission') : yourls_e('Add New Permission'); ?></h3>
        <form method="post" action="" class="rbac-form">
            <?php yourls_nonce_field('rbac_save_permission'); ?>
            <label for="perm-name"><?php yourls_e('Name'); ?></label>
            <div class="rbac-field">
                <input type="text" id="perm-name" name="perm_name" value="<?php echo yourls_esc_attr($perm_name); ?>" required />
            </div>
            <label for="perm-slug"><?php yourls_e('Slug'); ?></label>
            <div class="rbac-field">
                <input type="text" id="perm-slug" name="perm_slug" value="<?php echo yourls_esc_attr($perm_slug); ?>" required data-rbac-slug="1" />
                <span class="rbac-hint rbac-slug-hint" style="display:none;color:#e74c3c;"><?php yourls_e('Lowercase letters, numbers and underscores only.'); ?></span>
            </div>
            <label for="perm-desc"><?php yourls_e('Description'); ?></label>
            <div class="rbac-field">
                <input type="text" id="perm-desc" name="perm_desc" value="<?php echo yourls_esc_attr($perm_desc); ?>" />
            </div>
            <span></span>
            <div class="rbac-field">
                <input type="hidden" name="perm_id" value="<?php echo $perm_id; ?>" />
                <input type="submit" name="save_permission" value="<?php yourls_e('Save Permission'); ?>" class="button primary" />
                <input type="button" value="<?php yourls_e('Cancel'); ?>" class="button" onclick="window.location.href='<?php echo yourls_admin_url('plugins.php?page=rbac_permissions'); ?>'" />
            </div>
        </form>
    </div>

    <div class="rbac-card">
        <div class="rbac-toolbar">
            <h3 style="border:none;margin:0;"><?php yourls_e('Existing Permissions'); ?></h3>
            <input type="search" class="rbac-search" placeholder="<?php yourls_e('Search permissions…'); ?>" data-rbac-target="#rbac-perm-list" />
        </div>
        <?php if (empty($permissions)): ?>
            <p><?php yourls_e('No permissions defined.'); ?></p>
        <?php else: ?>
            <div class="rbac-list" id="rbac-perm-list">
                <?php foreach ($permissions as $p): ?>
                    <div class="rbac-item">
                        <div class="rbac-item-main">
                            <span class="rbac-item-title"><?php echo yourls_esc_html($p->name); ?></span>
                            <span class="rbac-badge"><?php echo yourls_esc_html($p->slug); ?></span>
                            <?php if (in_array($p->slug, Rbac::PROTECTED_PERMISSION_SLUGS, true)): ?>
                                <span class="rbac-badge rbac-badge-off"><?php yourls_e('protected'); ?></span>
                            <?php endif; ?>
                            <span class="rbac-item-meta"><?php echo yourls_esc_html($p->description ?? ''); ?></span>
                        </div>
                        <div class="rbac-item-actions">
                            <a href="<?php echo yourls_admin_url('plugins.php?page=rbac_permissions&action=edit&id=' . $p->id); ?>" class="button"><?php yourls_e('Edit'); ?></a>
                            <?php if (!in_array($p->slug, Rbac::PROTECTED_PERMISSION_SLUGS, true)): ?>
                                <form method="post" action="<?php echo yourls_esc_attr(yourls_admin_url('plugins.php?page=rbac_permissions&action=delete&id=' . $p->id)); ?>" data-rbac-confirm="<?php echo yourls_esc_attr(yourls__('Delete this permission? Roles holding it lose the capability.')); ?>">
                                    <?php yourls_nonce_field('rbac_delete_permission'); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $p->id; ?>" />
                                    <input type="hidden" name="action" value="delete" />
                                    <input type="submit" value="<?php yourls_e('Delete'); ?>" class="button" style="background:#e74c3c;color:#fff;" />
                                </form>
                            <?php endif; ?>
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
