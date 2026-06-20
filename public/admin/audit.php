<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('faculty_coordinator');

$moduleFilter = trim((string) ($_GET['module'] ?? ''));
$actionFilter = trim((string) ($_GET['action'] ?? ''));
$userIdFilter = ($_GET['user_id'] ?? '') !== '' ? (int) $_GET['user_id'] : null;

$logs = AuditService::listAll($moduleFilter, $actionFilter, $userIdFilter, 100);

// Get unique modules and actions for filter dropdowns
$modulesStmt = db()->query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC");
$modules = $modulesStmt->fetchAll(PDO::FETCH_COLUMN);

$actionsStmt = db()->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

$title = 'Audit Log Oversight';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header" style="border-bottom: 1px solid var(--line); padding-bottom: 1.5rem; margin-bottom: 2rem;">
    <h2>Audit Log Oversight</h2>
    <p class="muted" style="margin-top: 0.5rem;">Secure log auditing of all sensitive actions, configuration updates, and role alterations.</p>
</div>

<div class="card" style="margin-bottom: 2rem;">
    <form method="get" action="<?= h(url('admin/audit.php')) ?>" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <div class="field" style="margin: 0;">
            <label for="module">Module</label>
            <select id="module" name="module" class="form-control" onchange="this.form.submit()">
                <option value="">-- All Modules --</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= h($m) ?>" <?= $moduleFilter === $m ? 'selected' : '' ?>><?= h(ucfirst($m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field" style="margin: 0;">
            <label for="action">Action</label>
            <select id="action" name="action" class="form-control" onchange="this.form.submit()">
                <option value="">-- All Actions --</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= h($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= h(str_replace('_', ' ', ucfirst($act))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field" style="margin: 0;">
            <label for="user_id">Actor User ID</label>
            <input type="number" id="user_id" name="user_id" value="<?= $userIdFilter !== null ? h((string) $userIdFilter) : '' ?>" placeholder="ID" class="form-control">
        </div>

        <button type="submit" class="button">Apply Filters</button>
    </form>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="min-width: 140px;">Timestamp</th>
                    <th>Actor</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Entity target</th>
                    <th>Network details</th>
                    <th>Value Changes</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--muted);">No audit logs found matching filters.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr style="border-bottom: 1px solid var(--line); font-size: 0.9em;">
                        <td>
                            <span style="font-family: monospace; font-size: 0.9em;"><?= h($log['created_at']) ?></span>
                        </td>
                        <td>
                            <?php if ($log['user_id']): ?>
                                <strong><?= h($log['actor_name']) ?></strong><br>
                                <span class="muted" style="font-size: 0.85em;">ID: <?= h((string) $log['user_id']) ?></span>
                            <?php else: ?>
                                <span class="muted">System / Guest</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge" style="background: #edf6f8; color: var(--primary-dark); font-size: 0.85em;"><?= h($log['module']) ?></span>
                        </td>
                        <td>
                            <strong><?= h(str_replace('_', ' ', $log['action'])) ?></strong>
                        </td>
                        <td>
                            <?php if ($log['entity_type']): ?>
                                <span class="muted" style="font-size: 0.85em; font-family: monospace;">
                                    <?= h($log['entity_type']) ?> #<?= h((string) $log['entity_id']) ?>
                                </span>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 0.85em;">
                                IP: <?= h($log['ip_address']) ?><br>
                                <span title="<?= h($log['user_agent']) ?>" style="cursor: help; color: var(--muted);">UA Hover</span>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                <details style="cursor: pointer; font-size: 0.85em;">
                                    <summary style="color: var(--primary);">View Payload</summary>
                                    <div style="background: #f8f9fa; padding: 0.5rem; border-radius: 4px; border: 1px solid var(--line); margin-top: 0.5rem; font-family: monospace; white-space: pre-wrap; max-width: 320px; overflow-x: auto;">
                                        <?php if (!empty($log['old_values'])): ?><strong>Before:</strong> <?= h($log['old_values']) ?><br><?php endif; ?>
                                        <?php if (!empty($log['new_values'])): ?><strong>After:</strong> <?= h($log['new_values']) ?><?php endif; ?>
                                    </div>
                                </details>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
