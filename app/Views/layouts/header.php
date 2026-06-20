<?php
/** @var string $title */
$title = $title ?? app_config('name');
$pageErrors = errors();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="<?= h(url('assets/css/app.css')) ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/css/app.css') ?>">
</head>
<body>
<main class="auth-shell">
    <section class="brand-panel">
        <div>
            <img src="<?= h(url('assets/images/logo.png')) ?>" alt="CyberKavach Logo" style="width: 360px; height: auto; margin-bottom: 2rem; display: block; margin-left: -5px;">
            <h1>Smart Club Management System</h1>
            <p>Secure role-based access for coordinators, members, and student participants.</p>
        </div>
    </section>
    <section class="form-panel">
        <?php if ($message = flash('success')): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($message = flash('error')): ?>
            <div class="alert alert-error"><?= h($message) ?></div>
        <?php endif; ?>
