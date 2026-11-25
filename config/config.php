<?php
/**
 * config/config.php
 * Application-wide constants and the multidimensional subject array.
 */

define('APP_NAME', 'PHP Portal');
define('PER_PAGE', 5);                          // rows per page in the Submissions Directory
define('LOG_FILE', __DIR__ . '/../logs/activity.log');
define('REMEMBER_COOKIE', 'last_username');     // non-sensitive cookie (username only)
define('REMEMBER_DAYS', 30);

// Auto-detect the base URL (e.g. "/ip_project" on XAMPP, "" on a hosted root).
// If links break on your host, set it manually: define('BASE_URL', '/your-folder');
$__root = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
$__doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
define('BASE_URL', rtrim(str_replace($__doc, '', $__root), '/'));

// Multidimensional array: subject categories (id + label), used by the Contact form.
$SUBJECT_CATEGORIES = [
    ['id' => 1, 'label' => 'General Enquiry'],
    ['id' => 2, 'label' => 'Technical Support'],
    ['id' => 3, 'label' => 'Feedback'],
    ['id' => 4, 'label' => 'Bug Report'],
    ['id' => 5, 'label' => 'Partnership'],
];

// Allowed theme values for the theme-preference cookie.
$ALLOWED_THEMES = ['light', 'dark'];
