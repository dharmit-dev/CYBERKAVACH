<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();

$errors = [];
$successMessage = null;

// Fetch events for selection dropdown
$eventsStmt = db()->query("SELECT id, title FROM events ORDER BY created_at DESC");
$events = $eventsStmt->fetchAll();

$tab = trim((string) ($_GET['tab'] ?? 'budget'));
if (!in_array($tab, ['budget', 'venue', 'social', 'content', 'collab'], true)) {
    $tab = 'budget';
}

if (request_method_is('POST')) {
    verify_csrf();

    $action = input_string('action');

    try {
        db()->beginTransaction();

        if ($action === 'budget') {
            $eventId = (int) input_string('event_id');
            $amount = floatval(input_string('amount'));
            $purpose = input_string('purpose');

            if ($amount <= 0) {
                $errors['amount'] = 'Amount must be greater than 0.';
            }
            if ($purpose === '') {
                $errors['purpose'] = 'Purpose is required.';
            }

            if ($errors === []) {
                $entityId = BudgetRequest::create([
                    'event_id' => $eventId > 0 ? $eventId : null,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'requested_by' => (int) $user['id'],
                    'status' => 'pending',
                ]);

                $eventTitle = '';
                if ($eventId > 0) {
                    $selectedEvent = Event::findById($eventId);
                    $eventTitle = $selectedEvent ? ' for ' . $selectedEvent['title'] : '';
                }

                ApprovalService::submit(
                    'budget_request_approval',
                    'budget_request',
                    $entityId,
                    (int) $user['id'],
                    "Budget request: Rs. " . number_format($amount, 2) . $eventTitle,
                    "Purpose: " . $purpose
                );

                db()->commit();
                flash('success', 'Budget approval request submitted successfully.');
                redirect('approvals/index.php');
            }
        } elseif ($action === 'venue') {
            $eventId = (int) input_string('event_id');
            $venue = input_string('venue');
            $resources = input_string('resources_needed');
            $startTime = input_string('start_time');
            $endTime = input_string('end_time');

            if ($venue === '') {
                $errors['venue'] = 'Venue name is required.';
            }
            if ($resources === '') {
                $errors['resources_needed'] = 'Resources details are required.';
            }
            if ($startTime === '' || strtotime($startTime) === false) {
                $errors['start_time'] = 'Valid start time is required.';
            }
            if ($endTime === '' || strtotime($endTime) === false) {
                $errors['end_time'] = 'Valid end time is required.';
            } elseif ($endTime <= $startTime) {
                $errors['end_time'] = 'End time must be after start time.';
            }

            if ($errors === []) {
                $entityId = VenueResourceRequest::create([
                    'event_id' => $eventId > 0 ? $eventId : null,
                    'venue' => $venue,
                    'resources_needed' => $resources,
                    'start_time' => date('Y-m-d H:i:s', strtotime($startTime)),
                    'end_time' => date('Y-m-d H:i:s', strtotime($endTime)),
                    'requested_by' => (int) $user['id'],
                    'status' => 'pending',
                ]);

                ApprovalService::submit(
                    'venue_resource_approval',
                    'venue_resource_request',
                    $entityId,
                    (int) $user['id'],
                    "Venue & Resource request: " . $venue,
                    "Resources: " . $resources
                );

                db()->commit();
                flash('success', 'Venue / Resource request submitted successfully.');
                redirect('approvals/index.php');
            }
        } elseif ($action === 'social') {
            $platformsArray = $_POST['platforms'] ?? [];
            if (!is_array($platformsArray)) {
                $platformsArray = [];
            }
            $platforms = implode(', ', array_map('trim', $platformsArray));
            $caption = input_string('caption');
            $scheduleTime = input_string('schedule_time');

            if ($platforms === '') {
                $errors['platforms'] = 'Select at least one social media platform.';
            }
            if ($caption === '') {
                $errors['caption'] = 'Post caption/content is required.';
            }

            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                $file = $_FILES['image'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    if ((int) $file['size'] > 5 * 1024 * 1024) {
                        $errors['image'] = 'Flyer image must be 5MB or smaller.';
                    } else {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->file($file['tmp_name']);
                        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (!isset($allowed[$mime])) {
                            $errors['image'] = 'Flyer must be JPG, PNG, or WEBP image.';
                        } else {
                            $filename = 'social-' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                            $uploadDir = BASE_PATH . '/public/uploads/social';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0775, true);
                            }
                            if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                                $imagePath = 'uploads/social/' . $filename;
                            } else {
                                $errors['image'] = 'Failed to save flyer image.';
                            }
                        }
                    }
                }
            }

            if ($errors === []) {
                $entityId = SocialMediaPost::create([
                    'platforms' => $platforms,
                    'caption' => $caption,
                    'image_path' => $imagePath,
                    'schedule_time' => $scheduleTime !== '' ? date('Y-m-d H:i:s', strtotime($scheduleTime)) : null,
                    'requested_by' => (int) $user['id'],
                    'status' => 'pending',
                ]);

                ApprovalService::submit(
                    'social_media_approval',
                    'social_media_post',
                    $entityId,
                    (int) $user['id'],
                    "Social Media approval: Post for " . $platforms,
                    "Caption: " . substr($caption, 0, 100) . (strlen($caption) > 100 ? '...' : '')
                );

                db()->commit();
                flash('success', 'Social Media Posting request submitted successfully.');
                redirect('approvals/index.php');
            }
        } elseif ($action === 'content') {
            $titlePost = input_string('title');
            $category = input_string('category');
            $body = input_string('content_body');

            if ($titlePost === '') {
                $errors['title'] = 'Post title is required.';
            }
            if ($category === '') {
                $errors['category'] = 'Category is required.';
            }
            if ($body === '') {
                $errors['content_body'] = 'Content body cannot be empty.';
            }

            if ($errors === []) {
                $entityId = ContentPost::create([
                    'title' => $titlePost,
                    'content_body' => $body,
                    'category' => $category,
                    'requested_by' => (int) $user['id'],
                    'status' => 'pending',
                ]);

                ApprovalService::submit(
                    'content_publishing_approval',
                    'content_post',
                    $entityId,
                    (int) $user['id'],
                    "Content approval: " . $titlePost,
                    "Category: " . $category
                );

                db()->commit();
                flash('success', 'Content publishing request submitted successfully.');
                redirect('approvals/index.php');
            }
        } elseif ($action === 'collab') {
            $partner = input_string('partner_name');
            $desc = input_string('description');
            $contactName = input_string('contact_person');
            $contactEmail = input_string('contact_email');

            if ($partner === '') {
                $errors['partner_name'] = 'Partner name is required.';
            }
            if ($desc === '') {
                $errors['description'] = 'Collaboration description is required.';
            }
            if ($contactName === '') {
                $errors['contact_person'] = 'Contact person name is required.';
            }
            if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['contact_email'] = 'Enter a valid contact email.';
            }

            if ($errors === []) {
                $entityId = ExternalCollaboration::create([
                    'partner_name' => $partner,
                    'description' => $desc,
                    'contact_person' => $contactName,
                    'contact_email' => $contactEmail,
                    'requested_by' => (int) $user['id'],
                    'status' => 'pending',
                ]);

                ApprovalService::submit(
                    'external_collaboration_approval',
                    'external_collaboration',
                    $entityId,
                    (int) $user['id'],
                    "Collaboration: " . $partner,
                    "Contact: " . $contactName . " (" . $contactEmail . ")"
                );

                db()->commit();
                flash('success', 'External Collaboration request submitted successfully.');
                redirect('approvals/index.php');
            }
        }

        if (db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $errors['general'] = $e->getMessage();
    }
}

