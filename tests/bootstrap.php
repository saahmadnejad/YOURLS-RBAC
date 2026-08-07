<?php
/**
 * Test bootstrap: provides YOURLS stubs so the Rbac class can be loaded
 * and its validation methods tested without a full YOURLS installation.
 */

require __DIR__ . '/../vendor/autoload.php';

// YOURLS constants
if (!defined('YOURLS_DB_PREFIX')) {
    define('YOURLS_DB_PREFIX', 'yourls_');
}

// Stub class for YOURLS\Database\YDB return type resolution
if (!class_exists('YOURLS\Database\YDB')) {
    eval('namespace YOURLS\Database; class YDB {}');
}
