#!/bin/bash
set -e

YOURLS_DIR=/var/www/html

# Generate YOURLS config from environment variables
COOKIE_KEY="${YOURLS_COOKIE_KEY:-$(php -r 'echo bin2hex(random_bytes(20));')}"

cat > "$YOURLS_DIR/user/config.php" <<PHP
<?php
// Database settings
define('YOURLS_DB_HOST', getenv('YOURLS_DB_HOST') ?: 'db:3306');
define('YOURLS_DB_USER', getenv('YOURLS_DB_USER') ?: 'yourls');
define('YOURLS_DB_PASS', getenv('YOURLS_DB_PASS') ?: 'yourls');
define('YOURLS_DB_NAME', getenv('YOURLS_DB_NAME') ?: 'yourls');

// YOURLS core settings
define('YOURLS_DB_PREFIX', getenv('YOURLS_DB_PREFIX') ?: 'yourls_');
define('YOURLS_SITE', getenv('YOURLS_SITE') ?: 'http://localhost:8080');
define('YOURLS_HOURS_OFFSET', getenv('YOURLS_HOURS_OFFSET') ?: 0);
define('YOURLS_LANGUAGES', '');
define('YOURLS_UNIQUE_HIGH_ORDER_BITS', 8);
define('YOURLS_SHORTURL_CONVERT', 36);

// Cookie security key (random by default)
define('YOURLS_COOKIEKEY', '$COOKIE_KEY');

// Access control
define('YOURLS_PRIVATE', getenv('YOURLS_PRIVATE') ?: 'true');
define('YOURLS_USER', getenv('YOURLS_USER') ?: 'admin');
define('YOURLS_PASSWD', getenv('YOURLS_PASSWD') ?: 'password123');

// User passwords array (YOURLS uses this for authentication, not the constants above)
\$user = getenv('YOURLS_USER') ?: 'admin';
\$pass = getenv('YOURLS_PASSWD') ?: 'password123';
\$yourls_user_passwords = array(\$user => \$pass);

// Debug
define('YOURLS_DEBUG', false);

// Skip version check (fails without internet access)
define('YOURLS_NO_VERSION_CHECK', true);
PHP

# Wait for database to be ready
echo "Waiting for database..."
for i in $(seq 1 30); do
    if php -r "
        try {
            \$pdo = new PDO(
                'mysql:host=' . getenv('YOURLS_DB_HOST') . ';dbname=' . getenv('YOURLS_DB_NAME'),
                getenv('YOURLS_DB_USER'),
                getenv('YOURLS_DB_PASS'),
                [PDO::ATTR_TIMEOUT => 1, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo 'OK';
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "Database is ready!"
        break
    fi
    echo "Retrying ($i/30)..."
    sleep 2
done

# Fix permissions on non-mounted directories only
chown www-data:www-data "$YOURLS_DIR/user/config.php" 2>/dev/null || true
chown -R www-data:www-data "$YOURLS_DIR/temp" 2>/dev/null || true
chown -R www-data:www-data "$YOURLS_DIR/images" 2>/dev/null || true

# Start Apache
exec apache2-foreground
