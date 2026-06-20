<?php
/** @var string $title */
/** @var array $user */
/** @var array $navItems */
$title = $title ?? app_config('name');
$navItems = $navItems ?? [];
$pageErrors = errors();
$unreadCount = Notification::countUnreadForUser((int) $user['id']);
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
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="<?= h(url('assets/images/logo.png')) ?>" alt="CyberKavach Logo" style="width: 100%; max-width: 180px; height: auto; margin-bottom: 0.5rem; display: block;">
            <strong><?= h($user['role_name']) ?></strong>
        </div>
        <nav class="side-nav" aria-label="Role navigation">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= h(url($item['href'])) ?>"><?= h($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div>
                <p class="eyebrow">Smart Club Management System</p>
                <h1><?= h($title) ?></h1>
            </div>
            <div class="topbar-actions">
                <span class="notification-pill"><?= h((string) $unreadCount) ?> unread</span>
                <span><?= h($user['full_name']) ?></span>
                <a href="<?= h(url('logout.php')) ?>">Logout</a>
            </div>
        </header>

        <?php if ($message = flash('success')): ?>
            <div class="alert alert-success alert-wide"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($message = flash('error')): ?>
            <div class="alert alert-error alert-wide"><?= h($message) ?></div>
        <?php endif; ?>

        <main class="content-area">