$title = 'Submit Approval Request';
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header" style="border-bottom: 1px solid var(--line); padding-bottom: 1.5rem; margin-bottom: 2rem;">
    <h2>Submit Permission Request</h2>
    <p class="muted" style="margin-top: 0.5rem;">Select request category below to load the appropriate submission form.</p>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-error" style="width: 100%; margin-bottom: 1.5rem;"><?= h($errors['general']) ?></div>
<?php endif; ?>

<!-- Developer-style Navigation Tabs -->
<div class="card" style="padding: 0; background: transparent; border: none; margin-bottom: 1.5rem;">
    <div style="display: flex; gap: 0.5rem; border-bottom: 2px solid var(--line); padding-bottom: 0px; flex-wrap: wrap;">
        <a href="?tab=budget" style="padding: 0.75rem 1.25rem; font-weight: 600; border-bottom: 3px solid <?= $tab === 'budget' ? 'var(--primary)' : 'transparent' ?>; color: <?= $tab === 'budget' ? 'var(--ink)' : 'var(--muted)' ?>; text-decoration: none;">Budget Approval</a>
        <a href="?tab=venue" style="padding: 0.75rem 1.25rem; font-weight: 600; border-bottom: 3px solid <?= $tab === 'venue' ? 'var(--primary)' : 'transparent' ?>; color: <?= $tab === 'venue' ? 'var(--ink)' : 'var(--muted)' ?>; text-decoration: none;">Venue & Resources</a>
        <a href="?tab=social" style="padding: 0.75rem 1.25rem; font-weight: 600; border-bottom: 3px solid <?= $tab === 'social' ? 'var(--primary)' : 'transparent' ?>; color: <?= $tab === 'social' ? 'var(--ink)' : 'var(--muted)' ?>; text-decoration: none;">Social Media Post</a>
        <a href="?tab=content" style="padding: 0.75rem 1.25rem; font-weight: 600; border-bottom: 3px solid <?= $tab === 'content' ? 'var(--primary)' : 'transparent' ?>; color: <?= $tab === 'content' ? 'var(--ink)' : 'var(--muted)' ?>; text-decoration: none;">Content Publishing</a>
        <a href="?tab=collab" style="padding: 0.75rem 1.25rem; font-weight: 600; border-bottom: 3px solid <?= $tab === 'collab' ? 'var(--primary)' : 'transparent' ?>; color: <?= $tab === 'collab' ? 'var(--ink)' : 'var(--muted)' ?>; text-decoration: none;">External Collaboration</a>
    </div>
