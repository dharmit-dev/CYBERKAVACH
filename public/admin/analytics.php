<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

// Gate access to Student & Faculty Coordinators
$user = require_role(['student_coordinator', 'faculty_coordinator']);
$db = db();

// --- 1. REGISTRATION METRICS ---
$totalRegs = (int) $db->query("SELECT COUNT(*) FROM event_registrations WHERE status = 'registered'")->fetchColumn();
$indivRegs = (int) $db->query("SELECT COUNT(*) FROM event_registrations WHERE registration_type = 'individual' AND status = 'registered'")->fetchColumn();
$teamRegs = (int) $db->query("SELECT COUNT(*) FROM event_registrations WHERE registration_type = 'team' AND status = 'registered'")->fetchColumn();

// Popular Events
$popEvents = $db->query("
    SELECT e.title, COUNT(er.id) as reg_count 
    FROM events e 
    LEFT JOIN event_registrations er ON er.event_id = e.id AND er.status = 'registered' 
    GROUP BY e.id, e.title 
    ORDER BY reg_count DESC 
    LIMIT 5
")->fetchAll();

// --- 2. ATTENDANCE METRICS ---
$checkins = (int) $db->query("SELECT COUNT(*) FROM event_attendance WHERE attendance_type = 'check_in'")->fetchColumn();
$checkouts = (int) $db->query("SELECT COUNT(*) FROM event_attendance WHERE attendance_type = 'check_out'")->fetchColumn();
$lateCount = (int) $db->query("SELECT COUNT(*) FROM event_attendance WHERE attendance_type = 'check_in' AND is_late = 1")->fetchColumn();
$earlyExits = (int) $db->query("SELECT COUNT(*) FROM event_attendance WHERE attendance_type = 'check_out' AND is_early_exit = 1")->fetchColumn();

// --- 3. REWARDS & APPRECIATION METRICS ---
$pointsIssued = (int) $db->query("SELECT SUM(points) FROM member_points WHERE points > 0")->fetchColumn();
$pointsSpent = (int) abs((int) $db->query("SELECT SUM(points) FROM member_points WHERE points < 0")->fetchColumn());

// Points by category
$categoryPoints = $db->query("
    SELECT category, SUM(points) as cat_points 
    FROM member_points 
    GROUP BY category
")->fetchAll();

// Badge unlocks
$badgeUnlocks = $db->query("
    SELECT b.name, COUNT(ub.badge_id) as unlock_count 
    FROM user_badges ub 
    INNER JOIN badges b ON b.id = ub.badge_id 
    GROUP BY ub.badge_id, b.name 
    ORDER BY unlock_count DESC
")->fetchAll();

// --- 4. APPROVAL WORKFLOW PERFORMANCE ---
$statusCounts = $db->query("
    SELECT status, COUNT(*) as scount 
    FROM approval_requests 
    GROUP BY status
")->fetchAll();
$statusMap = array_column($statusCounts, 'scount', 'status');

$avgApprovalHours = $db->query("
    SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)), 1) 
    FROM approval_requests 
    WHERE status IN ('approved', 'rejected') AND completed_at IS NOT NULL
")->fetchColumn();
$avgApprovalHoursVal = $avgApprovalHours !== null ? $avgApprovalHours : '0';

// Coordinator workflow actions
$coordWorkload = $db->query("
    SELECT u.full_name, COUNT(aa.id) as action_count 
    FROM approval_actions aa 
    INNER JOIN users u ON u.id = aa.actor_user_id 
    GROUP BY aa.actor_user_id, u.full_name 
    ORDER BY action_count DESC 
    LIMIT 5
")->fetchAll();

$title = 'Club Operations Analytics | ' . app_config('name');
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>Club Operations Analytics</h2>
    <div class="actions">
        <a href="<?= h(url('admin/audit.php')) ?>" class="button button-outline">Audit Oversight Console</a>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Metric summary blocks -->
    <div class="card" style="padding: 1.5rem; text-align: center;">
        <span class="muted" style="font-size: 0.85em; text-transform: uppercase; font-weight: bold;">Active Registrations</span>
        <h2 style="font-size: 2.2rem; margin: 0.5rem 0; color: var(--primary);"><?= $totalRegs ?></h2>
        <small class="muted">Indiv: <?= $indivRegs ?> | Teams: <?= $teamRegs ?></small>
    </div>
    <div class="card" style="padding: 1.5rem; text-align: center;">
        <span class="muted" style="font-size: 0.85em; text-transform: uppercase; font-weight: bold;">Attendance Check-ins</span>
        <h2 style="font-size: 2.2rem; margin: 0.5rem 0; color: #2e7d32;"><?= $checkins ?></h2>
        <small class="muted">Late Check-ins: <?= $lateCount ?></small>
    </div>
    <div class="card" style="padding: 1.5rem; text-align: center;">
        <span class="muted" style="font-size: 0.85em; text-transform: uppercase; font-weight: bold;">Reward Points Issued</span>
        <h2 style="font-size: 2.2rem; margin: 0.5rem 0; color: #f59e0b;"><?= $pointsIssued ?> pts</h2>
        <small class="muted">Redeemed: <?= $pointsSpent ?> pts</small>
    </div>
    <div class="card" style="padding: 1.5rem; text-align: center;">
        <span class="muted" style="font-size: 0.85em; text-transform: uppercase; font-weight: bold;">Avg. Approval Velocity</span>
        <h2 style="font-size: 2.2rem; margin: 0.5rem 0; color: #3b82f6;"><?= $avgApprovalHoursVal ?> hrs</h2>
        <small class="muted">Resolved Requests</small>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
    <!-- Registration popular events -->
    <div class="card">
        <h3>Popular Events (Registrations)</h3>
        <p class="muted" style="margin-bottom: 1.5rem;">Events with the highest numbers of confirmed registrations.</p>
        
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php if ($popEvents === []): ?>
                <div class="muted" style="text-align: center; padding: 2rem 0;">No active registrations logged.</div>
            <?php endif; ?>
            <?php foreach ($popEvents as $ev): ?>
                <?php 
                $maxVal = max(1, (int) ($popEvents[0]['reg_count'] ?? 1));
                $pct = (int) (( (int) $ev['reg_count'] / $maxVal) * 100);
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 0.35rem;">
                        <strong><?= h($ev['title']) ?></strong>
                        <span style="font-weight: bold;"><?= h((string) $ev['reg_count']) ?> members</span>
                    </div>
                    <div style="background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden;">
                        <div style="background: var(--primary); width: <?= $pct ?>%; height: 100%; border-radius: 6px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Attendance Performance -->
    <div class="card">
        <h3>Attendance & Punctuality Indicators</h3>
        <p class="muted" style="margin-bottom: 1.5rem;">Audit metrics for session check-ins, check-outs, and exit timings.</p>
        
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <!-- Check-ins vs checkouts -->
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.95em; margin-bottom: 0.35rem;">
                    <span>Completed Cycles (Check-out Rate)</span>
                    <strong style="color: #2e7d32;"><?= $checkins > 0 ? (int) (($checkouts / $checkins) * 100) : 0 ?>%</strong>
                </div>
                <div style="background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden; display: flex;">
                    <?php 
                    $coPct = $checkins > 0 ? (int) (($checkouts / $checkins) * 100) : 0;
                    ?>
                    <div style="background: #2e7d32; width: <?= $coPct ?>%; height: 100%;"></div>
                </div>
                <small class="muted"><?= $checkouts ?> checkouts out of <?= $checkins ?> check-ins</small>
            </div>

            <!-- Late arrival stats -->
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.95em; margin-bottom: 0.35rem;">
                    <span>Late Arrivals Rate</span>
                    <strong style="color: #c62828;"><?= $checkins > 0 ? (int) (($lateCount / $checkins) * 100) : 0 ?>%</strong>
                </div>
                <div style="background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden;">
                    <?php 
                    $latePct = $checkins > 0 ? (int) (($lateCount / $checkins) * 100) : 0;
                    ?>
                    <div style="background: #c62828; width: <?= $latePct ?>%; height: 100%;"></div>
                </div>
                <small class="muted"><?= $lateCount ?> check-ins logged after configuration thresholds</small>
            </div>

            <!-- Early exits stats -->
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.95em; margin-bottom: 0.35rem;">
                    <span>Early Exits Rate</span>
                    <strong style="color: #fd7e14;"><?= $checkouts > 0 ? (int) (($earlyExits / $checkouts) * 100) : 0 ?>%</strong>
                </div>
                <div style="background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden;">
                    <?php 
                    $earlyPct = $checkouts > 0 ? (int) (($earlyExits / $checkouts) * 100) : 0;
                    ?>
                    <div style="background: #fd7e14; width: <?= $earlyPct ?>%; height: 100%;"></div>
                </div>
                <small class="muted"><?= $earlyExits ?> checkouts logged before configured end timings</small>
            </div>
        </div>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
    <!-- Points log category analysis -->
    <div class="card">
        <h3>Appreciation Points Allocation Analysis</h3>
        <p class="muted" style="margin-bottom: 1.5rem;">Comparison chart representing points issued by operational categories.</p>

        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php if ($categoryPoints === []): ?>
                <div class="muted" style="text-align: center; padding: 2rem 0;">No points ledger records to evaluate.</div>
            <?php endif; ?>
            <?php 
            $catMax = 1;
            foreach ($categoryPoints as $c) {
                if (abs((int) $c['cat_points']) > $catMax) {
                    $catMax = abs((int) $c['cat_points']);
                }
            }
            foreach ($categoryPoints as $c): 
                $points = (int) $c['cat_points'];
                $val = abs($points);
                $pct = (int) (($val / $catMax) * 100);
                $color = $points > 0 ? '#f59e0b' : '#ef4444';
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 0.35rem;">
                        <strong><?= h(str_replace('_', ' ', ucfirst($c['category']))) ?></strong>
                        <span style="font-weight: bold; color: <?= $color ?>;"><?= $points > 0 ? '+' : '' ?><?= h((string) $points) ?> pts</span>
                    </div>
                    <div style="background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden;">
                        <div style="background: <?= $color ?>; width: <?= $pct ?>%; height: 100%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Badge Unlocks distribution -->
    <div class="card">
        <h3>Badge Milestone Distribution</h3>
        <p class="muted" style="margin-bottom: 1.5rem;">Aggregated statistics of specific milestones unlocked by members.</p>

        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php if ($badgeUnlocks === []): ?>
                <div class="muted" style="text-align: center; padding: 2rem 0;">No badges unlocked yet.</div>
            <?php endif; ?>
            <?php foreach ($badgeUnlocks as $bu): ?>
                <?php 
                $buMax = max(1, (int) ($badgeUnlocks[0]['unlock_count'] ?? 1));
                $pct = (int) (((int) $bu['unlock_count'] / $buMax) * 100);
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 0.35rem;">
                        <strong><?= h($bu['name']) ?></strong>
                        <span style="font-weight: bold;"><?= h((string) $bu['unlock_count']) ?> members</span>
                    </div>
                    <div style="background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden;">
                        <div style="background: #10b981; width: <?= $pct ?>%; height: 100%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
    <!-- Workflow status counts -->
    <div class="card">
        <h3>Approval Queue Status Matrix</h3>
        <p class="muted" style="margin-bottom: 1.5rem;">Splits representing pending, approved, or returned requests.</p>

        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95em;">
            <thead>
                <tr style="border-bottom: 2px solid #eee;">
                    <th style="padding: 0.5rem 0;">Queue State</th>
                    <th style="padding: 0.5rem 0; text-align: right;">Count</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 0.6rem 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #ffc107;"></span>
                        Pending
                    </td>
                    <td style="padding: 0.6rem 0; text-align: right; font-weight: bold;"><?= (int) ($statusMap['pending'] ?? 0) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 0.6rem 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #17a2b8;"></span>
                        Under Review
                    </td>
                    <td style="padding: 0.6rem 0; text-align: right; font-weight: bold;"><?= (int) ($statusMap['under_review'] ?? 0) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 0.6rem 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #28a745;"></span>
                        Approved
                    </td>
                    <td style="padding: 0.6rem 0; text-align: right; font-weight: bold;"><?= (int) ($statusMap['approved'] ?? 0) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 0.6rem 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #dc3545;"></span>
                        Rejected
                    </td>
                    <td style="padding: 0.6rem 0; text-align: right; font-weight: bold;"><?= (int) ($statusMap['rejected'] ?? 0) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 0.6rem 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #6c757d;"></span>
                        Returned / Returned
                    </td>
                    <td style="padding: 0.6rem 0; text-align: right; font-weight: bold;"><?= (int) ($statusMap['returned'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Coordinator workloads -->
    <div class="card">
        <h3>Coordinator Approval Activity</h3>
        <p class="muted" style="margin-bottom: 1.5rem;">Activity log metric of reviews processed by individual coordinators.</p>

        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95em;">
            <thead>
                <tr style="border-bottom: 2px solid #eee;">
                    <th style="padding: 0.5rem 0;">Coordinator Name</th>
                    <th style="padding: 0.5rem 0; text-align: right;">Decisions Logged</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($coordWorkload === []): ?>
                    <tr>
                        <td colspan="2" style="text-align: center; padding: 2rem 0; color: #888;">No coordinator decisions recorded.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($coordWorkload as $cw): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 0.6rem 0;"><strong><?= h($cw['full_name']) ?></strong></td>
                        <td style="padding: 0.6rem 0; text-align: right; font-weight: bold;"><?= h((string) $cw['action_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
