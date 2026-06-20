<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Services/AttendanceService.php';

$user = require_auth();

if (!user_has_permission($user, 'events.attendance')) {
    flash('error', 'Unauthorized access.');
    redirect('dashboard.php');
}

if (request_method_is('POST')) {
    verify_csrf();

    $eventId = (int) input_string('event_id');
    $userId = (int) input_string('user_id');
    $teamId = (int) input_string('team_id');
    $action = input_string('action');

    if ($action === 'check_in') {
        $result = AttendanceService::checkIn($eventId, $userId, $user, $teamId > 0 ? $teamId : null);
    } elseif ($action === 'check_out') {
        $result = AttendanceService::checkOut($eventId, $userId, $user, $teamId > 0 ? $teamId : null);
    } else {
        $result = ['ok' => false, 'message' => 'Invalid action.'];
    }

    if ($result['ok']) {
        flash('success', $result['message']);
    } else {
        flash('error', $result['message']);
    }

    $q = http_build_query([
        'event_id' => $eventId,
        'search' => $_GET['search'] ?? '',
        'filter' => $_GET['filter'] ?? '',
        'page' => $_GET['page'] ?? 1
    ]);
    redirect('events/attendance.php?' . $q);
}

$eventId = (int) ($_GET['event_id'] ?? 0);
$search = trim((string) ($_GET['search'] ?? ''));
$filter = trim((string) ($_GET['filter'] ?? ''));

$eventsStmt = db()->prepare("SELECT id, title FROM events WHERE status IN ('approved', 'published', 'completed') ORDER BY event_date DESC");
$eventsStmt->execute();
$events = $eventsStmt->fetchAll();

if ($eventId === 0 && count($events) > 0) {
    $eventId = (int) $events[0]['id'];
}

$participants = [];
$event = null;

