<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();
$registrations = EventRegistration::historyForUser((int) $user['id']);
$title = 'Registration History';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>
<section class="panel">
    <h2>My Registrations</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Event</th><th>Date</th><th>Type</th><th>Team</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($registrations === []): ?><tr><td colspan="5">No registrations yet.</td></tr><?php endif; ?>
            <?php foreach ($registrations as $registration): ?>
                <tr>
                    <td><a href="<?= h(url('events/show.php?slug=' . urlencode($registration['slug']))) ?>"><?= h($registration['title']) ?></a></td>
                    <td><?= h($registration['event_date']) ?></td>
                    <td><?= h($registration['registration_type']) ?></td>
                    <td><?= h($registration['team_name'] ?? '-') ?> <?= !empty($registration['team_identifier']) ? '(' . h($registration['team_identifier']) . ')' : '' ?></td>
                    <td><?= h($registration['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