</div>

<div class="card" style="padding: 2rem; background: #ffffff; border: 1px solid var(--line); border-radius: 8px;">

    <!-- TAB 1: BUDGET APPROVAL -->
    <?php if ($tab === 'budget'): ?>
        <form method="post" action="?tab=budget" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="budget">
            
            <h3 style="margin-top: 0; margin-bottom: 1.5rem;">Request Event Budget</h3>
            
            <div class="field">
                <label for="event_id">Associated Event (Optional)</label>
                <select id="event_id" name="event_id" style="width: 100%;">
                    <option value="0">-- General Club Budget --</option>
                    <?php foreach ($events as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= h($e['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="amount">Requested Amount (INR)</label>
                <input type="number" id="amount" name="amount" min="1" step="0.01" value="<?= h(old('amount')) ?>" required placeholder="e.g. 5000.00">
                <?php if (isset($errors['amount'])): ?><div class="field-error"><?= h($errors['amount']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label for="purpose">Detailed Purpose & Allocation Remarks</label>
                <textarea id="purpose" name="purpose" rows="6" required placeholder="Describe what this budget is for (prizes, refreshments, prints, licenses, etc.)"><?= h(old('purpose')) ?></textarea>
                <?php if (isset($errors['purpose'])): ?><div class="field-error"><?= h($errors['purpose']) ?></div><?php endif; ?>
            </div>

            <div class="subpanel" style="margin: 1.5rem 0; padding: 1rem; border-left: 4px solid var(--primary); background: #f8f9fa;">
                <h4 style="margin: 0 0 0.5rem; color: var(--primary);">Escalation Chain:</h4>
                <p class="muted" style="margin: 0; font-size: 0.9em;">Student Coordinator Review (48h max) &rarr; Faculty Coordinator Final Sign-off.</p>
            </div>

            <button class="button" type="submit" style="width: auto; min-width: 160px;">Submit Request</button>
        </form>

    <!-- TAB 2: VENUE & RESOURCES -->
    <?php if ($tab === 'venue'): ?>
        <form method="post" action="?tab=venue" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="venue">
            
            <h3 style="margin-top: 0; margin-bottom: 1.5rem;">Request Venue Access & Resource Allocations</h3>
            
            <div class="field">
                <label for="event_id">Associated Event (Optional)</label>
                <select id="event_id" name="event_id" style="width: 100%;">
                    <option value="0">-- General Club Use --</option>
                    <?php foreach ($events as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= h($e['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="venue">Requested Venue Name</label>
                <input id="venue" name="venue" value="<?= h(old('venue')) ?>" required placeholder="e.g. Seminar Hall B, Audi-2, Lab 4">
                <?php if (isset($errors['venue'])): ?><div class="field-error"><?= h($errors['venue']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label for="resources_needed">Resources Required (Laptops, Projectors, Mics, Chairs, Internet, etc.)</label>
                <textarea id="resources_needed" name="resources_needed" rows="4" required placeholder="Specify items, quantities and technical support needed..."><?= h(old('resources_needed')) ?></textarea>
                <?php if (isset($errors['resources_needed'])): ?><div class="field-error"><?= h($errors['resources_needed']) ?></div><?php endif; ?>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="start_time">Reservation Start Time</label>
                    <input type="datetime-local" id="start_time" name="start_time" value="<?= h(old('start_time')) ?>" required>
                    <?php if (isset($errors['start_time'])): ?><div class="field-error"><?= h($errors['start_time']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label for="end_time">Reservation End Time</label>
                    <input type="datetime-local" id="end_time" name="end_time" value="<?= h(old('end_time')) ?>" required>
                    <?php if (isset($errors['end_time'])): ?><div class="field-error"><?= h($errors['end_time']) ?></div><?php endif; ?>
                </div>
            </div>

            <button class="button" type="submit" style="width: auto; min-width: 160px;">Submit Request</button>
        </form>

    <!-- TAB 3: SOCIAL MEDIA POST -->
    <?php if ($tab === 'social'): ?>
        <form method="post" action="?tab=social" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="social">
            
            <h3 style="margin-top: 0; margin-bottom: 1.5rem;">Request Social Media Posting Permission</h3>
            
            <div class="field">
                <label>Select Target Channels</label>
                <div style="display: flex; gap: 2rem; margin-top: 0.5rem; flex-wrap: wrap;">
                    <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="platforms[]" value="Instagram" class="checkbox-inline"> Instagram</label>
                    <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="platforms[]" value="LinkedIn" class="checkbox-inline"> LinkedIn</label>
                    <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="platforms[]" value="Twitter/X" class="checkbox-inline"> Twitter / X</label>
                    <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="platforms[]" value="Website Banner" class="checkbox-inline"> Club Website Portal</label>
                </div>
                <?php if (isset($errors['platforms'])): ?><div class="field-error" style="margin-top: 0.5rem;"><?= h($errors['platforms']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label for="caption">Post Caption & Hashtags Content</label>
                <textarea id="caption" name="caption" rows="5" required placeholder="Enter the exact caption text you want published..."><?= h(old('caption')) ?></textarea>
                <?php if (isset($errors['caption'])): ?><div class="field-error"><?= h($errors['caption']) ?></div><?php endif; ?>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="image">Attach Flyer / Creative Image (Optional)</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                    <?php if (isset($errors['image'])): ?><div class="field-error"><?= h($errors['image']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label for="schedule_time">Preferred Posting Schedule (Optional)</label>
                    <input type="datetime-local" id="schedule_time" name="schedule_time" value="<?= h(old('schedule_time')) ?>">
                </div>
            </div>

            <button class="button" type="submit" style="width: auto; min-width: 160px;">Submit Request</button>
        </form>

    <!-- TAB 4: CONTENT PUBLISHING -->
    <?php if ($tab === 'content'): ?>
        <form method="post" action="?tab=content" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="content">
            
            <h3 style="margin-top: 0; margin-bottom: 1.5rem;">Request Content/Blog Publishing Permission</h3>
            
            <div class="field">
                <label for="title">Title / Headline</label>
                <input id="title" name="title" value="<?= h(old('title')) ?>" required placeholder="e.g. Cybersecurity Essentials: A Student Perspective">
                <?php if (isset($errors['title'])): ?><div class="field-error"><?= h($errors['title']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label for="category">Content Category</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <option value="Blog Post">Blog Post / Article</option>
                    <option value="Club Newsletter">Monthly Club Newsletter</option>
                    <option value="Write-up">Write-up / Event Summary</option>
                    <option value="Cyber Threat Advisory">Cyber Threat Advisory</option>
                </select>
                <?php if (isset($errors['category'])): ?><div class="field-error"><?= h($errors['category']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label for="content_body">Content Body Markup / Text</label>
                <textarea id="content_body" name="content_body" rows="8" required placeholder="Write or paste your article draft content here..."><?= h(old('content_body')) ?></textarea>
                <?php if (isset($errors['content_body'])): ?><div class="field-error"><?= h($errors['content_body']) ?></div><?php endif; ?>
            </div>

            <button class="button" type="submit" style="width: auto; min-width: 160px;">Submit Request</button>
        </form>

    <!-- TAB 5: EXTERNAL COLLABORATION -->
    <?php if ($tab === 'collab'): ?>
        <form method="post" action="?tab=collab" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="collab">
            
            <h3 style="margin-top: 0; margin-bottom: 1.5rem;">Request Collaboration with External Entities</h3>
            
            <div class="field">
                <label for="partner_name">External Partner / Organization Name</label>
                <input id="partner_name" name="partner_name" value="<?= h(old('partner_name')) ?>" required placeholder="e.g. HackerOne Club, IIT Sec, Corporate Sponsor">
                <?php if (isset($errors['partner_name'])): ?><div class="field-error"><?= h($errors['partner_name']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label for="description">Collaboration Scope / Description</label>
                <textarea id="description" name="description" rows="5" required placeholder="Explain the partnership terms, target goals, deliverables, and event scope..."><?= h(old('description')) ?></textarea>
                <?php if (isset($errors['description'])): ?><div class="field-error"><?= h($errors['description']) ?></div><?php endif; ?>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="contact_person">Partner Representative Name</label>
                    <input id="contact_person" name="contact_person" value="<?= h(old('contact_person')) ?>" required placeholder="e.g. Jane Doe">
                    <?php if (isset($errors['contact_person'])): ?><div class="field-error"><?= h($errors['contact_person']) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label for="contact_email">Representative Email Address</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?= h(old('contact_email')) ?>" required placeholder="e.g. contact@partner.org">
                    <?php if (isset($errors['contact_email'])): ?><div class="field-error"><?= h($errors['contact_email']) ?></div><?php endif; ?>
                </div>
            </div>

            <button class="button" type="submit" style="width: auto; min-width: 160px;">Submit Request</button>
        </form>
    <?php endif; ?>

</div>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