if ($eventId > 0) {
    $event = Event::findById($eventId);

    if (!$event) {
        flash('error', 'Event not found.');
        redirect('events/attendance.php');
    }

    $sql = "
        SELECT 
            u.id as user_id, u.full_name, u.email, u.phone,
            et.id as team_id, et.team_name,
            (SELECT attendance_type FROM event_attendance WHERE event_id = :event_id AND user_id = u.id ORDER BY scanned_at DESC LIMIT 1) as current_status,
            (SELECT is_late FROM event_attendance WHERE event_id = :event_id_3 AND user_id = u.id AND attendance_type = 'check_in' ORDER BY scanned_at DESC LIMIT 1) as is_late,
            (SELECT is_early_exit FROM event_attendance WHERE event_id = :event_id_4 AND user_id = u.id AND attendance_type = 'check_out' ORDER BY scanned_at DESC LIMIT 1) as is_early_exit
        FROM users u
        LEFT JOIN event_registrations er ON er.user_id = u.id AND er.event_id = :event_id_1 AND er.registration_type = 'individual' AND er.status = 'registered'
        LEFT JOIN event_team_members etm ON etm.user_id = u.id
        LEFT JOIN event_teams et ON et.id = etm.team_id AND et.event_id = :event_id_2 AND et.status = 'registered'
        WHERE (er.id IS NOT NULL OR et.id IS NOT NULL)
    ";

    $params = [
        'event_id' => $eventId,
        'event_id_1' => $eventId,
        'event_id_2' => $eventId,
        'event_id_3' => $eventId,
        'event_id_4' => $eventId,
    ];

    if ($search !== '') {
        $sql .= " AND (u.full_name LIKE :search1 OR u.email LIKE :search2 OR et.team_name LIKE :search3)";
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
        $params['search3'] = '%' . $search . '%';
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rawParticipants = $stmt->fetchAll();

    foreach ($rawParticipants as $p) {
        $status = $p['current_status'] ?? 'absent';
        
        if ($filter === 'absent' && $status !== 'absent') continue;
        if ($filter === 'checked_in' && $status !== 'check_in') continue;
        if ($filter === 'checked_out' && $status !== 'check_out') continue;

        $participants[] = $p;
    }
}

$title = 'Manual Attendance | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>Manual Attendance</h2>
    <div class="actions">
        <?php if ($eventId > 0): ?>
            <a href="<?= h(url('events/scan.php?event_id=' . $eventId)) ?>" class="button button-outline">Open QR Scanner</a>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom: 2rem;">
    <form method="get" action="<?= h(url('events/attendance.php')) ?>" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        
        <div class="field" style="margin: 0;">
            <label for="event_id">Select Event</label>
            <select id="event_id" name="event_id" onchange="this.form.submit()" class="form-control">
                <option value="0">-- Select Event --</option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?= h((string) $ev['id']) ?>" <?= $eventId === (int) $ev['id'] ? 'selected' : '' ?>>
                        <?= h($ev['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field" style="margin: 0;">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="<?= h($search) ?>" placeholder="Name, Email or Team" class="form-control">
        </div>

        <div class="field" style="margin: 0;">
            <label for="filter">Filter</label>
            <select id="filter" name="filter" onchange="this.form.submit()" class="form-control">
                <option value="">All Participants</option>
                <option value="absent" <?= $filter === 'absent' ? 'selected' : '' ?>>Absent</option>
                <option value="checked_in" <?= $filter === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                <option value="checked_out" <?= $filter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
            </select>
        </div>

        <button type="submit" class="button">Apply Filter</button>
    </form>
</div>

<?php if ($eventId > 0): ?>
    <div class="card">
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; min-width: 800px; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Participant</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Contact</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Team Info</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Status</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($participants) === 0): ?>
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: #666;">No participants found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($participants as $p): ?>
                        <?php 
                        $currentStatus = $p['current_status'] ?? 'absent';
                        $statusColor = match($currentStatus) {
                            'check_in' => '#28a745',
                            'check_out' => '#ffc107',
                            default => '#6c757d'
                        };
                        ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 1rem;">
                                <strong><?= h($p['full_name']) ?></strong><br>
                                <small style="color: #666;">ID: <?= h((string) $p['user_id']) ?></small>
                            </td>
                            <td style="padding: 1rem;">
                                <?= h($p['email']) ?><br>
                                <small><?= h($p['phone']) ?></small>
                            </td>
                            <td style="padding: 1rem;">
                                <?php if ($p['team_id']): ?>
                                    <span class="badge" style="background: #e9ecef; color: #333; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.85em;">
                                        <?= h($p['team_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #888; font-size: 0.9em;">Individual</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem;">
                                <span style="color: <?= $statusColor ?>; font-weight: 600; text-transform: uppercase; font-size: 0.85em;">
                                    <?= h(str_replace('_', ' ', $currentStatus)) ?>
                                </span>
                                <?php if (!empty($p['is_late'])): ?>
                                    <span class="badge" style="background: #dc3545; color: #fff; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.75em; font-weight: bold; margin-left: 5px;">LATE</span>
                                <?php endif; ?>
                                <?php if (!empty($p['is_early_exit'])): ?>
                                    <span class="badge" style="background: #fd7e14; color: #fff; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.75em; font-weight: bold; margin-left: 5px;">EARLY EXIT</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem;">
                                <form method="post" action="<?= h(url('events/attendance.php?event_id='.$eventId.'&search='.urlencode($search).'&filter='.urlencode($filter))) ?>" style="display: flex; gap: 0.5rem; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                    <input type="hidden" name="user_id" value="<?= h((string) $p['user_id']) ?>">
                                    <input type="hidden" name="team_id" value="<?= h((string) ($p['team_id'] ?? 0)) ?>">
                                    
                                    <?php if ($currentStatus === 'absent' || $currentStatus === 'check_out'): ?>
                                        <button type="submit" name="action" value="check_in" class="button" style="padding: 0.4rem 0.8rem; font-size: 0.85em; background: #28a745; color: #fff; border: none; cursor: pointer; border-radius: 4px;">Check In</button>
                                    <?php endif; ?>

                                    <?php if ($currentStatus === 'check_in'): ?>
                                        <button type="submit" name="action" value="check_out" class="button" style="padding: 0.4rem 0.8rem; font-size: 0.85em; background: #ffc107; color: #000; border: none; cursor: pointer; border-radius: 4px;">Check Out</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 3rem;">
        <h3>No Events Available</h3>
        <p>Please select an active event or wait for one to be published.</p>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
