<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';
require_once BASE_PATH . '/app/Services/CertificateService.php';

// Gated access to coordinators
$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);

$templates = db()->query('SELECT id, name FROM certificate_templates ORDER BY name ASC')->fetchAll();
$events = db()->query("SELECT id, title FROM events WHERE status IN ('approved', 'published', 'completed') ORDER BY event_date DESC")->fetchAll();

$generationResult = null;

if (request_method_is('POST')) {
    verify_csrf();

    $templateId = (int) ($_POST['template_id'] ?? 0);
    $sourceType = input_string('source_type');
    $eventId = (int) ($_POST['event_id'] ?? 0);

    try {
        if ($templateId === 0) {
            throw new RuntimeException('Please select a certificate template.');
        }

        $recipients = [];
        $eventTitle = 'CyberKavach Event';

        if ($eventId > 0) {
            $ev = Event::findById($eventId);
            if ($ev) {
                $eventTitle = $ev['title'];
            }
        }

        if ($sourceType === 'event') {
            if ($eventId === 0) {
                throw new RuntimeException('Please select an event to load participants.');
            }

            // Fetch participants
            $stmt = db()->prepare('
                SELECT er.user_id, users.full_name AS name, users.email
                FROM event_registrations er
                INNER JOIN users ON users.id = er.user_id
                WHERE er.event_id = :event_id AND er.status = "registered"
            ');
            $stmt->execute(['event_id' => $eventId]);
            $rows = $stmt->fetchAll();

            foreach ($rows as $r) {
                $recipients[] = [
                    'name' => $r['name'],
                    'email' => $r['email'],
                    'user_id' => $r['user_id'],
                    'event_title' => $eventTitle,
                ];
            }

            if ($recipients === []) {
                throw new RuntimeException('No registered participants found for the selected event.');
            }
        } else {
            // Process CSV Upload
            $file = $_FILES['csv_file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please upload a valid CSV file.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            // Allow text/csv, text/plain or application/vnd.ms-excel (CSV variations)
            if (!in_array($mime, ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/octet-stream'], true)) {
                throw new RuntimeException('Invalid file type. Please upload a plain CSV spreadsheet.');
            }

            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                throw new RuntimeException('Unable to open uploaded file.');
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                throw new RuntimeException('CSV file is empty.');
            }

            // Clean headers
            $headers = array_map(function ($h) {
                return strtolower(trim($h));
            }, $headers);

            $nameIdx = array_search('name', $headers, true);
            $emailIdx = array_search('email', $headers, true);

            if ($nameIdx === false || $emailIdx === false) {
                fclose($handle);
                throw new RuntimeException('CSV must contain both a "name" and an "email" column.');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) <= max($nameIdx, $emailIdx)) {
                    continue;
                }
                $recipients[] = [
                    'name' => trim($row[$nameIdx]),
                    'email' => trim($row[$emailIdx]),
                    'event_title' => $eventTitle,
                ];
            }
            fclose($handle);

            if ($recipients === []) {
                throw new RuntimeException('No valid participant entries found in the CSV.');
            }
        }

        // Run bulk generation service
        $generationResult = CertificateService::generateBatch($templateId, $eventId > 0 ? $eventId : null, $recipients);

        if ($generationResult['ok']) {
            flash('success', 'Batch certificate project completed successfully.');
        } else {
            flash('error', $generationResult['message']);
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$title = 'Batch Certificate Generation | ' . app_config('name');
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>Batch Certificate Generator</h2>
    <div class="actions">
        <a href="<?= h(url('certificates/templates.php')) ?>" class="button button-outline">Manage Templates</a>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
    <!-- Generation Project Form -->
    <div class="card">
        <h3>Create Certificate Batch</h3>
        <p class="muted">Generate and email certificates in bulk using a template.</p>

        <form method="post" enctype="multipart/form-data" style="margin-top: 1.5rem;" id="generator_form" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="template_id">Select Template</label>
                <select id="template_id" name="template_id" class="form-control" required>
                    <option value="">-- Choose Template --</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= h((string) $t['id']) ?>"><?= h($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="source_type">Participant Source</label>
                <select id="source_type" name="source_type" class="form-control" required>
                    <option value="event">Load Checked-in/Registered Event Participants</option>
                    <option value="csv">Upload CSV Spreadsheet</option>
                </select>
            </div>

            <!-- Event selection field -->
            <div class="field" id="event_source_field">
                <label for="event_id">Select Event Context</label>
                <select id="event_id" name="event_id" class="form-control">
                    <option value="0">-- General (Non-Event specific) --</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= h((string) $ev['id']) ?>"><?= h($ev['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CSV upload field -->
            <div class="field" id="csv_source_field" style="display: none;">
                <label for="csv_file">Upload Spreadsheet (CSV)</label>
                <input id="csv_file" type="file" name="csv_file" accept=".csv" class="form-control">
                <p class="muted" style="margin-top: 4px;">Spreadsheet must contain headers "name" and "email".</p>
            </div>

            <button type="submit" class="button" style="width: 100%; margin-top: 1rem;">Run Batch Generation</button>
        </form>
    </div>

    <!-- Results Panel -->
    <div class="card">
        <h3>Batch Project Status</h3>
        <p class="muted">Results will render upon batch completion.</p>

        <div style="margin-top: 1.5rem;">
            <?php if ($generationResult && $generationResult['ok']): ?>
                <div class="alert alert-success">
                    <strong>Batch complete!</strong> Generated <?= $generationResult['generated_count'] ?> certificates.
                </div>
                
                <div style="margin-top: 1.5rem; text-align: center; border: 1px solid #111; padding: 1.5rem; border-radius: 4px; background: #fafafa;">
                    <h4>Download ZIP Package</h4>
                    <p class="muted" style="font-size: 0.9em; margin-bottom: 1rem;">Export copy containing all rendered certificate image files.</p>
                    <a href="<?= h($generationResult['zip_url']) ?>" class="button">Download Batch ZIP</a>
                </div>

                <?php if ($generationResult['errors'] !== []): ?>
                    <h4 style="margin-top: 1.5rem; color: #dc3545;">Errors encountered:</h4>
                    <ul style="color: #c92a2a; font-size: 0.9em; padding-left: 1.25rem;">
                        <?php foreach ($generationResult['errors'] as $err): ?>
                            <li><?= h($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <div style="color: #888; padding: 3rem 0; text-align: center; border: 1px dashed #ccc; border-radius: 4px;">
                    Awaiting generation execution parameters...
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sourceType = document.getElementById('source_type');
    const eventField = document.getElementById('event_source_field');
    const csvField = document.getElementById('csv_source_field');

    if (sourceType) {
        sourceType.addEventListener('change', () => {
            if (sourceType.value === 'event') {
                eventField.style.display = 'block';
                csvField.style.display = 'none';
            } else {
                eventField.style.display = 'none';
                csvField.style.display = 'block';
            }
        });
    }
});
</script>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
