<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('faculty_coordinator');

$search = trim((string) ($_GET['search'] ?? ''));

if (request_method_is('POST')) {
    verify_csrf();

    $action = input_string('action');
    $targetUserId = (int) input_string('user_id');

    $targetUser = User::findById($targetUserId);
    if ($targetUser) {
        if ($action === 'change_role') {
            $newRoleId = (int) input_string('role_id');
            if ($newRoleId > 0 && $newRoleId !== (int) $targetUser['role_id']) {
                User::updateRole($targetUserId, $newRoleId);
                AuditService::record(
                    'role_changed',
                    'auth',
                    (int) $user['id'],
                    'users',
                    $targetUserId,
                    ['role_id' => $targetUser['role_id']],
                    ['role_id' => $newRoleId]
                );
                flash('success', 'User role updated successfully.');
            }
        } elseif ($action === 'block') {
            User::block($targetUserId);
            AuditService::record('account_blocked', 'auth', (int) $user['id'], 'users', $targetUserId);
            flash('success', 'User account blocked successfully.');
        } elseif ($action === 'unblock') {
            User::unblock($targetUserId);
            AuditService::record('account_unblocked', 'auth', (int) $user['id'], 'users', $targetUserId);
            flash('success', 'User account unblocked successfully.');
        }
    }
    redirect('admin/users.php?search=' . urlencode($search));
}

$users = User::listAll($search, 100);
$roles = User::allRoles();

$title = 'User Accounts Management';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header" style="border-bottom: 1px solid var(--line); padding-bottom: 1.5rem; margin-bottom: 2rem;">
    <h2>User Accounts Management</h2>
    <p class="muted" style="margin-top: 0.5rem;">View all registered users, promote coordinators, modify roles, and manage account statuses.</p>
</div>

<div class="card" style="margin-bottom: 2rem;">
    <form method="get" action="<?= h(url('admin/users.php')) ?>" class="inline-filter">
        <input name="search" value="<?= h($search) ?>" placeholder="Search by name, email, roll number, college ID" style="min-width: 320px;">
        <button class="button button-small" type="submit">Filter Users</button>
    </form>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User Detail</th>
                    <th>Academics ID</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--muted);">No users found.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid var(--line);">
                        <td>
                            <strong><?= h($u['full_name']) ?></strong><br>
                            <span class="muted" style="font-size: 0.9em;"><?= h($u['email']) ?></span><br>
                            <span class="muted" style="font-size: 0.85em;"><?= h($u['phone']) ?></span>
                        </td>
                        <td>
                            <?php if (!empty($u['college_id']) || !empty($u['roll_number'])): ?>
                                <span style="font-size: 0.9em;">
                                    College ID: <?= h($u['college_id'] ?: '-') ?><br>
                                    Roll No: <?= h($u['roll_number'] ?: '-') ?>
                                </span>
                            <?php else: ?>
                                <span class="muted" style="font-size: 0.9em;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- Change Role Form -->
                            <form method="post" action="<?= h(url('admin/users.php?search='.urlencode($search))) ?>" style="margin: 0; display: inline-flex; gap: 0.5rem; align-items: center;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="user_id" value="<?= h((string) $u['id']) ?>">
                                <select name="role_id" onchange="this.form.submit()" style="height: 34px; padding: 4px 8px; font-size: 0.9em;">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= (int) $u['role_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                                            <?= h($r['role_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php 
                            $statusClass = match($u['status']) {
                                'active' => 'badge-approved',
                                'blocked' => 'badge-rejected',
                                'pending_approval' => 'badge-pending',
                                default => 'badge-draft'
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>" style="padding: 0.25rem 0.5rem; font-size: 0.85em; font-weight: 600;">
                                <?= h(str_replace('_', ' ', strtoupper($u['status']))) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="<?= h(url('admin/users.php?search='.urlencode($search))) ?>" style="margin: 0; display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= h((string) $u['id']) ?>">
                                <?php if ($u['status'] === 'blocked'): ?>
                                    <button class="button button-small button-approve" type="submit" name="action" value="unblock" style="padding: 4px 8px; font-size: 0.85em;">Activate</button>
                                <?php elseif ($u['status'] === 'active'): ?>
                                    <button class="button button-small button-reject" type="submit" name="action" value="block" style="padding: 4px 8px; font-size: 0.85em;">Block</button>
                                <?php else: ?>
                                    <span class="muted" style="font-size: 0.9em;">Verify/Approve via Queue</span>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
