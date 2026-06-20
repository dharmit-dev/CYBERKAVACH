<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

// Gate access to club members
$user = require_role('club_member');
$userId = (int) $user['id'];

if (request_method_is('POST')) {
    verify_csrf();

    $action = input_string('action');
    $itemId = (int) ($_POST['item_id'] ?? 0);

    if ($action === 'redeem' && $itemId > 0) {
        $result = PointsService::redeemReward($userId, $itemId);
        if ($result['ok']) {
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }
    }
    redirect('rewards/dashboard.php');
}

// Fetch member data
$pointsTotal = PointsService::getUserPointsTotal($userId);
$unlockedBadges = PointsService::getUserBadges($userId);
$unlockedBadgeIds = array_column($unlockedBadges, 'name'); // to check if unlocked
$pointsLogs = PointsService::getUserPointsLogs($userId);
$leaderboard = PointsService::getLeaderboard(10);
$catalogItems = PointsService::getCatalog();

// Fetch all defined badges
$allBadges = db()->query('SELECT * FROM badges ORDER BY threshold_points ASC')->fetchAll();

// Find next milestone badge
$nextBadge = null;
foreach ($allBadges as $badge) {
    if ($pointsTotal < (int) $badge['threshold_points']) {
        $nextBadge = $badge;
        break;
    }
}

