<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';

$user = current_user();
$event = isset($_GET['slug'])
    ? Event::findBySlug((string) $_GET['slug'])
    : Event::findById((int) ($_GET['id'] ?? 0));

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$isManager = $user && in_array($user['role_key'], ['student_coordinator', 'faculty_coordinator', 'tech_coordinator'], true);
if ($event['status'] !== 'published' && !$isManager) {
    http_response_code(404);
    exit('Event not found.');
}

$registered = $user ? EventRegistration::userRegistered((int) $event['id'], (int) $user['id']) : false;
$tags = Event::tagsForEvent((int) $event['id']);

$title = $event['title'];
if ($user) {
    require_once BASE_PATH . '/app/Views/dashboard/components.php';
    $navItems = dashboard_nav($user['role_key']);
    require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
} else {
    require BASE_PATH . '/app/Views/layouts/header.php';
}
?>
<section class="detail-grid">
    <article class="panel">
        <?php if (!empty($event['poster_path'])): ?><img class="hero-poster" src="<?= h(url($event['poster_path'])) ?>" alt="<?= h($event['title']) ?> poster"><?php endif; ?>
        <div class="panel-heading">
            <div>
                <h2><?= h($event['title']) ?></h2>
                <p><?= h($event['category_name']) ?> / <?= h($event['venue']) ?></p>
            </div>
            <?= ApprovalService::statusBadge($event['status']) ?>
        </div>
        <p><?= nl2br(h($event['description'])) ?></p>
        <div class="tag-row">
            <?php foreach ($tags as $tag): ?><span><?= h($tag) ?></span><?php endforeach; ?>
        </div>

        <div class="subpanel">
            <h3>Rules</h3>
            <p><?= nl2br(h($event['event_rules'] ?: 'No extra rules added.')) ?></p>
        </div>
    </article>

    <aside class="panel">
        <h2>Event Info</h2>
        <div class="meta-list">
            <div><strong>Date:</strong> <?= h($event['event_date']) ?></div>
            <div><strong>Time:</strong> <?= h(substr($event['start_time'], 0, 5)) ?> - <?= h(substr($event['end_time'], 0, 5)) ?></div>
            <div><strong>Deadline:</strong> <?= h($event['registration_deadline']) ?></div>
            <div><strong>Capacity:</strong> <?= h((string) $event['capacity']) ?></div>
            <div><strong>Team allowed:</strong> <?= (int) $event['team_allowed'] === 1 ? 'Yes' : 'No' ?></div>
            <?php if ((int) $event['team_allowed'] === 1): ?>
                <div><strong>Team size:</strong> <?= h((string) $event['min_team_size']) ?> - <?= h((string) $event['max_team_size']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($event['status'] === 'published'): ?>
            <?php if (!$user): ?>
                <a class="button" href="<?= h(url('login.php')) ?>">Login to register</a>
            <?php elseif ($registered): ?>
                <div class="alert alert-success">You are registered for this event.</div>
            <?php else: ?>
                <a class="button" href="<?= h(url('registrations/create.php?event_id=' . (int) $event['id'])) ?>">Register</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($isManager): ?>
            <div class="button-stack">
                <a class="button button-small" href="<?= h(url('events/edit.php?id=' . (int) $event['id'])) ?>">Edit</a>
                <a class="button button-small" href="<?= h(url('events/registrations.php?id=' . (int) $event['id'])) ?>">Registrations</a>
                <?php if (in_array($event['status'], ['draft', 'rejected'], true)): ?>
                    <form method="post" action="<?= h(url('events/submit.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
                        <button class="button button-small button-approve" type="submit">Submit for approval</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= h(url('events/cancel.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
                    <button class="button button-small button-reject" type="submit">Cancel event</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>
</section>
<?php
if ($user) {
    require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
} else {
    require BASE_PATH . '/app/Views/layouts/footer.php';
}
?>
