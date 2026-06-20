<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('club_member');
$userId = (int) $user['id'];

// Fetch live metrics
$regEventsCount = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id AND status = 'registered'");
$regEventsCount->execute(['user_id' => $userId]);
$regEventsCountVal = (int) $regEventsCount->fetchColumn();

$attendanceCount = db()->prepare("SELECT COUNT(DISTINCT event_id) FROM event_attendance WHERE user_id = :user_id AND attendance_type = 'check_in'");
$attendanceCount->execute(['user_id' => $userId]);
$attendanceCountVal = (int) $attendanceCount->fetchColumn();

$pointsCountVal = PointsService::getUserPointsTotal($userId);

$certCount = db()->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = :user_id");
$certCount->execute(['user_id' => $userId]);
$certCountVal = (int) $certCount->fetchColumn();

$title = 'Club Member Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Registered Events', 'value' => (string) $regEventsCountVal, 'hint' => 'Events you registered for'],
    ['label' => 'Attendance Check-ins', 'value' => (string) $attendanceCountVal, 'hint' => 'Events checked into'],
    ['label' => 'Reward Points', 'value' => (string) $pointsCountVal, 'hint' => 'Your current rewards balance'],
    ['label' => 'Certificates', 'value' => (string) $certCountVal, 'hint' => 'Certificates issued to you'],
]);

render_placeholder_panel('Member Activity Center', [
    ['title' => 'My Recognition & Badges', 'body' => 'Track your progress milestones, view unlocked achievements, and browse the catalog to redeem stickers, passes or hoodies at <a href="' . h(url('rewards/dashboard.php')) . '" style="color: var(--primary); font-weight: 600;">My Rewards Dashboard</a>.'],
    ['title' => 'Event Participation', 'body' => 'Check into events using QR codes. View your active and completed events at <a href="' . h(url('registrations/history.php')) . '" style="color: var(--primary); font-weight: 600;">My Registrations History</a>.'],
    ['title' => 'Secure Public Verification', 'body' => 'Verify other club certificates or check cryptographic signatures at <a href="' . h(url('certificates/verify.php')) . '" style="color: var(--primary); font-weight: 600;">Public Verification Portal</a>.'],
]);

require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
