<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();
$requestId = (int) ($_GET['id'] ?? 0);
$request = $requestId > 0 ? ApprovalRequest::findById($requestId) : null;

if (!$request || !ApprovalService::canView($request, $user)) {
    flash('error', 'Unauthorized access');
    redirect('dashboard.php');
}

$targetUser = null;
if ($request['entity_type'] === 'user') {
    $targetUser = User::findById((int) $request['entity_id']);
}

$timeline = ApprovalAction::timelineForRequest($requestId);
$currentStep = ApprovalService::stepForRequest($request);
$canAct = ApprovalService::canAct($request, $user);

$title = 'Approval Detail';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>
<section class="detail-grid">
    <article class="panel">
        <div class="panel-heading">
            <div>
                <h2><?= h($request['title']) ?></h2>
                <p><?= h($request['workflow_name']) ?></p>
            </div>
            <?= ApprovalService::statusBadge($request['status']) ?>
        </div>

        <div class="meta-list">
            <div><strong>Submitter:</strong> <?= h($request['submitter_name']) ?> / <?= h($request['submitter_email']) ?></div>
            <div><strong>Entity:</strong> <?= h($request['entity_type']) ?> #<?= h((string) $request['entity_id']) ?></div>
            <div><strong>Current step:</strong> <?= h($currentStep['step_name'] ?? 'Completed') ?></div>
            <div><strong>Approver role:</strong> <?= h($currentStep['role_name'] ?? 'None') ?></div>
            <div><strong>Escalation due:</strong> <?= h((string) ($request['escalation_due_at'] ?? 'Not configured')) ?></div>
        </div>

        <?php if ($targetUser): ?>
            <div class="subpanel">
                <h3>Account Details</h3>
                <div class="meta-list compact">
                    <div><strong>Name:</strong> <?= h($targetUser['full_name']) ?></div>
                    <div><strong>Email:</strong> <?= h($targetUser['email']) ?></div>
                    <div><strong>Phone:</strong> <?= h($targetUser['phone']) ?></div>
                    <div><strong>Requested role:</strong> <?= h($targetUser['role_name']) ?></div>
                    <div><strong>Account status:</strong> <?= h($targetUser['status']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canAct): ?>
            <form class="action-form" method="post" action="<?= h(url('approvals/action.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= h((string) $requestId) ?>">

                <div class="field">
                    <label for="comments">Coordinator remarks</label>
                    <textarea id="comments" name="comments" rows="4" placeholder="Add review remarks for the approval history"></textarea>
                    <?php if (isset($pageErrors['comments'])): ?><div class="field-error"><?= h($pageErrors['comments']) ?></div><?php endif; ?>
                </div>

                <div class="button-row">
                    <button class="button button-approve" name="decision" value="approve" type="submit">Approve</button>
                    <button class="button button-return" name="decision" value="return" type="submit">Return</button>
                    <button class="button button-reject" name="decision" value="reject" type="submit">Reject</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (ApprovalService::canView($request, $user)): ?>
            <form class="comment-form" method="post" action="<?= h(url('approvals/action.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= h((string) $requestId) ?>">
                <input type="hidden" name="decision" value="comment">
                <div class="field">
                    <label for="comment_only">Add comment</label>
                    <textarea id="comment_only" name="comments" rows="3" placeholder="Add a note to the timeline"></textarea>
                </div>
                <button class="button button-small" type="submit">Add comment</button>
            </form>
        <?php endif; ?>
    </article>

    <aside class="panel">
        <h2>Approval Timeline</h2>
        <div class="timeline">
            <?php foreach ($timeline as $action): ?>
                <div class="timeline-item">
                    <strong><?= h(ucwords(str_replace('_', ' ', $action['action']))) ?></strong>
                    <span><?= h($action['actor_name']) ?> / <?= h($action['actor_role_name']) ?></span>
                    <time><?= h($action['created_at']) ?></time>
                    <?php if (!empty($action['comments'])): ?>
                        <p><?= h($action['comments']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
</section>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
