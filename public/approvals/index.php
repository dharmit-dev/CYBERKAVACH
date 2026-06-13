<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();
$typeFilter = trim((string) ($_GET['type'] ?? ''));
$requests = ApprovalRequest::listVisibleForUser($user);

if ($typeFilter !== '') {
    $requests = array_values(array_filter(
        $requests,
        static fn (array $request): bool => $request['entity_type'] === $typeFilter
    ));
}

$title = 'Approval Requests';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Approval Queue</h2>
            <p>Only the request submitter, Student Coordinator, and Faculty Coordinator can see approval requests.</p>
        </div>
        <a class="button button-small" href="<?= h(url(role_dashboard_path($user['role_key']))) ?>">Back to dashboard</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Request</th>
                <th>Workflow</th>
                <th>Submitter</th>
                <th>Current Step</th>
                <th>Status</th>
                <th>Updated</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($requests === []): ?>
                <tr>
                    <td colspan="7">No approval requests visible to your role.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= h($request['title']) ?></td>
                    <td><?= h($request['workflow_name']) ?></td>
                    <td><?= h($request['submitter_name']) ?></td>
                    <td><?= h($request['current_step_name'] ?? 'Completed') ?></td>
                    <td><?= ApprovalService::statusBadge($request['status']) ?></td>
                    <td><?= h((string) ($request['updated_at'] ?? $request['created_at'])) ?></td>
                    <td><a href="<?= h(url('approvals/show.php?id=' . (int) $request['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
