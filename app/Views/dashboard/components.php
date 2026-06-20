<?php

declare(strict_types=1);

function dashboard_nav(?string $roleKey): array
{
    $roleKey = $roleKey ?: 'unknown';

    $common = [
        ['label' => 'Dashboard', 'href' => role_dashboard_path($roleKey)],
    ];

    if ($roleKey !== 'guest_participant' && $roleKey !== 'unknown') {
        $common[] = ['label' => 'Submit Request', 'href' => 'approvals/create.php'];
    }

    $common[] = ['label' => 'Notifications', 'href' => 'notifications.php'];

    return match ($roleKey) {
        'faculty_coordinator' => array_merge($common, [
            ['label' => 'Manage Events', 'href' => 'events/manage.php'],
            ['label' => 'Public Events', 'href' => 'events/index.php'],
            ['label' => 'Approval Requests', 'href' => 'approvals/index.php'],
            ['label' => 'User Approvals', 'href' => 'approvals/index.php?type=user'],
            ['label' => 'Certificates', 'href' => 'certificates/templates.php'],
            ['label' => 'Analytics Review', 'href' => 'dashboard.php?panel=analytics'],
            ['label' => 'Audit Oversight', 'href' => 'dashboard.php?panel=audit'],
        ]),
        'student_coordinator' => array_merge($common, [
            ['label' => 'Manage Events', 'href' => 'events/manage.php'],
            ['label' => 'Public Events', 'href' => 'events/index.php'],
            ['label' => 'Approval Queue', 'href' => 'approvals/index.php'],
            ['label' => 'Student Accounts', 'href' => 'approvals/index.php?type=user'],
            ['label' => 'Certificates', 'href' => 'certificates/templates.php'],
            ['label' => 'Club Operations', 'href' => 'dashboard.php?panel=operations'],
            ['label' => 'Reports', 'href' => 'dashboard.php?panel=reports'],
        ]),
        'tech_coordinator' => array_merge($common, [
            ['label' => 'Manage Events', 'href' => 'events/manage.php'],
            ['label' => 'Public Events', 'href' => 'events/index.php'],
            ['label' => 'Certificates', 'href' => 'certificates/templates.php'],
            ['label' => 'QR Setup', 'href' => 'dashboard.php?panel=qr-placeholder'],
            ['label' => 'Attendance Tools', 'href' => 'dashboard.php?panel=attendance-placeholder'],
            ['label' => 'Technical Logs', 'href' => 'dashboard.php?panel=technical-placeholder'],
        ]),
        'content_coordinator' => array_merge($common, [
            ['label' => 'Content Drafts', 'href' => 'dashboard.php?panel=content-placeholder'],
            ['label' => 'Publishing Calendar', 'href' => 'dashboard.php?panel=calendar-placeholder'],
            ['label' => 'Approval Status', 'href' => 'dashboard.php?panel=approval-placeholder'],
        ]),
        'social_media_coordinator' => array_merge($common, [
            ['label' => 'Social Posts', 'href' => 'dashboard.php?panel=social-placeholder'],
            ['label' => 'Campaign Calendar', 'href' => 'dashboard.php?panel=campaign-placeholder'],
            ['label' => 'Creative Queue', 'href' => 'dashboard.php?panel=creative-placeholder'],
        ]),
        'club_member' => array_merge($common, [
            ['label' => 'Public Events', 'href' => 'events/index.php'],
            ['label' => 'My Registrations', 'href' => 'registrations/history.php'],
            ['label' => 'My Rewards', 'href' => 'dashboard.php?panel=rewards-placeholder'],
            ['label' => 'Certificates Verify', 'href' => 'certificates/verify.php'],
        ]),
        'guest_participant' => array_merge($common, [
            ['label' => 'Available Events', 'href' => 'events/index.php'],
            ['label' => 'My Registrations', 'href' => 'registrations/history.php'],
            ['label' => 'Certificate Verify', 'href' => 'certificates/verify.php'],
        ]),
        default => $common,
    };
}

function render_metric_cards(array $cards): void
{
    echo '<section class="metric-grid">';
    foreach ($cards as $card) {
        echo '<article class="metric-card">';
        echo '<span>' . h($card['label']) . '</span>';
        echo '<strong>' . h((string) $card['value']) . '</strong>';
        echo '<p>' . h($card['hint']) . '</p>';
        echo '</article>';
    }
    echo '</section>';
}

function render_placeholder_panel(string $heading, array $items): void
{
    echo '<section class="panel">';
    echo '<h2>' . h($heading) . '</h2>';
    echo '<div class="placeholder-list">';
    foreach ($items as $item) {
        echo '<div><strong>' . h($item['title']) . '</strong><span>' . h($item['body']) . '</span></div>';
    }
    echo '</div>';
    echo '</section>';
}
