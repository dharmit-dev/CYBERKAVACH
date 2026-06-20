<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';
require_once BASE_PATH . '/app/Services/CertificateService.php';

// 1. Gate access to coordinators
$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);

if (request_method_is('POST')) {
    verify_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        flash('error', 'Template name is required.');
        redirect('certificates/templates.php');
    }

    $settings = [
        'name_x' => (int) ($_POST['name_x'] ?? 400),
        'name_y' => (int) ($_POST['name_y'] ?? 250),
        'name_size' => (int) ($_POST['name_size'] ?? 28),

        'event_x' => (int) ($_POST['event_x'] ?? 400),
        'event_y' => (int) ($_POST['event_y'] ?? 340),
        'event_size' => (int) ($_POST['event_size'] ?? 20),

        'code_x' => (int) ($_POST['code_x'] ?? 100),
        'code_y' => (int) ($_POST['code_y'] ?? 520),
        'code_size' => (int) ($_POST['code_size'] ?? 12),

        'date_x' => (int) ($_POST['date_x'] ?? 600),
        'date_y' => (int) ($_POST['date_y'] ?? 520),
        'date_size' => (int) ($_POST['date_size'] ?? 12),
    ];

    $result = CertificateService::saveTemplate($name, $_FILES['template_file'] ?? [], $settings);

    if ($result['ok']) {
        flash('success', 'Certificate template uploaded successfully.');
    } else {
        flash('error', $result['message']);
    }

    redirect('certificates/templates.php');
}

// Fetch existing templates
$stmt = db()->query('SELECT * FROM certificate_templates ORDER BY created_at DESC');
$templates = $stmt->fetchAll();

$title = 'Certificate Templates | ' . app_config('name');
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>Certificate Templates</h2>
    <div class="actions">
        <a href="<?= h(url('certificates/generate.php')) ?>" class="button">Batch Generation</a>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
    <!-- Upload Panel -->
    <div class="card">
        <h3>Upload New Template</h3>
        <p class="muted">Supported formats: PNG, JPG. Max size: 5MB.</p>

        <form method="post" enctype="multipart/form-data" style="margin-top: 1.5rem;" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="name">Template Name</label>
                <input id="name" name="name" class="form-control" placeholder="e.g. Workshop Certificate" required>
            </div>

            <div class="field">
                <label for="template_file">Background Image</label>
                <input id="template_file" type="file" name="template_file" accept="image/png,image/jpeg" required>
            </div>

            <h4 style="margin: 1.5rem 0 0.5rem 0; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;">Typography Alignment Coordinates</h4>
            <p class="muted" style="margin-bottom: 1rem;">Set coordinate offsets in pixels relative to top-left (0,0) of the template background image.</p>

            <div class="grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem;">
                <!-- Recipient Name -->
                <div class="field" style="margin: 0;">
                    <label>Name X</label>
                    <input type="number" name="name_x" value="400" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>Name Y</label>
                    <input type="number" name="name_y" value="250" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>Size (pt)</label>
                    <input type="number" name="name_size" value="28" class="form-control">
                </div>

                <!-- Event Name -->
                <div class="field" style="margin: 0;">
                    <label>Event X</label>
                    <input type="number" name="event_x" value="400" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>Event Y</label>
                    <input type="number" name="event_y" value="340" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>Size (pt)</label>
                    <input type="number" name="event_size" value="20" class="form-control">
                </div>

                <!-- ID/Code -->
                <div class="field" style="margin: 0;">
                    <label>ID X</label>
                    <input type="number" name="code_x" value="100" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>ID Y</label>
                    <input type="number" name="code_y" value="520" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>Size (pt)</label>
                    <input type="number" name="code_size" value="12" class="form-control">
                </div>

                <!-- Date -->
                <div class="field" style="margin: 0;">
                    <label>Date X</label>
                    <input type="number" name="date_x" value="600" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>Date Y</label>
                    <input type="number" name="date_y" value="520" class="form-control">
                </div>
                <div class="field" style="margin: 0;">
                    <label>Size (pt)</label>
                    <input type="number" name="date_size" value="12" class="form-control">
                </div>
            </div>

            <button type="submit" class="button" style="width: 100%; margin-top: 1rem;">Save Template</button>
        </form>
    </div>

    <!-- Active Templates List -->
    <div class="card">
        <h3>Existing Templates</h3>
        <p class="muted">Templates loaded for generation projects.</p>

        <div style="overflow-y: auto; max-height: 500px; margin-top: 1.5rem;">
            <?php if ($templates === []): ?>
                <div style="color: #888; padding: 2rem 0; text-align: center;">No templates uploaded yet.</div>
            <?php endif; ?>
            <?php foreach ($templates as $t): ?>
                <?php $settings = json_decode($t['text_settings'], true); ?>
                <div style="border: 1px solid #111; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; background: #fafafa;">
                    <h4 style="margin: 0 0 0.5rem 0;"><?= h($t['name']) ?></h4>
                    <div style="font-size: 0.85em; color: #555; margin-bottom: 0.5rem;">
                        Uploaded: <?= h($t['created_at']) ?>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="<?= h(url($t['file_path'])) ?>" target="_blank" class="button button-small button-outline" style="font-size: 0.8em; padding: 0.25rem 0.5rem;">Preview Image</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
