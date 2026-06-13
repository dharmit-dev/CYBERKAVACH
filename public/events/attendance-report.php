<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';

$user = require_auth();

if (!user_has_permission($user, 'events.attendance')) {
    flash('error', 'Unauthorized access.');
    redirect('dashboard.php');
}

$eventId = (int) ($_GET['event_id'] ?? 0);
$filter = trim((string) ($_GET['filter'] ?? ''));

$eventsStmt = db()->prepare("SELECT id, title FROM events WHERE status IN ('approved', 'published', 'completed') ORDER BY event_date DESC");
$eventsStmt->execute();
$events = $eventsStmt->fetchAll();

if ($eventId === 0 && count($events) > 0) {
    $eventId = (int) $events[0]['id'];
}

$participants = [];
$event = null;

$stats = [
    'registered' => 0,
    'checked_in' => 0,
    'checked_out' => 0,
    'absent' => 0,
    'percentage' => 0,
];

if ($eventId > 0) {
    $event = Event::findById($eventId);

    if (!$event) {
        flash('error', 'Event not found.');
        redirect('events/attendance-report.php');
    }

    $sql = "
        SELECT 
            u.id as user_id, u.full_name, u.email, u.phone,
            et.id as team_id, et.team_name,
            (SELECT scanned_at FROM event_attendance WHERE event_id = :event_id_1 AND user_id = u.id AND attendance_type = 'check_in' ORDER BY scanned_at ASC LIMIT 1) as check_in_time,
            (SELECT scanned_at FROM event_attendance WHERE event_id = :event_id_2 AND user_id = u.id AND attendance_type = 'check_out' ORDER BY scanned_at DESC LIMIT 1) as check_out_time,
            (SELECT attendance_type FROM event_attendance WHERE event_id = :event_id_3 AND user_id = u.id ORDER BY scanned_at DESC LIMIT 1) as current_status
        FROM users u
        LEFT JOIN event_registrations er ON er.user_id = u.id AND er.event_id = :event_id_4 AND er.registration_type = 'individual' AND er.status = 'registered'
        LEFT JOIN event_team_members etm ON etm.user_id = u.id
        LEFT JOIN event_teams et ON et.id = etm.team_id AND et.event_id = :event_id_5 AND et.status = 'registered'
        WHERE (er.id IS NOT NULL OR et.id IS NOT NULL)
        ORDER BY u.full_name ASC
    ";

    $params = [
        'event_id_1' => $eventId,
        'event_id_2' => $eventId,
        'event_id_3' => $eventId,
        'event_id_4' => $eventId,
        'event_id_5' => $eventId,
    ];

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rawParticipants = $stmt->fetchAll();

    foreach ($rawParticipants as $p) {
        $stats['registered']++;
        $status = $p['current_status'] ?? 'absent';
        
        if ($status === 'check_in') {
            $stats['checked_in']++;
        } elseif ($status === 'check_out') {
            $stats['checked_out']++;
        } else {
            $stats['absent']++;
        }

        if ($filter === 'absent' && $status !== 'absent') continue;
        if ($filter === 'checked_in' && $status !== 'check_in') continue;
        if ($filter === 'checked_out' && $status !== 'check_out') continue;

        $participants[] = $p;
    }

    if ($stats['registered'] > 0) {
        $stats['percentage'] = round((($stats['checked_in'] + $stats['checked_out']) / $stats['registered']) * 100, 1);
    }

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance-report-event-' . $eventId . '.csv"');
        
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Participant Name', 'Email', 'Phone', 'Team', 'Status', 'Check-In Time', 'Check-Out Time']);
        
        foreach ($participants as $p) {
            fputcsv($out, [
                $p['full_name'],
                $p['email'],
                $p['phone'],
                $p['team_name'] ?? 'Individual',
                str_replace('_', ' ', strtoupper($p['current_status'] ?? 'absent')),
                $p['check_in_time'] ?? '-',
                $p['check_out_time'] ?? '-'
            ]);
        }
        fclose($out);
        exit;
    }
}

