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
    <link rel="stylesheet" href="<?= h(url('assets/css/app.css')) ?>">
</head>
<body>
<main class="auth-shell">
    <section class="brand-panel">
        <div>
            <p class="eyebrow">CyberKavach Club</p>
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