$title = 'My Rewards & Badges | ' . app_config('name');
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>My Rewards & Badges</h2>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
    <!-- Point Summary Card -->
    <div class="card" style="text-align: center; display: flex; flex-direction: column; justify-content: center; padding: 2rem;">
        <span class="muted" style="text-transform: uppercase; font-size: 0.85em; font-weight: bold; letter-spacing: 0.05em;">Your Point Balance</span>
        <h1 style="font-size: 3.5rem; margin: 0.5rem 0; color: var(--primary); font-weight: 800;"><?= $pointsTotal ?> <span style="font-size: 1.5rem; font-weight: 500; color: #555;">pts</span></h1>
        
        <?php if ($nextBadge): ?>
            <?php 
            $prevThreshold = 0;
            // Find current progress percentage
            $currentProgress = $pointsTotal;
            $neededPoints = (int) $nextBadge['threshold_points'];
            $pct = min(100, max(0, (int) (($currentProgress / $neededPoints) * 100)));
            ?>
            <div style="margin-top: 1.5rem; text-align: left;">
                <div style="display: flex; justify-content: space-between; font-size: 0.85em; margin-bottom: 0.5rem;">
                    <span>Next Milestone: <strong><?= h($nextBadge['name']) ?></strong></span>
                    <span><?= $pointsTotal ?> / <?= $neededPoints ?> pts</span>
                </div>
                <div style="background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden; width: 100%;">
                    <div style="background: var(--primary); width: <?= $pct ?>%; height: 100%; border-radius: 4px; transition: width 0.3s ease;"></div>
                </div>
                <p class="muted" style="font-size: 0.8em; margin-top: 6px; text-align: center;">Earn <?= $neededPoints - $pointsTotal ?> more points to unlock this badge!</p>
            </div>
        <?php else: ?>
            <div style="margin-top: 1.5rem;">
                <span class="badge badge-success" style="padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold;">Elite Rank Achieved</span>
                <p class="muted" style="font-size: 0.85em; margin-top: 8px;">You have unlocked all current milestone badges! Outstanding work!</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Badge Milestones List -->
    <div class="card">
        <h3>Badge Milestone Road</h3>
        <p class="muted">Participate in events and support club operations to earn points and claim badges.</p>

        <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($allBadges as $b): ?>
                <?php 
                $isUnlocked = $pointsTotal >= (int) $b['threshold_points'];
                $bg = $isUnlocked ? '#f0fdf4' : '#fafafa';
                $border = $isUnlocked ? '1px solid #bbf7d0' : '1px solid #e2e8f0';
                $opacity = $isUnlocked ? '1.0' : '0.5';
                ?>
                <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; background: <?= $bg ?>; border: <?= $border ?>; border-radius: 6px; opacity: <?= $opacity ?>;">
                    <div style="font-size: 1.8rem; min-width: 45px; height: 45px; background: <?= $isUnlocked ? '#d1fae5' : '#e2e8f0' ?>; color: <?= $isUnlocked ? '#166534' : '#64748b' ?>; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                        <!-- Fallback symbol representation if font awesome not loaded -->
                        ★
                    </div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <?= h($b['name']) ?>
                            <?php if ($isUnlocked): ?>
                                <span style="font-size: 0.75em; background: #d1fae5; color: #166534; padding: 0.1rem 0.4rem; border-radius: 12px; font-weight: bold;">Unlocked</span>
                            <?php endif; ?>
                        </h4>
                        <p class="muted" style="margin: 0.15rem 0 0 0; font-size: 0.85em;"><?= h($b['description']) ?></p>
                    </div>
                    <div style="font-size: 0.85em; font-weight: bold; text-align: right; min-width: 60px;">
                        <?= h((string) $b['threshold_points']) ?> pts
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Rewards Catalog -->
    <div class="card">
        <h3>Rewards Catalog</h3>
        <p class="muted">Redeem your earned points for exclusive physical goods, passes, or digital templates.</p>

        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
            <?php foreach ($catalogItems as $item): ?>
                <?php 
                $canAfford = $pointsTotal >= (int) $item['points_cost'];
                $hasStock = (int) $item['stock'] > 0;
                $redeemable = $canAfford && $hasStock;
                ?>
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 1.25rem; display: flex; flex-direction: column; background: #ffffff;">
                    <h4 style="margin-top: 0; margin-bottom: 0.5rem;"><?= h($item['name']) ?></h4>
                    <p class="muted" style="font-size: 0.85em; flex: 1; margin-bottom: 1rem;"><?= h($item['description']) ?></p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 0.9em;">
                        <span style="font-weight: bold; color: var(--primary); font-size: 1.1em;"><?= h((string) $item['points_cost']) ?> pts</span>
                        <span class="muted" style="font-size: 0.8em;"><?= $item['stock'] ?> left in stock</span>
                    </div>

                    <form method="post" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="redeem">
                        <input type="hidden" name="item_id" value="<?= h((string) $item['id']) ?>">
                        <button type="submit" class="button button-small" style="width: 100%; padding: 0.5rem;" <?= !$redeemable ? 'disabled' : '' ?>>
                            <?php if (!$hasStock): ?>
                                Out of Stock
                            <?php elseif (!$canAfford): ?>
                                Insufficient Points
                            <?php else: ?>
                                Redeem Item
                            <?php endif; ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Leaderboard -->
    <div class="card" style="display: flex; flex-direction: column;">
        <h3>Leaderboard Top 10</h3>
        <p class="muted">Top performing club members by overall points.</p>

        <div style="margin-top: 1.5rem; flex: 1;">
            <ol style="padding: 0; margin: 0; list-style: none;">
                <?php if ($leaderboard === []): ?>
                    <li style="color: #888; text-align: center; padding: 2rem 0;">No leaderboard data available yet.</li>
                <?php endif; ?>
                <?php 
                $rank = 1;
                foreach ($leaderboard as $row): 
                    $isSelf = (int) $row['id'] === $userId;
                    $bg = $isSelf ? '#f8fafc' : 'transparent';
                    $border = $isSelf ? '1px solid var(--primary)' : '1px solid transparent';
                    ?>
                    <li style="display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.75rem; background: <?= $bg ?>; border: <?= $border ?>; border-radius: 4px; margin-bottom: 0.25rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-weight: 800; min-width: 20px; color: <?= $rank <= 3 ? 'var(--primary)' : '#888' ?>;"><?= $rank ?></span>
                            <div>
                                <span style="font-weight: <?= $isSelf ? 'bold' : 'normal' ?>;"><?= h($row['full_name']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span style="font-size: 0.7em; background: #e2e8f0; padding: 0.1rem 0.3rem; border-radius: 3px; margin-left: 4px;">You</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span style="font-weight: bold; color: #333;"><?= h((string) $row['total_points']) ?> pts</span>
                    </li>
                    <?php 
                    $rank++;
                endforeach; 
                ?>
            </ol>
        </div>
    </div>
</div>

<!-- Points Ledger Log -->
<div class="card">
    <h3>Point Ledger Transactions</h3>
    <p class="muted">Detailed logs of your earned points, deductions, and prizes redeemed.</p>

    <div style="overflow-x: auto; margin-top: 1.5rem;">
        <table class="data-table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #eee;">
                    <th style="padding: 0.75rem;">Transaction ID</th>
                    <th style="padding: 0.75rem;">Points</th>
                    <th style="padding: 0.75rem;">Activity / Reason</th>
                    <th style="padding: 0.75rem;">Category</th>
                    <th style="padding: 0.75rem;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pointsLogs === []): ?>
                    <tr>
                        <td colspan="5" style="padding: 2rem; text-align: center; color: #666;">You haven't earned any points yet. Participate in club events to accumulate balance!</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($pointsLogs as $log): ?>
                    <?php $isPositive = (int) $log['points'] > 0; ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 0.75rem; font-family: monospace; font-weight: bold;">
                            #<?= h((string) $log['id']) ?>
                        </td>
                        <td style="padding: 0.75rem; font-weight: bold; color: <?= $isPositive ? '#28a745' : '#dc3545' ?>;">
                            <?= $isPositive ? '+' : '' ?><?= h((string) $log['points']) ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?= h($log['reason']) ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge" style="background: #e2e8f0; color: #333; text-transform: uppercase; font-size: 0.8em; padding: 0.2rem 0.5rem;">
                                <?= h(str_replace('_', ' ', $log['category'])) ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem; font-size: 0.85em; color: #666;">
                            <?= h(date('F d, Y H:i', strtotime($log['created_at']))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
