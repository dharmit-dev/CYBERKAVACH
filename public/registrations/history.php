<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();
$registrations = EventRegistration::historyForUser((int) $user['id']);
$title = 'Registration History | ' . app_config('name');
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>
<section class="panel">
    <h2>My Registrations</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Team</th>
                    <th>Status</th>
                    <th>Check-in Pass</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($registrations === []): ?>
                <tr>
                    <td colspan="6">No registrations yet.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($registrations as $registration): ?>
                <tr>
                    <td><a href="<?= h(url('events/show.php?slug=' . urlencode($registration['slug']))) ?>"><?= h($registration['title']) ?></a></td>
                    <td><?= h($registration['event_date']) ?></td>
                    <td><?= h($registration['registration_type']) ?></td>
                    <td><?= h($registration['team_name'] ?? '-') ?> <?= !empty($registration['team_identifier']) ? '(' . h($registration['team_identifier']) . ')' : '' ?></td>
                    <td><?= h($registration['status']) ?></td>
                    <td>
                        <?php if ($registration['registration_type'] === 'team' && !empty($registration['qr_path'])): ?>
                            <button class="button button-small" onclick="viewQr('<?= h(url($registration['qr_path'])) ?>', '<?= h(addslashes($registration['team_name'])) ?>')">View QR</button>
                        <?php else: ?>
                            <span class="muted" style="font-size: 0.9em;">ID: #<?= h((string) $user['id']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- QR Modal -->
<div id="qrModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="card" style="background-color: #fff; margin: auto; padding: 2rem; border: 1px solid #111; width: 320px; text-align: center; border-radius: 6px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); position: relative;">
        <span onclick="closeQrModal()" style="position: absolute; right: 15px; top: 10px; font-size: 24px; font-weight: bold; cursor: pointer; color: #666;">&times;</span>
        <h3 id="modalTeamName" style="margin-top: 0; font-size: 1.25em;">Team QR Code</h3>
        <div style="margin: 1.5rem 0; display: flex; justify-content: center;">
            <img id="modalQrImg" src="" alt="Team QR Code" style="width: 200px; height: 200px; border: 1px solid #eee; border-radius: 4px; padding: 4px; background: #fff;">
        </div>
        <a id="modalDownloadBtn" href="" download class="button button-small" style="display: inline-block; width: 100%; box-sizing: border-box;">Download QR Code</a>
    </div>
</div>

<script>
function viewQr(qrUrl, teamName) {
    document.getElementById('modalTeamName').innerText = teamName + ' QR Code';
    document.getElementById('modalQrImg').src = qrUrl;
    document.getElementById('modalDownloadBtn').href = qrUrl;
    document.getElementById('modalDownloadBtn').download = teamName.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-qr.svg';
    document.getElementById('qrModal').style.display = 'flex';
}

function closeQrModal() {
    document.getElementById('qrModal').style.display = 'none';
}

// Close modal if clicked outside
window.onclick = function(event) {
    const modal = document.getElementById('qrModal');
    if (event.target == modal) {
        closeQrModal();
    }
}
</script>
<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
