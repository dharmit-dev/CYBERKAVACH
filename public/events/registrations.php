<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);
$event = Event::findById((int) ($_GET['id'] ?? 0));

if (!$event) {
    flash('error', 'Event not found.');
    redirect('events/manage.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$participants = EventRegistration::participantsForEvent((int) $event['id'], $search);
$totalRegistrations = EventRegistration::countForEvent((int) $event['id']);
$teamCount = EventRegistration::teamCountForEvent((int) $event['id']);
$duplicates = max(0, count($participants) - count(array_unique(array_column($participants, 'user_id'))));

$title = 'Event Registrations';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Total registrations', 'value' => $totalRegistrations, 'hint' => 'Active individual and team participants'],
    ['label' => 'Teams', 'value' => $teamCount, 'hint' => 'Registered teams for this event'],
    ['label' => 'Capacity left', 'value' => max(0, (int) $event['capacity'] - $totalRegistrations), 'hint' => 'Remaining slots'],
    ['label' => 'Duplicates', 'value' => $duplicates, 'hint' => 'Should remain zero due to DB constraints'],
]);
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2><?= h($event['title']) ?> Participants</h2>
            <p>Search, inspect, and export event registrations.</p>
        </div>
        <a class="button button-small" href="<?= h(url('events/export.php?id=' . (int) $event['id'])) ?>">Export CSV</a>
    </div>

    <form class="inline-filter" method="get" action="<?= h(url('events/registrations.php')) ?>">
        <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
        <input name="q" value="<?= h($search) ?>" placeholder="Search participants, email, team">
        <button class="button button-small" type="submit">Search</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Team</th><th>Registered</th></tr></thead>
            <tbody>
            <?php if ($participants === []): ?><tr><td colspan="5">No participants found.</td></tr><?php endif; ?>
            <?php foreach ($participants as $participant): ?>
                <tr>
                    <td><?= h($participant['full_name']) ?></td>
                    <td><?= h($participant['email']) ?></td>
                    <td><?= h($participant['registration_type']) ?></td>
                    <td><?= h($participant['team_name'] ?? '-') ?> <?= !empty($participant['team_identifier']) ? '(' . h($participant['team_identifier']) . ')' : '' ?></td>
                    <td><?= h($participant['registered_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
