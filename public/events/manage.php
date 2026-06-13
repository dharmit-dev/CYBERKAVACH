<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);
$search = trim((string) ($_GET['q'] ?? ''));
$events = Event::listManaged($search);

$title = 'Manage Events';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Draft Events', 'value' => Event::countByStatus('draft'), 'hint' => 'Events still being prepared'],
    ['label' => 'Pending Approval', 'value' => Event::countByStatus('pending_approval'), 'hint' => 'Submitted for workflow review'],
    ['label' => 'Published', 'value' => Event::countByStatus('published'), 'hint' => 'Visible in public portal'],
    ['label' => 'Rejected', 'value' => Event::countByStatus('rejected'), 'hint' => 'Needs revision before resubmission'],
]);
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Event Management</h2>
            <p>Create, edit, submit, publish through approval, and monitor registrations.</p>
        </div>
        <a class="button button-small" href="<?= h(url('events/create.php')) ?>">Create event</a>
    </div>

    <form class="inline-filter" method="get" action="<?= h(url('events/manage.php')) ?>">
        <input name="q" value="<?= h($search) ?>" placeholder="Search events or venue">
        <button class="button button-small" type="submit">Search</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Event</th>
                <th>Date</th>
                <th>Status</th>
                <th>Registrations</th>
                <th>Teams</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($events === []): ?>
                <tr><td colspan="6">No events found.</td></tr>
            <?php endif; ?>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><?= h($event['title']) ?><br><span class="muted"><?= h($event['category_name']) ?></span></td>
                    <td><?= h($event['event_date']) ?> <?= h(substr($event['start_time'], 0, 5)) ?></td>
                    <td><?= ApprovalService::statusBadge($event['status']) ?></td>
                    <td><?= h((string) $event['registration_count']) ?></td>
                    <td><?= h((string) $event['team_count']) ?></td>
                    <td class="action-links">
                        <a href="<?= h(url('events/show.php?id=' . (int) $event['id'])) ?>">View</a>
                        <a href="<?= h(url('events/edit.php?id=' . (int) $event['id'])) ?>">Edit</a>
                        <a href="<?= h(url('events/registrations.php?id=' . (int) $event['id'])) ?>">Participants</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
