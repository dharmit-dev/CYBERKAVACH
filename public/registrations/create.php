<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();
$event = Event::findById((int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0));

if (!$event || $event['status'] !== 'published') {
    flash('error', 'Event is not available for registration.');
    redirect('events/index.php');
}

if (request_method_is('POST')) {
    verify_csrf();
    $result = EventService::register([
        'event_id' => (int) $event['id'],
        'registration_type' => input_string('registration_type'),
        'team_name' => input_string('team_name'),
        'saved_team_id' => input_string('saved_team_id'),
        'member_ids' => input_string('member_ids'),
    ], $user);

    if (!$result['ok']) {
        flash('error', $result['message']);
        redirect('registrations/create.php?event_id=' . (int) $event['id']);
    }

    flash('success', $result['message']);
    redirect('registrations/history.php');
}

$query = trim((string) ($_GET['member_q'] ?? ''));
$members = $query !== '' ? User::searchActiveUsers($query, 15) : [];
$savedTeams = EventRegistration::savedTeamsForUser((int) $user['id']);
$title = 'Register for Event';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>
<section class="panel">
    <h2><?= h($event['title']) ?></h2>
    <p>Choose individual registration or team registration.</p>

    <form class="inline-filter" method="get" action="<?= h(url('registrations/create.php')) ?>">
        <input type="hidden" name="event_id" value="<?= h((string) $event['id']) ?>">
        <input name="member_q" value="<?= h($query) ?>" placeholder="Search team members by name, email, student ID">
        <button class="button button-small" type="submit">Search members</button>
    </form>

    <form method="post" action="<?= h(url('registrations/create.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="event_id" value="<?= h((string) $event['id']) ?>">

        <div class="field">
            <label for="registration_type">Registration type</label>
            <select id="registration_type" name="registration_type">
                <option value="individual">Individual</option>
                <?php if ((int) $event['team_allowed'] === 1): ?><option value="team">Team</option><?php endif; ?>
            </select>
        </div>

        <?php if ((int) $event['team_allowed'] === 1): ?>
            <div class="field">
                <label for="team_name">Team name</label>
                <input id="team_name" name="team_name">
            </div>

            <div class="field">
                <label for="saved_team_id">Reuse saved team</label>
                <select id="saved_team_id" name="saved_team_id">
                    <option value="">Create/select members manually</option>
                    <?php foreach ($savedTeams as $savedTeam): ?>
                        <option value="<?= h((string) $savedTeam['id']) ?>"><?= h($savedTeam['team_name']) ?> (<?= h((string) $savedTeam['member_count']) ?> members)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="member_ids">Selected member IDs</label>
                <input id="member_ids" name="member_ids" placeholder="Comma-separated IDs from search results">
                <p class="muted">Leader is added automatically. Team size must be <?= h((string) $event['min_team_size']) ?>-<?= h((string) $event['max_team_size']) ?> including leader.</p>
            </div>

            <?php if ($members !== []): ?>
                <div class="subpanel">
                    <h3>Search Results</h3>
                    <div class="placeholder-list">
                        <?php foreach ($members as $member): ?>
                            <div>
                                <strong>#<?= h((string) $member['id']) ?> <?= h($member['full_name']) ?></strong>
                                <span><?= h($member['email']) ?> / <?= h($member['role_name']) ?> / <?= h((string) ($member['college_id'] ?? $member['roll_number'] ?? 'No student ID')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <button class="button button-small" type="submit">Complete registration</button>
    </form>
</section>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
