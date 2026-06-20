<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_role('faculty_coordinator');

if (request_method_is('POST') && input_string('action') === 'run_escalation') {
    verify_csrf();
    
    $cmd = 'C:\\xampp\\php\\php.exe ' . escapeshellarg(BASE_PATH . '/app/Core/escalate.php');
    @exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0) {
        // Find how many requests were escalated in output log
        $outputString = implode(" | ", $output);
        flash('success', 'Escalation engine completed: ' . $outputString);
    } else {
        flash('error', 'Escalation checker engine failed to run.');
    }
    
    redirect('dashboards/faculty.php');
}

$title = 'Faculty Coordinator Dashboard';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';

render_metric_cards([
    ['label' => 'Pending Approvals', 'value' => ApprovalRequest::countByStatusForUser($user, 'pending') + ApprovalRequest::countByStatusForUser($user, 'under_review'), 'hint' => 'Requests waiting across approval workflows'],
    ['label' => 'Pending Accounts', 'value' => User::countByStatus('pending_approval'), 'hint' => 'Verified accounts needing approval'],
    ['label' => 'Club Members', 'value' => User::countByRoleKey('club_member'), 'hint' => 'Active approved members'],
    ['label' => 'Escalations', 'value' => count(ApprovalService::escalationCandidates()), 'hint' => 'Requests past configured review window'],
]);
?>

<section class="panel" style="margin-top: 2rem; background: #ffffff; border: 1px solid var(--line); border-radius: 8px; padding: 2rem;">
    <h2 style="margin-top: 0; margin-bottom: 0.5rem;">Governance Control Center</h2>
    <p class="muted" style="margin-bottom: 2rem; font-size: 0.95em;">Club governance shortcuts for account setups, system monitoring, and queue overrides.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
        <article class="card" style="padding: 1.5rem; border: 1px solid var(--line); border-radius: 8px; background: #f8f9fa; display: flex; flex-direction: column; justify-content: space-between; min-height: 180px;">
            <div>
                <h3 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem;">User Administration</h3>
                <p class="muted" style="margin-bottom: 1rem; font-size: 0.9em; line-height: 1.4;">Promote coordinators, block/unblock members, edit role assignments, and review roll numbers.</p>
            </div>
            <a class="button button-small" href="<?= h(url('admin/users.php')) ?>" style="width: auto; align-self: flex-start;">Manage Accounts</a>
        </article>

        <article class="card" style="padding: 1.5rem; border: 1px solid var(--line); border-radius: 8px; background: #f8f9fa; display: flex; flex-direction: column; justify-content: space-between; min-height: 180px;">
            <div>
                <h3 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem;">Security Audit Logs</h3>
                <p class="muted" style="margin-bottom: 1rem; font-size: 0.9em; line-height: 1.4;">Review detailed audit logs, login attempts, IP addresses, user agents, and database value alterations.</p>
            </div>
            <a class="button button-small" href="<?= h(url('admin/audit.php')) ?>" style="width: auto; align-self: flex-start;">View Audit Console</a>
        </article>

        <article class="card" style="padding: 1.5rem; border: 1px solid var(--line); border-radius: 8px; background: #f8f9fa; display: flex; flex-direction: column; justify-content: space-between; min-height: 180px;">
            <div>
                <h3 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem;">Escalation Engine</h3>
                <p class="muted" style="margin-bottom: 1rem; font-size: 0.9em; line-height: 1.4;">Scan pending requests for inactivity (>48h) and trigger alerts for Student and Faculty coordinators.</p>
            </div>
            <form method="post" action="<?= h(url('dashboards/faculty.php')) ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" name="action" value="run_escalation" class="button button-small button-return" style="width: auto; align-self: flex-start;">Run Checker Hook</button>
            </form>
        </article>

        <article class="card" style="padding: 1.5rem; border: 1px solid var(--line); border-radius: 8px; background: #f8f9fa; display: flex; flex-direction: column; justify-content: space-between; min-height: 180px;">
            <div>
                <h3 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem;">Approvals Queue</h3>
                <p class="muted" style="margin-bottom: 1rem; font-size: 0.9em; line-height: 1.4;">Action pending requests for events, budgets, resource allocations, and content publishing.</p>
            </div>
            <a class="button button-small" href="<?= h(url('approvals/index.php')) ?>" style="width: auto; align-self: flex-start; background: var(--success);">Open Queue</a>
        </article>
    </div>
</section>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
