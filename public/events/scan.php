<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Services/AttendanceService.php';

$user = require_auth();

if (!user_has_permission($user, 'events.attendance')) {
    flash('error', 'Unauthorized access. You do not have permission to scan attendance.');
    redirect('dashboard.php');
}

if (request_method_is('POST')) {
    header('Content-Type: application/json');
    verify_csrf();

    $eventId = (int) input_string('event_id');
    $payload = input_string('qr_payload');
    $type = input_string('type') === 'check_out' ? 'check_out' : 'check_in';

    // 1. Try to find a team with this QR payload
    $stmt = db()->prepare('SELECT id FROM event_teams WHERE qr_payload = :payload AND event_id = :event_id LIMIT 1');
    $stmt->execute(['payload' => $payload, 'event_id' => $eventId]);
    $team = $stmt->fetch();

    if ($team) {
        $teamId = (int) $team['id'];
        $stmt = db()->prepare('SELECT user_id FROM event_team_members WHERE team_id = :team_id');
        $stmt->execute(['team_id' => $teamId]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $successCount = 0;
        $lastError = '';

        foreach ($members as $memberId) {
            $memberId = (int) $memberId;
            $result = $type === 'check_in' 
                ? AttendanceService::checkIn($eventId, $memberId, $user, $teamId)
                : AttendanceService::checkOut($eventId, $memberId, $user, $teamId);
            
            if ($result['ok']) {
                $successCount++;
            } else {
                $lastError = $result['message'];
            }
        }

        if ($successCount > 0) {
            echo json_encode(['ok' => true, 'message' => "Team processed: $successCount members."]);
        } else {
            echo json_encode(['ok' => false, 'message' => $lastError ?: 'Already processed.']);
        }
        exit;
    }

    // 2. Fallback: If payload is numeric, treat it as an individual user ID
    if (is_numeric($payload)) {
        $userId = (int) $payload;
        $result = $type === 'check_in' 
            ? AttendanceService::checkIn($eventId, $userId, $user)
            : AttendanceService::checkOut($eventId, $userId, $user);
        
        echo json_encode($result);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Invalid QR Code.']);
    exit;
}

$eventId = (int) ($_GET['event_id'] ?? 0);
$event = $eventId > 0 ? Event::findById($eventId) : null;

if (!$event || !in_array($event['status'], ['approved', 'published', 'completed'], true)) {
    flash('error', 'Invalid or inactive event. Event might be closed.');
    redirect('events/manage.php'); // Fallback to a safe page
}

$title = 'Scan Attendance - ' . $event['title'];
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<div class="dashboard-header">
    <h2>Scan Attendance: <?= h($event['title']) ?></h2>
    <div class="actions">
        <!-- Back button, assuming the coordinator came from event management -->
        <a href="<?= h(url('events/manage.php')) ?>" class="button button-outline">Cancel</a>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
    <!-- Scanner Panel -->
    <div class="card">
        <h3>Scanner</h3>
        
        <div class="field">
            <label for="scan_type">Mode</label>
            <select id="scan_type" class="form-control">
                <option value="check_in">Check-In</option>
                <option value="check_out">Check-Out</option>
            </select>
        </div>

        <div id="reader" style="width: 100%; min-height: 300px; background: #000; border-radius: 8px; overflow: hidden; margin-bottom: 1rem;"></div>
        
        <div id="scan-result" class="alert" style="display: none;"></div>
    </div>

    <!-- Recent Scans Panel -->
    <div class="card">
        <h3>Recent Scans (This Session)</h3>
        <ul id="recent-scans" style="list-style: none; padding: 0; margin: 0; max-height: 400px; overflow-y: auto;">
            <li id="no-scans-msg" style="color: #666; padding: 0.5rem 0;">No scans yet.</li>
        </ul>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const html5QrCode = new Html5Qrcode("reader");
    const resultDiv = document.getElementById('scan-result');
    const recentScans = document.getElementById('recent-scans');
    const scanType = document.getElementById('scan_type');
    const noScansMsg = document.getElementById('no-scans-msg');
    const csrfToken = '<?= h(csrf_token()) ?>';
    const eventId = <?= $eventId ?>;
    
    let isProcessing = false;

    const qrCodeSuccessCallback = (decodedText, decodedResult) => {
        if (isProcessing) return;
        isProcessing = true;
        
        resultDiv.style.display = 'block';
        resultDiv.className = 'alert alert-info';
        resultDiv.innerText = 'Processing QR: ' + decodedText.substring(0, 15) + '...';

        const formData = new FormData();
        formData.append('_csrf', csrfToken);
        formData.append('event_id', eventId);
        formData.append('qr_payload', decodedText);
        formData.append('type', scanType.value);

        fetch('<?= h(url('events/scan.php')) ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultDiv.className = data.ok ? 'alert alert-success' : 'alert alert-error';
            resultDiv.innerText = data.message;
            
            if (noScansMsg) {
                noScansMsg.style.display = 'none';
            }
            
            const li = document.createElement('li');
            li.style.padding = '0.5rem 0';
            li.style.borderBottom = '1px solid #eee';
            
            const time = new Date().toLocaleTimeString();
            const action = scanType.value === 'check_in' ? 'IN' : 'OUT';
            const color = data.ok ? 'green' : 'red';
            
            li.innerHTML = `<span style="color: #888; font-size: 0.85em; font-family: monospace;">[${time}]</span> <strong style="display:inline-block; width:40px;">${action}</strong> <span style="color: ${color}">${data.message}</span>`;
            
            recentScans.insertBefore(li, recentScans.firstChild);
            
            // Limit to 10 recent scans
            while (recentScans.children.length > 10) {
                recentScans.removeChild(recentScans.lastChild);
            }

            // Cooldown before allowing next scan
            setTimeout(() => { 
                isProcessing = false; 
                resultDiv.style.display = 'none';
            }, 2500);
        })
        .catch(error => {
            resultDiv.className = 'alert alert-error';
            resultDiv.innerText = 'Network error or server error.';
            setTimeout(() => { isProcessing = false; }, 2500);
        });
    };

    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    
    html5QrCode.start(
        { facingMode: "environment" }, 
        config, 
        qrCodeSuccessCallback
    ).catch(err => {
        resultDiv.style.display = 'block';
        resultDiv.className = 'alert alert-error';
        resultDiv.innerText = 'Unable to access camera. Please grant camera permissions.';
    });
});
</script>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
