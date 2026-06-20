<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

// Gate access to coordinators
$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);

$searchQuery = trim((string) ($_GET['search'] ?? ''));
$searchedMember = null;
$memberPointsTotal = 0;
$memberBadges = [];
$memberLogs = [];

if ($searchQuery !== '') {
    $stmt = db()->prepare('
        SELECT u.id, u.full_name, u.email, u.phone, u.status, r.role_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE (u.email = :email OR u.id = :id) AND r.role_key = \'club_member\'
        LIMIT 1
    ');
    $stmt->execute([
        'email' => $searchQuery,
        'id' => is_numeric($searchQuery) ? (int) $searchQuery : 0,
    ]);
    $searchedMember = $stmt->fetch();

    if ($searchedMember) {
        $memberPointsTotal = PointsService::getUserPointsTotal((int) $searchedMember['id']);
        $memberBadges = PointsService::getUserBadges((int) $searchedMember['id']);
        $memberLogs = PointsService::getUserPointsLogs((int) $searchedMember['id']);
    } else {
        flash('error', 'No active club member found matching that Email or ID.');
    }
}

if (request_method_is('POST')) {
    verify_csrf();

    $memberId = (int) ($_POST['member_id'] ?? 0);
    $points = (int) ($_POST['points'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($memberId <= 0) {
        flash('error', 'Invalid member selection.');
    } elseif ($points === 0) {
        flash('error', 'Points value cannot be zero.');
    } elseif ($reason === '') {
        flash('error', 'Reason is required for auditing.');
    } else {
        try {
            // Verify recipient exists and is a club member
            $chk = db()->prepare('
                SELECT u.id FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.id = :id AND r.role_key = \'club_member\'
                LIMIT 1
            ');
            $chk->execute(['id' => $memberId]);
            if (!$chk->fetch()) {
                throw new RuntimeException('Selected user is not an approved club member.');
            }

            $category = $points > 0 ? 'manual_award' : 'manual_deduction';
            PointsService::awardPoints($memberId, $points, $reason, $category, (int) $user['id']);
            flash('success', 'Points adjusted successfully for the member.');
            redirect('rewards/manage.php?search=' . urlencode((string) $memberId));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('rewards/manage.php' . ($searchQuery !== '' ? '?search=' . urlencode($searchQuery) : ''));
}

// Fetch recent points transactions globally
$stmt = db()->prepare('
    SELECT mp.*, recipient.full_name as recipient_name, recipient.email as recipient_email,
           coordinator.full_name as coordinator_name
    FROM member_points mp
    INNER JOIN users recipient ON recipient.id = mp.user_id
    LEFT JOIN users coordinator ON coordinator.id = mp.awarded_by
    ORDER BY mp.created_at DESC
    LIMIT 30
');
$stmt->execute();
$recentTransactions = $stmt->fetchAll();

$title = 'Manage Club Rewards | ' . app_config('name');
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>Club Rewards & Member Recognition</h2>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
    <!-- Search / Adjustment Portal -->
    <div class="card">
        <h3>Adjust Member Points</h3>
        <p class="muted">Search for an active club member by ID or email address to review logs and adjust points.</p>

        <form method="get" action="<?= h(url('rewards/manage.php')) ?>" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; align-items: end;">
            <div class="field" style="margin: 0; flex: 1;">
                <label for="search">Member Email or ID</label>
                <input id="search" name="search" value="<?= h($searchQuery) ?>" placeholder="e.g. member@cyberkavach.local" class="form-control" required>
            </div>
            <button type="submit" class="button" style="height: 42px;">Search</button>
        </form>

        <?php if ($searchedMember): ?>
            <div style="border-top: 1px solid #eee; margin-top: 2rem; padding-top: 1.5rem;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; text-align: left; font-size: 0.95em;">
                    <tr style="border-bottom: 1px solid #eee;">
                        <th style="padding: 0.5rem 0; width: 40%;">Member Name</th>
                        <td style="padding: 0.5rem 0;"><?= h($searchedMember['full_name']) ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <th style="padding: 0.5rem 0;">Email</th>
                        <td style="padding: 0.5rem 0;"><?= h($searchedMember['email']) ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <th style="padding: 0.5rem 0;">Points Balance</th>
                        <td style="padding: 0.5rem 0; font-weight: bold; color: var(--primary);"><?= $memberPointsTotal ?> pts</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <th style="padding: 0.5rem 0;">Unlocked Badges</th>
                        <td style="padding: 0.5rem 0;">
                            <?php if ($memberBadges === []): ?>
                                <span class="muted" style="font-size: 0.9em;">None</span>
                            <?php else: ?>
                                <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                    <?php foreach ($memberBadges as $badge): ?>
                                        <span class="badge badge-success" style="font-size: 0.8em; padding: 0.25rem 0.5rem;" title="<?= h($badge['description']) ?>">
                                            <?= h($badge['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <form method="post" action="<?= h(url('rewards/manage.php?search=' . urlencode($searchQuery))) ?>" style="background: #fafafa; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 6px;" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="member_id" value="<?= h((string) $searchedMember['id']) ?>">

                    <h4 style="margin-top: 0; margin-bottom: 1rem;">Award or Deduct Points</h4>

                    <div class="field">
                        <label for="points">Points Amount</label>
                        <input type="number" id="points" name="points" placeholder="Use negative for deductions (e.g. -20 or 15)" required class="form-control">
                        <p class="muted" style="font-size: 0.8em; margin-top: 4px;">Enter a positive integer to award appreciation points, or a negative integer to penalize.</p>
                    </div>

                    <div class="field">
                        <label for="reason">Reason / Citation</label>
                        <input id="reason" name="reason" placeholder="e.g. Excellent coordinator support during workshop" required class="form-control">
                    </div>

                    <button type="submit" class="button" style="width: 100%; margin-top: 0.5rem;">Process Point Adjustment</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Member's Points Statement (when a member is searched) -->
    <div class="card" style="display: flex; flex-direction: column;">
        <h3>Member Point Ledger</h3>
        <p class="muted">Ledger timeline for the queried club member.</p>

        <div style="flex: 1; overflow-y: auto; max-height: 450px; margin-top: 1.5rem;">
            <?php if (!$searchedMember): ?>
                <div style="color: #888; padding: 3rem 0; text-align: center; border: 1px dashed #ccc; border-radius: 4px;">
                    Search a member to display their history ledger...
                </div>
            <?php elseif ($memberLogs === []): ?>
                <div style="color: #888; padding: 2rem 0; text-align: center;">No points logged yet.</div>
            <?php else: ?>
                <table style="width: 100%; font-size: 0.9em; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #eee;">
                            <th style="padding: 0.5rem 0;">Amount</th>
                            <th style="padding: 0.5rem 0;">Reason</th>
                            <th style="padding: 0.5rem 0;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memberLogs as $log): ?>
                            <?php $isPositive = (int) $log['points'] > 0; ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 0.75rem 0; font-weight: bold; color: <?= $isPositive ? '#2e7d32' : '#c62828' ?>;">
                                    <?= $isPositive ? '+' : '' ?><?= h((string) $log['points']) ?>
                                </td>
                                <td style="padding: 0.75rem 0;">
                                    <?= h($log['reason']) ?><br>
                                    <small class="muted" style="font-size: 0.85em;">Category: <?= h($log['category']) ?></small>
                                </td>
                                <td style="padding: 0.75rem 0; font-size: 0.85em; color: #666;">
                                    <?= h(date('M d, Y H:i', strtotime($log['created_at']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Global Recent Activity Stream -->
<div class="card">
    <h3>Recent Points Activity Log</h3>
    <p class="muted">Audit log showing recent points adjustments across all members.</p>

    <div style="overflow-x: auto; margin-top: 1.5rem;">
        <table class="data-table" style="width: 100%; text-align: left; border-collapse: collapse; min-width: 600px;">
            <thead>
                <tr style="border-bottom: 2px solid #eee;">
                    <th style="padding: 0.75rem; border-bottom: 2px solid #eee;">Member</th>
                    <th style="padding: 0.75rem; border-bottom: 2px solid #eee;">Points</th>
                    <th style="padding: 0.75rem; border-bottom: 2px solid #eee;">Reason</th>
                    <th style="padding: 0.75rem; border-bottom: 2px solid #eee;">Authorized By</th>
                    <th style="padding: 0.75rem; border-bottom: 2px solid #eee;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentTransactions === []): ?>
                    <tr>
                        <td colspan="5" style="padding: 2rem; text-align: center; color: #666;">No point transactions logged yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($recentTransactions as $t): ?>
                    <?php $pos = (int) $t['points'] > 0; ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 0.75rem;">
                            <strong><?= h($t['recipient_name']) ?></strong><br>
                            <small class="muted"><?= h($t['recipient_email']) ?></small>
                        </td>
                        <td style="padding: 0.75rem; font-weight: bold; color: <?= $pos ? '#28a745' : '#dc3545' ?>;">
                            <?= $pos ? '+' : '' ?><?= h((string) $t['points']) ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?= h($t['reason']) ?><br>
                            <small class="badge badge-success" style="font-size: 0.75em; padding: 0.15rem 0.35rem; text-transform: uppercase;">
                                <?= h(str_replace('_', ' ', $t['category'])) ?>
                            </small>
                        </td>
                        <td style="padding: 0.75rem; color: #555;">
                            <?= h($t['coordinator_name'] ?? 'System Process') ?>
                        </td>
                        <td style="padding: 0.75rem; font-size: 0.85em; color: #666;">
                            <?= h(date('F d, Y H:i', strtotime($t['created_at']))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
