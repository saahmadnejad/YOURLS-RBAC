<?php

namespace YOURLS\RBAC;

use YOURLS\Database\YDB;

class Rbac {

    public static function table_users(): string {
        return defined('YOURLS_RBAC_TABLE_USERS') ? \YOURLS_RBAC_TABLE_USERS : \YOURLS_DB_PREFIX . 'rbac_users';
    }

    public static function table_roles(): string {
        return defined('YOURLS_RBAC_TABLE_ROLES') ? \YOURLS_RBAC_TABLE_ROLES : \YOURLS_DB_PREFIX . 'rbac_roles';
    }

    public static function table_permissions(): string {
        return defined('YOURLS_RBAC_TABLE_PERMISSIONS') ? \YOURLS_RBAC_TABLE_PERMISSIONS : \YOURLS_DB_PREFIX . 'rbac_permissions';
    }

    public static function table_user_roles(): string {
        return defined('YOURLS_RBAC_TABLE_USER_ROLES') ? \YOURLS_RBAC_TABLE_USER_ROLES : \YOURLS_DB_PREFIX . 'rbac_user_roles';
    }

    public static function table_role_permissions(): string {
        return defined('YOURLS_RBAC_TABLE_ROLE_PERMISSIONS') ? \YOURLS_RBAC_TABLE_ROLE_PERMISSIONS : \YOURLS_DB_PREFIX . 'rbac_role_permissions';
    }

    public static function db($context = 'read-rbac'): YDB {
        return \yourls_get_db($context);
    }

