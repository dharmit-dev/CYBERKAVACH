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

$entityDetails = null;
if ($request['entity_type'] === 'user') {
    $entityDetails = User::findById((int) $request['entity_id']);
} elseif ($request['entity_type'] === 'event') {
    $entityDetails = Event::findById((int) $request['entity_id']);
} elseif ($request['entity_type'] === 'budget_request') {
    $entityDetails = BudgetRequest::findById((int) $request['entity_id']);
} elseif ($request['entity_type'] === 'venue_resource_request') {
    $entityDetails = VenueResourceRequest::findById((int) $request['entity_id']);
} elseif ($request['entity_type'] === 'social_media_post') {
    $entityDetails = SocialMediaPost::findById((int) $request['entity_id']);
} elseif ($request['entity_type'] === 'content_post') {
    $entityDetails = ContentPost::findById((int) $request['entity_id']);
} elseif ($request['entity_type'] === 'external_collaboration') {
    $entityDetails = ExternalCollaboration::findById((int) $request['entity_id']);
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

        <?php if ($entityDetails): ?>
            <div class="subpanel" style="margin-top: 1.5rem; padding: 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <h3 style="margin-top: 0; margin-bottom: 1rem; color: var(--ink); border-bottom: 1px solid #cbd5e1; padding-bottom: 0.5rem; font-size: 1.1em;">Request Details</h3>
                
                <?php if ($request['entity_type'] === 'user'): ?>
                    <div class="meta-list compact">
                        <div><strong>Full Name:</strong> <?= h($entityDetails['full_name']) ?></div>
                        <div><strong>Email Address:</strong> <?= h($entityDetails['email']) ?></div>
                        <div><strong>Phone Number:</strong> <?= h($entityDetails['phone']) ?></div>
                        <div><strong>Requested Role:</strong> <?= h($entityDetails['role_name']) ?></div>
                        <div><strong>Current Status:</strong> <?= h(ucfirst($entityDetails['status'])) ?></div>
                    </div>
                <?php elseif ($request['entity_type'] === 'event'): ?>
                    <div class="meta-list compact">
                        <div><strong>Event Title:</strong> <?= h($entityDetails['title']) ?></div>
                        <div><strong>Category:</strong> <?= h($entityDetails['category_name'] ?? 'Uncategorized') ?></div>
                        <div><strong>Date:</strong> <?= h($entityDetails['event_date']) ?> (<?= h($entityDetails['start_time']) ?> - <?= h($entityDetails['end_time']) ?>)</div>
                        <div><strong>Venue:</strong> <?= h($entityDetails['venue']) ?></div>
                        <div><strong>Capacity:</strong> <?= h((string) $entityDetails['capacity']) ?> members</div>
                        <div><strong>Type:</strong> <?= (int)$entityDetails['team_allowed'] === 1 ? 'Team Event' : 'Individual Event' ?></div>
                        <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                            <strong>Description:</strong><br>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.95em; line-height: 1.4; color: var(--muted);"><?= nl2br(h($entityDetails['description'])) ?></p>
                        </div>
                    </div>
                <?php elseif ($request['entity_type'] === 'budget_request'): ?>
                    <div class="meta-list compact">
                        <div><strong>Requested Amount:</strong> <strong style="color: #2e7d32;">Rs. <?= h(number_format((float)($entityDetails['amount'] ?? 0), 2)) ?></strong></div>
                        <div><strong>Associated Event:</strong> <?= h($entityDetails['event_title'] ?? 'General Club Budget') ?></div>
                        <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                            <strong>Detailed Purpose:</strong><br>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.95em; line-height: 1.4; color: var(--muted);"><?= nl2br(h($entityDetails['purpose'])) ?></p>
                        </div>
                    </div>
                <?php elseif ($request['entity_type'] === 'venue_resource_request'): ?>
                    <div class="meta-list compact">
                        <div><strong>Requested Venue:</strong> <?= h($entityDetails['venue']) ?></div>
                        <div><strong>Associated Event:</strong> <?= h($entityDetails['event_title'] ?? 'General Club Use') ?></div>
                        <div><strong>Reservation Start:</strong> <?= h($entityDetails['start_time']) ?></div>
                        <div><strong>Reservation End:</strong> <?= h($entityDetails['end_time']) ?></div>
                        <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                            <strong>Resources Needed:</strong><br>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.95em; line-height: 1.4; color: var(--muted);"><?= nl2br(h($entityDetails['resources_needed'])) ?></p>
                        </div>
                    </div>
                <?php elseif ($request['entity_type'] === 'social_media_post'): ?>
                    <div class="meta-list compact">
                        <div><strong>Target Platforms:</strong> <?= h($entityDetails['platforms']) ?></div>
                        <div><strong>Schedule Time:</strong> <?= h($entityDetails['schedule_time'] ?? 'Post Immediately') ?></div>
                        <?php if (!empty($entityDetails['image_path'])): ?>
                            <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                                <strong>Attached Flyer:</strong><br>
                                <a href="<?= h(url($entityDetails['image_path'])) ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 500;">
                                    View attached media / image preview
                                </a>
                            </div>
                        <?php endif; ?>
                        <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                            <strong>Post Caption & Content:</strong><br>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.95em; line-height: 1.4; color: var(--muted); background: #f1f5f9; padding: 0.75rem; border-radius: 4px; font-family: monospace; white-space: pre-wrap;"><?= h($entityDetails['caption']) ?></p>
                        </div>
                    </div>
                <?php elseif ($request['entity_type'] === 'content_post'): ?>
                    <div class="meta-list compact">
                        <div><strong>Content Title:</strong> <?= h($entityDetails['title']) ?></div>
                        <div><strong>Category:</strong> <?= h($entityDetails['category']) ?></div>
                        <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                            <strong>Content Body:</strong><br>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.95em; line-height: 1.4; color: var(--muted); background: #f1f5f9; padding: 1rem; border-radius: 4px; white-space: pre-wrap; max-height: 250px; overflow-y: auto;"><?= h($entityDetails['content_body']) ?></p>
                        </div>
                    </div>
                <?php elseif ($request['entity_type'] === 'external_collaboration'): ?>
                    <div class="meta-list compact">
                        <div><strong>Partner Organization:</strong> <?= h($entityDetails['partner_name']) ?></div>
                        <div><strong>Contact Representative:</strong> <?= h($entityDetails['contact_person']) ?></div>
                        <div><strong>Contact Email:</strong> <?= h($entityDetails['contact_email']) ?></div>
                        <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                            <strong>Collaboration Description & Scope:</strong><br>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.95em; line-height: 1.4; color: var(--muted);"><?= nl2br(h($entityDetails['description'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
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