$title = 'Attendance Report | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>Attendance Report</h2>
    <div class="actions">
        <?php if ($eventId > 0): ?>
            <a href="<?= h(url('events/attendance-report.php?export=csv&event_id=' . $eventId . '&filter=' . urlencode($filter))) ?>" class="button button-outline">Export to CSV</a>
            <a href="<?= h(url('events/attendance.php?event_id=' . $eventId)) ?>" class="button">Manage Attendance</a>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom: 2rem;">
    <form method="get" action="<?= h(url('events/attendance-report.php')) ?>" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; align-items: end;">
        
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
            <label for="filter">Filter By Status</label>
            <select id="filter" name="filter" onchange="this.form.submit()" class="form-control">
                <option value="">All Participants</option>
                <option value="absent" <?= $filter === 'absent' ? 'selected' : '' ?>>Absent</option>
                <option value="checked_in" <?= $filter === 'checked_in' ? 'selected' : '' ?>>Currently Checked In</option>
                <option value="checked_out" <?= $filter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
            </select>
        </div>

        <button type="submit" class="button">Apply Filter</button>
    </form>
</div>

<?php if ($eventId > 0): ?>

    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="card" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 2rem; font-weight: bold; color: #333;"><?= $stats['registered'] ?></div>
            <div style="color: #666; font-size: 0.9em; text-transform: uppercase;">Total Registered</div>
        </div>
        <div class="card" style="text-align: center; padding: 1.5rem; border-bottom: 4px solid #28a745;">
            <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?= $stats['checked_in'] ?></div>
            <div style="color: #666; font-size: 0.9em; text-transform: uppercase;">Checked In</div>
        </div>
        <div class="card" style="text-align: center; padding: 1.5rem; border-bottom: 4px solid #ffc107;">
            <div style="font-size: 2rem; font-weight: bold; color: #ffc107;"><?= $stats['checked_out'] ?></div>
            <div style="color: #666; font-size: 0.9em; text-transform: uppercase;">Checked Out</div>
        </div>
        <div class="card" style="text-align: center; padding: 1.5rem; border-bottom: 4px solid #dc3545;">
            <div style="font-size: 2rem; font-weight: bold; color: #dc3545;"><?= $stats['absent'] ?></div>
            <div style="color: #666; font-size: 0.9em; text-transform: uppercase;">Absent</div>
        </div>
        <div class="card" style="text-align: center; padding: 1.5rem; background: #f8f9fa;">
            <div style="font-size: 2rem; font-weight: bold; color: #17a2b8;"><?= $stats['percentage'] ?>%</div>
            <div style="color: #666; font-size: 0.9em; text-transform: uppercase;">Attendance Rate</div>
        </div>
    </div>

    <div class="card">
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; min-width: 900px; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Participant</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Email</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Team Info</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Status</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Check-In Time</th>
                        <th style="padding: 1rem; border-bottom: 2px solid #eee;">Check-Out Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($participants) === 0): ?>
                        <tr>
                            <td colspan="6" style="padding: 2rem; text-align: center; color: #666;">No records found for the current filter.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($participants as $p): ?>
                        <?php 
                        $currentStatus = $p['current_status'] ?? 'absent';
                        $statusColor = match($currentStatus) {
                            'check_in' => '#28a745',
                            'check_out' => '#ffc107',
                            default => '#dc3545'
                        };
                        ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 1rem; font-weight: 500;">
                                <?= h($p['full_name']) ?>
                            </td>
                            <td style="padding: 1rem; color: #555;">
                                <?= h($p['email']) ?>
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
                            </td>
                            <td style="padding: 1rem; color: #444; font-family: monospace;">
                                <?= $p['check_in_time'] ? h(date('M d, H:i:s', strtotime($p['check_in_time']))) : '<span style="color:#ccc;">-</span>' ?>
                            </td>
                            <td style="padding: 1rem; color: #444; font-family: monospace;">
                                <?= $p['check_out_time'] ? h(date('M d, H:i:s', strtotime($p['check_out_time']))) : '<span style="color:#ccc;">-</span>' ?>
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
