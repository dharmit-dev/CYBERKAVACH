<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();

if (request_method_is('POST')) {
    verify_csrf();
    $action = input_string('action');

    if ($action === 'mark_all_read') {
        Notification::markAllRead((int) $user['id']);
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'mark_read') {
        Notification::markRead((int) input_string('notification_id'), (int) $user['id']);
        flash('success', 'Notification marked as read.');
    }

    redirect('notifications.php');
}

$notifications = Notification::unreadForUser((int) $user['id'], 25);

$title = 'Notifications';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>In-App Notifications</h2>
            <p>Unread approval, event, and registration notifications appear here.</p>
        </div>
        <form method="post" action="<?= h(url('notifications.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button class="button button-small" type="submit">Mark all read</button>
        </form>
    </div>

    <div class="placeholder-list">
        <?php if ($notifications === []): ?>
            <div>
                <strong>No notifications</strong>
                <span>You do not have any approval notifications yet.</span>
            </div>
        <?php endif; ?>

        <?php foreach ($notifications as $notification): ?>
            <div>
                <strong><?= h($notification['title']) ?></strong>
                <span><?= h($notification['message']) ?></span>
                <?php if ($notification['entity_type'] === 'approval_request'): ?>
                    <a href="<?= h(url('approvals/show.php?id=' . (int) $notification['entity_id'])) ?>">Open approval</a>
                <?php elseif ($notification['entity_type'] === 'event'): ?>
                    <a href="<?= h(url('events/show.php?id=' . (int) $notification['entity_id'])) ?>">Open event</a>
                <?php endif; ?>
                <form method="post" action="<?= h(url('notifications.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="notification_id" value="<?= h((string) $notification['id']) ?>">
                    <button class="link-button" type="submit">Mark read</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
