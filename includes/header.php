<?php
/**
 * includes/header.php
 * Expects optional: $pageTitle, $pageDescription.
 */
$pageTitle       = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'A secure PHP and MySQL portal with authentication, CRUD and reports.';
$theme           = $_COOKIE['theme'] ?? 'light';
if (!in_array($theme, $ALLOWED_THEMES, true)) {
    $theme = 'light';
}
$scheme  = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$ogImage = $scheme . '://' . $host . url('/assets/img/og-image.svg');
$flash   = getFlash();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(url('/index.php')) ?>">
        <img src="<?= e(url('/assets/img/logo.svg')) ?>" alt="PHP Portal logo" width="32" height="32">
        <span><?= e(APP_NAME) ?></span>
    </a>
    <?php include __DIR__ . '/nav.php'; ?>
</header>
<main class="container">
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
<?php endif; ?>