    /**
     * Quote a database identifier (table name) with backticks.
     * This provides defense-in-depth against identifier injection.
     */
    public static function quote_identifier(string $identifier): string {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid database identifier');
        }
        return '`' . $identifier . '`';
    }

    /**
     * Validate a username. Returns the cleaned username or throws.
     */
    public static function validate_username(string $username): string {
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('Username cannot be empty');
        }
        if (strlen($username) > 100) {
            throw new \InvalidArgumentException('Username must be 100 characters or less');
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            throw new \InvalidArgumentException('Username can only contain letters, numbers, underscores, dots, and hyphens');
        }
        return $username;
    }

    /**
     * Validate an email address. Returns the cleaned email or throws.
     */
    public static function validate_email(string $email): string {
        $email = trim($email);
        if ($email === '') {
            return '';
        }
        if (strlen($email) > 255) {
            throw new \InvalidArgumentException('Email must be 255 characters or less');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
        return $email;
    }

    /**
     * Validate a slug. Returns the cleaned slug or throws.
     */
    public static function validate_slug(string $slug): string {
        $slug = trim($slug);
        if ($slug === '') {
            throw new \InvalidArgumentException('Slug cannot be empty');
        }
        if (strlen($slug) > 100) {
            throw new \InvalidArgumentException('Slug must be 100 characters or less');
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
            throw new \InvalidArgumentException('Slug can only contain lowercase letters, numbers, underscores, and hyphens');
        }
        return $slug;
    }

    /**
     * Validate a password. Returns the password or throws.
     */
    public static function validate_password(string $password): string {
        if ($password === '') {
            throw new \InvalidArgumentException('Password cannot be empty');
        }
        if (strlen($password) < 4) {
            throw new \InvalidArgumentException('Password must be at least 4 characters');
        }
        if (strlen($password) > 255) {
            throw new \InvalidArgumentException('Password must be 255 characters or less');
        }
        return $password;
    }

    /**
     * Validate a name (role or permission name). Returns the cleaned name or throws.
     */
    public static function validate_name(string $name): string {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        if (strlen($name) > 100) {
            throw new \InvalidArgumentException('Name must be 100 characters or less');
        }
        return $name;
    }

    /**
     * Validate a description. Returns the cleaned description or throws.
     */
    public static function validate_description(string $description): string {
        $description = trim($description);
        if (strlen($description) > 65535) {
            throw new \InvalidArgumentException('Description must be 65535 characters or less');
        }
        return $description;
    }

    /**
     * Check if a table exists in the database.
     */
    public static function tables_exist(): bool {
        $db = self::db('read-rbac-tables_exist');
        $table = self::table_users();
        $result = $db->fetchValue("SHOW TABLES LIKE :table", ['table' => $table]);
        return (bool) $result;
    }

    public static function create_tables(): array {
        $db = self::db('write-rbac-create_tables');
        $results = ['success' => [], 'error' => []];

        $tables = [
            self::table_users() =>
                "CREATE TABLE IF NOT EXISTS `" . self::table_users() . "` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `username` varchar(100) NOT NULL,
                    `password` varchar(255) NOT NULL,
                    `email` varchar(255) DEFAULT NULL,
                    `active` tinyint(1) NOT NULL DEFAULT 1,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `username` (`username`)
                ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            self::table_roles() =>
                "CREATE TABLE IF NOT EXISTS `" . self::table_roles() . "` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `slug` varchar(100) NOT NULL,
                    `description` text,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `slug` (`slug`)
                ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            self::table_permissions() =>
                "CREATE TABLE IF NOT EXISTS `" . self::table_permissions() . "` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `slug` varchar(100) NOT NULL,
                    `description` text,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `slug` (`slug`)
                ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            self::table_user_roles() =>
                "CREATE TABLE IF NOT EXISTS `" . self::table_user_roles() . "` (
                    `user_id` int(10) unsigned NOT NULL,
                    `role_id` int(10) unsigned NOT NULL,
                    PRIMARY KEY (`user_id`, `role_id`),
                    KEY `user_id` (`user_id`),
                    KEY `role_id` (`role_id`)
                ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            self::table_role_permissions() =>
                "CREATE TABLE IF NOT EXISTS `" . self::table_role_permissions() . "` (
                    `role_id` int(10) unsigned NOT NULL,
                    `permission_id` int(10) unsigned NOT NULL,
                    PRIMARY KEY (`role_id`, `permission_id`),
                    KEY `role_id` (`role_id`),
                    KEY `permission_id` (`permission_id`)
                ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        ];

        foreach ($tables as $table_name => $sql) {
            $db->perform($sql);
            $check = $db->fetchAffected("SHOW TABLES LIKE :table", ['table' => $table_name]);
            if ($check) {
                $results['success'][] = $table_name;
            } else {
                $results['error'][] = $table_name;
            }
        }

        return $results;
    }

    public static function drop_tables(): void {
        $db = self::db('write-rbac-drop_tables');
        $tables = [
            self::table_users(),
            self::table_roles(),
            self::table_permissions(),
            self::table_user_roles(),
            self::table_role_permissions(),
        ];
        foreach ($tables as $table) {
            $db->perform("DROP TABLE IF EXISTS `$table`");
        }
    }

    public static function seed_defaults(): void {
        $permissions = [
            ['name' => 'Access Admin',        'slug' => 'access_admin',       'description' => 'Can access the admin interface'],
            ['name' => 'Manage URLs',         'slug' => 'manage_urls',         'description' => 'Can add, edit, and delete short URLs'],
            ['name' => 'View Stats',          'slug' => 'view_stats',          'description' => 'Can view URL statistics and analytics'],
            ['name' => 'Manage Users',        'slug' => 'manage_users',        'description' => 'Can create, edit, and delete users'],
            ['name' => 'Manage Roles',        'slug' => 'manage_roles',        'description' => 'Can create, edit, and delete roles'],
            ['name' => 'Manage Permissions',  'slug' => 'manage_permissions',   'description' => 'Can manage role-permission assignments'],
            ['name' => 'Manage Plugins',      'slug' => 'manage_plugins',      'description' => 'Can activate and deactivate plugins'],
        ];

        $existing = self::get_all_permissions();
        $existing_slugs = array_column($existing, 'slug');

        foreach ($permissions as $perm) {
            if (!in_array($perm['slug'], $existing_slugs)) {
                self::create_permission($perm['name'], $perm['slug'], $perm['description']);
            }
        }

        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin',    'description' => 'Full access to all features'],
            ['name' => 'Manager',        'slug' => 'manager',  'description' => 'Manages URLs and views stats'],
            ['name' => 'Editor',         'slug' => 'editor',   'description' => 'Edits URLs and views stats'],
            ['name' => 'User',           'slug' => 'user',     'description' => 'Basic access to admin interface'],
        ];

        $existing_roles = self::get_all_roles();
        $existing_role_slugs = array_column($existing_roles, 'slug');

        foreach ($roles as $role) {
            if (!in_array($role['slug'], $existing_role_slugs)) {
                self::create_role($role['name'], $role['slug'], $role['description']);
            }
        }

        $admin_role = self::get_role_by_slug('admin');
        if ($admin_role) {
            $all_perms = self::get_all_permissions();
            foreach ($all_perms as $perm) {
                if (!self::role_has_permission($admin_role->id, $perm->id)) {
                    self::assign_permission_to_role($admin_role->id, $perm->id);
                }
            }
        }

        $manager_perms = ['access_admin', 'manage_urls', 'view_stats'];
        $manager_role = self::get_role_by_slug('manager');
        if ($manager_role) {
            self::sync_role_permissions($manager_role->id, $manager_perms);
        }

        $editor_perms = ['access_admin', 'manage_urls', 'view_stats'];
        $editor_role = self::get_role_by_slug('editor');
        if ($editor_role) {
            self::sync_role_permissions($editor_role->id, $editor_perms);
        }

        $user_perms = ['access_admin'];
        $user_role = self::get_role_by_slug('user');
        if ($user_role) {
            self::sync_role_permissions($user_role->id, $user_perms);
        }

        // Create default admin user from YOURLS_USER/YOURLS_PASSWD globals
        if (\defined('YOURLS_USER') && \YOURLS_USER !== '') {
            $admin_user = self::get_user_by_username(\YOURLS_USER);
            if (!$admin_user) {
                $admin_pass = \defined('YOURLS_PASSWD') ? \YOURLS_PASSWD : 'password123';
                self::create_user(\YOURLS_USER, $admin_pass, '', true);
                $new_user = self::get_user_by_username(\YOURLS_USER);
                if ($new_user && $admin_role) {
                    self::assign_role_to_user($new_user->id, $admin_role->id);
                }
            }
        }
    }

    private static function sync_role_permissions(int $role_id, array $permission_slugs): void {
        $db = self::db('write-rbac-sync_role_permissions');
        $db->fetchAffected("DELETE FROM `" . self::table_role_permissions() . "` WHERE `role_id` = :role_id", ['role_id' => $role_id]);

        foreach ($permission_slugs as $slug) {
            $perm = self::get_permission_by_slug($slug);
            if ($perm) {
                self::assign_permission_to_role($role_id, $perm->id);
            }
        }
    }

    public static function load_users_into_globals(): void {
        global $yourls_user_passwords;

        if (!self::tables_exist()) {
            return;
        }

        $db = self::db('read-rbac-load_users');
        $table = self::table_users();
        $results = $db->fetchObjects("SELECT `username`, `password` FROM `$table` WHERE `active` = 1");

        if (empty($results)) {
            return;
        }

        foreach ($results as $row) {
            $username = $row->username;
            $password = $row->password;

            if (!str_starts_with($password, 'phpass:') && !str_starts_with($password, 'md5:')) {
                $password = 'phpass:' . $password;
            }

            if (isset($yourls_user_passwords[$username])) {
                continue;
            }
            $yourls_user_passwords[$username] = $password;
        }
    }

    // --- User CRUD ---

    public static function get_user_by_id(int $id): ?object {
        $db = self::db('read-rbac-get_user_by_id');
        $table = self::table_users();
        return $db->fetchObject("SELECT * FROM `$table` WHERE `id` = :id", ['id' => $id]) ?: null;
    }

    public static function get_user_by_username(string $username): ?object {
        $db = self::db('read-rbac-get_user_by_username');
        $table = self::table_users();
        return $db->fetchObject("SELECT * FROM `$table` WHERE `username` = :username", ['username' => $username]) ?: null;
    }

    public static function get_all_users(): array {
        $db = self::db('read-rbac-get_all_users');
        $users_table = self::table_users();
        $user_roles_table = self::table_user_roles();
        $roles_table = self::table_roles();

        $sql = "SELECT u.*, GROUP_CONCAT(r.slug ORDER BY r.slug SEPARATOR ',') as role_slugs
                FROM `$users_table` u
                LEFT JOIN `$user_roles_table` ur ON u.`id` = ur.`user_id`
                LEFT JOIN `$roles_table` r ON ur.`role_id` = r.`id`
                GROUP BY u.`id`
                ORDER BY u.`username`";

        return $db->fetchObjects($sql);
    }

    public static function create_user(string $username, string $password, string $email = '', bool $active = true, array $role_ids = []): int|bool {
        $db = self::db('write-rbac-create_user');

        $username = self::validate_username($username);
        $password = self::validate_password($password);
        $email = self::validate_email($email);

        if (self::get_user_by_username($username)) {
            return false;
        }

        $hash = 'phpass:' . \yourls_phpass_hash($password);

        $table = self::table_users();
        $active_int = $active ? 1 : 0;

        $db->fetchAffected(
            "INSERT INTO `$table` (`username`, `password`, `email`, `active`) VALUES (:username, :password, :email, :active)",
            [
                'username' => $username,
                'password' => $hash,
                'email'    => $email,
                'active'   => $active_int,
            ]
        );

        $user_id = (int) $db->fetchValue("SELECT `id` FROM `$table` WHERE `username` = :username", ['username' => $username]);

        if ($user_id) {
            foreach ($role_ids as $role_id) {
                self::assign_role_to_user($user_id, $role_id);
            }
        }

        return $user_id;
    }

    public static function update_user(int $id, array $data): bool {
        $db = self::db('write-rbac-update_user');
        $table = self::table_users();

        $fields = [];
        $binds = ['id' => $id];

        if (isset($data['username'])) {
            $username = self::validate_username($data['username']);
            $fields[] = '`username` = :username';
            $binds['username'] = $username;
        }
        if (isset($data['email'])) {
            $email = self::validate_email($data['email']);
            $fields[] = '`email` = :email';
            $binds['email'] = $email;
        }
        if (isset($data['active'])) {
            $fields[] = '`active` = :active';
            $binds['active'] = (int) $data['active'];
        }
        if (isset($data['password'])) {
            $password = self::validate_password($data['password']);
            $hash = 'phpass:' . \yourls_phpass_hash($password);
            $fields[] = '`password` = :password';
            $binds['password'] = $hash;
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = '`updated_at` = CURRENT_TIMESTAMP';

        $result = $db->fetchAffected(
            "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE `id` = :id",
            $binds
        );

        return $result > 0;
    }

    public static function delete_user(int $id): bool {
        $db = self::db('write-rbac-delete_user');
        $users_table = self::table_users();

        $username = $db->fetchValue("SELECT `username` FROM `$users_table` WHERE `id` = :id", ['id' => $id]);
        if (!$username) {
            return false;
        }

        $db->fetchAffected("DELETE FROM `" . self::table_user_roles() . "` WHERE `user_id` = :id", ['id' => $id]);
        $result = $db->fetchAffected("DELETE FROM `$users_table` WHERE `id` = :id", ['id' => $id]);

        \yourls_do_action('rbac_user_deleted', $id, $username);

        return $result > 0;
    }

    // --- Role CRUD ---

    public static function get_role_by_id(int $id): ?object {
        $db = self::db('read-rbac-get_role_by_id');
        $table = self::table_roles();
        return $db->fetchObject("SELECT * FROM `$table` WHERE `id` = :id", ['id' => $id]) ?: null;
    }

    public static function get_role_by_slug(string $slug): ?object {
        $db = self::db('read-rbac-get_role_by_slug');
        $table = self::table_roles();
        return $db->fetchObject("SELECT * FROM `$table` WHERE `slug` = :slug", ['slug' => $slug]) ?: null;
    }

    public static function get_all_roles(): array {
        $db = self::db('read-rbac-get_all_roles');
        $roles_table = self::table_roles();
        $role_perms_table = self::table_role_permissions();
        $perms_table = self::table_permissions();

        $sql = "SELECT r.*, GROUP_CONCAT(p.slug ORDER BY p.slug SEPARATOR ',') as permission_slugs
                FROM `$roles_table` r
                LEFT JOIN `$role_perms_table` rp ON r.`id` = rp.`role_id`
                LEFT JOIN `$perms_table` p ON rp.`permission_id` = p.`id`
                GROUP BY r.`id`
                ORDER BY r.`id`";

        return $db->fetchObjects($sql);
    }

    public static function create_role(string $name, string $slug, string $description = ''): int|bool {
        $db = self::db('write-rbac-create_role');
        $table = self::table_roles();

        $name = self::validate_name($name);
        $slug = self::validate_slug($slug);
        $description = self::validate_description($description);

        if (self::get_role_by_slug($slug)) {
            return false;
        }

        $db->fetchAffected(
            "INSERT INTO `$table` (`name`, `slug`, `description`) VALUES (:name, :slug, :description)",
            [
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
            ]
        );

        return (int) $db->fetchValue("SELECT `id` FROM `$table` WHERE `slug` = :slug", ['slug' => $slug]);
    }

    public static function update_role(int $id, string $name, string $slug, string $description = ''): bool {
        $db = self::db('write-rbac-update_role');
        $table = self::table_roles();

        $name = self::validate_name($name);
        $slug = self::validate_slug($slug);
        $description = self::validate_description($description);

        $result = $db->fetchAffected(
            "UPDATE `$table` SET `name` = :name, `slug` = :slug, `description` = :description WHERE `id` = :id",
            [
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
                'id'          => $id,
            ]
        );

        return $result > 0;
    }

    public static function delete_role(int $id): bool {
        $db = self::db('write-rbac-delete_role');
        $roles_table = self::table_roles();

        $slug = $db->fetchValue("SELECT `slug` FROM `$roles_table` WHERE `id` = :id", ['id' => $id]);
        if (!$slug) {
            return false;
        }

        $db->fetchAffected("DELETE FROM `" . self::table_role_permissions() . "` WHERE `role_id` = :id", ['id' => $id]);
        $db->fetchAffected("DELETE FROM `" . self::table_user_roles() . "` WHERE `role_id` = :id", ['id' => $id]);
        $result = $db->fetchAffected("DELETE FROM `$roles_table` WHERE `id` = :id", ['id' => $id]);

        \yourls_do_action('rbac_role_deleted', $id, $slug);

        return $result > 0;
    }

    // --- Permission CRUD ---

    public static function get_permission_by_id(int $id): ?object {
        $db = self::db('read-rbac-get_permission_by_id');
        $table = self::table_permissions();
        return $db->fetchObject("SELECT * FROM `$table` WHERE `id` = :id", ['id' => $id]) ?: null;
    }

    public static function get_permission_by_slug(string $slug): ?object {
        $db = self::db('read-rbac-get_permission_by_slug');
        $table = self::table_permissions();
        return $db->fetchObject("SELECT * FROM `$table` WHERE `slug` = :slug", ['slug' => $slug]) ?: null;
    }

    public static function get_all_permissions(): array {
        $db = self::db('read-rbac-get_all_permissions');
        $table = self::table_permissions();
        return $db->fetchObjects("SELECT * FROM `$table` ORDER BY `name`");
    }

    public static function create_permission(string $name, string $slug, string $description = ''): int|bool {
        $db = self::db('write-rbac-create_permission');
        $table = self::table_permissions();

        $name = self::validate_name($name);
        $slug = self::validate_slug($slug);
        $description = self::validate_description($description);

        if (self::get_permission_by_slug($slug)) {
            return false;
        }

        $db->fetchAffected(
            "INSERT INTO `$table` (`name`, `slug`, `description`) VALUES (:name, :slug, :description)",
            [
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
            ]
        );

        return (int) $db->fetchValue("SELECT `id` FROM `$table` WHERE `slug` = :slug", ['slug' => $slug]);
    }

    public static function update_permission(int $id, string $name, string $slug, string $description = ''): bool {
        $db = self::db('write-rbac-update_permission');
        $table = self::table_permissions();

        $name = self::validate_name($name);
        $slug = self::validate_slug($slug);
        $description = self::validate_description($description);

        $result = $db->fetchAffected(
            "UPDATE `$table` SET `name` = :name, `slug` = :slug, `description` = :description WHERE `id` = :id",
            [
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
                'id'          => $id,
            ]
        );

        return $result > 0;
    }

    public static function delete_permission(int $id): bool {
        $db = self::db('write-rbac-delete_permission');
        $perms_table = self::table_permissions();

        $slug = $db->fetchValue("SELECT `slug` FROM `$perms_table` WHERE `id` = :id", ['id' => $id]);
        if (!$slug) {
            return false;
        }

        $db->fetchAffected("DELETE FROM `" . self::table_role_permissions() . "` WHERE `permission_id` = :id", ['id' => $id]);
        $result = $db->fetchAffected("DELETE FROM `$perms_table` WHERE `id` = :id", ['id' => $id]);

        \yourls_do_action('rbac_permission_deleted', $id, $slug);

        return $result > 0;
    }

    // --- Role Assignment ---

    public static function assign_role_to_user(int $user_id, int $role_id): bool {
        $db = self::db('write-rbac-assign_role_to_user');
        $table = self::table_user_roles();

        $existing = $db->fetchValue(
            "SELECT COUNT(*) FROM `$table` WHERE `user_id` = :user_id AND `role_id` = :role_id",
            ['user_id' => $user_id, 'role_id' => $role_id]
        );

        if ($existing > 0) {
            return true;
        }

        $result = $db->fetchAffected(
            "INSERT INTO `$table` (`user_id`, `role_id`) VALUES (:user_id, :role_id)",
            ['user_id' => $user_id, 'role_id' => $role_id]
        );

        return $result > 0;
    }

    public static function remove_role_from_user(int $user_id, int $role_id): bool {
        $db = self::db('write-rbac-remove_role_from_user');
        $table = self::table_user_roles();

        $result = $db->fetchAffected(
            "DELETE FROM `$table` WHERE `user_id` = :user_id AND `role_id` = :role_id",
            ['user_id' => $user_id, 'role_id' => $role_id]
        );

        return $result >= 0;
    }

    // --- Permission Assignment ---

    public static function assign_permission_to_role(int $role_id, int $permission_id): bool {
        $db = self::db('write-rbac-assign_permission_to_role');
        $table = self::table_role_permissions();

        $existing = $db->fetchValue(
            "SELECT COUNT(*) FROM `$table` WHERE `role_id` = :role_id AND `permission_id` = :permission_id",
            ['role_id' => $role_id, 'permission_id' => $permission_id]
        );

        if ($existing > 0) {
            return true;
        }

        $result = $db->fetchAffected(
            "INSERT INTO `$table` (`role_id`, `permission_id`) VALUES (:role_id, :permission_id)",
            ['role_id' => $role_id, 'permission_id' => $permission_id]
        );

        return $result > 0;
    }

    public static function remove_permission_from_role(int $role_id, int $permission_id): bool {
        $db = self::db('write-rbac-remove_permission_from_role');
        $table = self::table_role_permissions();

        $result = $db->fetchAffected(
            "DELETE FROM `$table` WHERE `role_id` = :role_id AND `permission_id` = :permission_id",
            ['role_id' => $role_id, 'permission_id' => $permission_id]
        );

        return $result >= 0;
    }

    // --- Queries ---

    public static function get_user_roles(int $user_id): array {
        $db = self::db('read-rbac-get_user_roles');
        $user_roles_table = self::table_user_roles();
        $roles_table = self::table_roles();

        $sql = "SELECT r.* FROM `$roles_table` r
                JOIN `$user_roles_table` ur ON r.`id` = ur.`role_id`
                WHERE ur.`user_id` = :user_id
                ORDER BY r.`name`";

        return $db->fetchObjects($sql, ['user_id' => $user_id]);
    }

    public static function get_role_permissions(int $role_id): array {
        $db = self::db('read-rbac-get_role_permissions');
        $role_perms_table = self::table_role_permissions();
        $perms_table = self::table_permissions();

        $sql = "SELECT p.* FROM `$perms_table` p
                JOIN `$role_perms_table` rp ON p.`id` = rp.`permission_id`
                WHERE rp.`role_id` = :role_id
                ORDER BY p.`name`";

        return $db->fetchObjects($sql, ['role_id' => $role_id]);
    }

    public static function role_has_permission(int $role_id, int $permission_id): bool {
        $db = self::db('read-rbac-role_has_permission');
        $table = self::table_role_permissions();

        $result = $db->fetchValue(
            "SELECT COUNT(*) FROM `$table` WHERE `role_id` = :role_id AND `permission_id` = :permission_id",
            ['role_id' => $role_id, 'permission_id' => $permission_id]
        );

        return (int) $result > 0;
    }

    public static function user_has_permission(int $user_id, string $permission_slug): bool {
        $db = self::db('read-rbac-user_has_permission');
        $user_roles_table = self::table_user_roles();
        $roles_table = self::table_roles();
        $role_perms_table = self::table_role_permissions();
        $perms_table = self::table_permissions();

        $sql = "SELECT COUNT(*) FROM `$perms_table` p
                JOIN `$role_perms_table` rp ON p.`id` = rp.`permission_id`
                JOIN `$roles_table` r ON rp.`role_id` = r.`id`
                JOIN `$user_roles_table` ur ON r.`id` = ur.`role_id`
                WHERE ur.`user_id` = :user_id AND p.`slug` = :slug";

        $result = $db->fetchValue($sql, ['user_id' => $user_id, 'slug' => $permission_slug]);

        return (int) $result > 0;
    }

    public static function user_has_role(int $user_id, string $role_slug): bool {
        $db = self::db('read-rbac-user_has_role');
        $user_roles_table = self::table_user_roles();
        $roles_table = self::table_roles();

        $sql = "SELECT COUNT(*) FROM `$roles_table` r
                JOIN `$user_roles_table` ur ON r.`id` = ur.`role_id`
                WHERE ur.`user_id` = :user_id AND r.`slug` = :slug";

        $result = $db->fetchValue($sql, ['user_id' => $user_id, 'slug' => $role_slug]);

        return (int) $result > 0;
    }

    public static function get_current_user(): ?object {
        if (!defined('YOURLS_USER') || \YOURLS_USER === '') {
            return null;
        }
        return self::get_user_by_username(\YOURLS_USER);
    }

    public static function current_user_can(string $permission_slug): bool {
        $user = self::get_current_user();
        if (!$user) {
            return false;
        }
        return self::user_has_permission($user->id, $permission_slug);
    }

    public static function current_user_has_role(string $role_slug): bool {
        $user = self::get_current_user();
        if (!$user) {
            return false;
        }
        return self::user_has_role($user->id, $role_slug);
    }
}
