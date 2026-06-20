<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';

// Ensure session is started for rate limiting
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('CYBERKAVACH_SESSION');
    session_start();
}

$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$certificate = null;
$error = null;
$isTampered = false;

if ($code !== '') {
    // 1. Bonus Security: Session-based Rate Limiting (max 30 searches per 10 minutes)
    if (empty($_SESSION['verify_rate_limit_time']) || time() - $_SESSION['verify_rate_limit_time'] > 600) {
        $_SESSION['verify_rate_limit_time'] = time();
        $_SESSION['verify_rate_limit_count'] = 1;
    } else {
        $_SESSION['verify_rate_limit_count']++;
        if ($_SESSION['verify_rate_limit_count'] > 30) {
            $error = 'Too many verification attempts. Please wait 10 minutes before searching again.';
        }
    }

    if (!$error) {
        // 2. Fetch certificate from database
        $stmt = db()->prepare('
            SELECT c.*, ct.name AS template_name, e.title AS event_title
            FROM certificates c
            INNER JOIN certificate_templates ct ON ct.id = c.template_id
            LEFT JOIN events e ON e.id = c.event_id
            WHERE c.certificate_code = :code
            LIMIT 1
        ');
        $stmt->execute(['code' => $code]);
        $certificate = $stmt->fetch();

        if ($certificate) {
            // 3. Cryptographic Tamper Validation using constant-time comparison
            $secretKey = env('COORDINATOR_BOOTSTRAP_KEY', 'cyberkavach-secret');
            $expectedSignature = hash_hmac(
                'sha256',
                $certificate['certificate_code'] . '|' . $certificate['recipient_name'] . '|' . $certificate['recipient_email'],
                $secretKey
            );

            if (!hash_equals($expectedSignature, $certificate['cryptographic_signature'])) {
                $isTampered = true;
                $error = 'TAMPER WARNING: The cryptographic signature for this certificate is invalid. This certificate may have been forged or modified.';
            }
        } else {
            $error = 'Certificate code not found. Please double-check the identifier.';
        }
    }
}

$title = 'Verify Certificate | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<form class="form-card" method="get" novalidate style="max-width: 480px; margin: 2rem auto; border: 1px solid #111; padding: 2rem; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
    <h2 style="text-align: center; margin-top: 0;">Verify Certificate</h2>
    <p class="lede" style="text-align: center; margin-bottom: 1.5rem;">Enter the certificate code (e.g. CK-CERT-XXXX) to verify its validity and cryptographic integrity.</p>

    <div class="field">
        <label for="code">Certificate ID / Code</label>
        <input id="code" name="code" value="<?= h($code) ?>" placeholder="CK-CERT-XXXXXXXXXXXXXXXX" required style="width: 100%; box-sizing: border-box;">
    </div>

    <button class="button" type="submit" style="width: 100%;">Verify Authenticity</button>

    <div class="link-row" style="text-align: center; margin-top: 1rem;">
        <a href="<?= h(url('login.php')) ?>">Back to Login</a>
    </div>
</form>

<?php if ($code !== ''): ?>
    <div class="form-card" style="max-width: 480px; margin: 2rem auto; border: 1px solid #111; padding: 2rem; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <h3>Verification Status</h3>

        <?php if ($error): ?>
            <div class="alert <?= $isTampered ? 'alert-error' : 'alert-info' ?>" style="margin-bottom: 0; word-break: break-word;">
                <strong><?= $isTampered ? 'Security Warning!' : 'Info' ?></strong><br>
                <?= h($error) ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                <strong>Verified Certificate!</strong> The cryptographic signature matches. This certificate is authentic and unmodified.
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; text-align: left;">
                <tr style="border-bottom: 1px solid #eee;">
                    <th style="padding: 0.5rem 0;">Recipient Name</th>
                    <td style="padding: 0.5rem 0;"><?= h($certificate['recipient_name']) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <th style="padding: 0.5rem 0;">Recipient Email</th>
                    <td style="padding: 0.5rem 0;"><?= h($certificate['recipient_email']) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <th style="padding: 0.5rem 0;">Event Title</th>
                    <td style="padding: 0.5rem 0;"><?= h($certificate['event_title'] ?? 'General Certificate') ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <th style="padding: 0.5rem 0;">Issue Date</th>
                    <td style="padding: 0.5rem 0;"><?= h(date('F d, Y', strtotime($certificate['created_at']))) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <th style="padding: 0.5rem 0;">Certificate Code</th>
                    <td style="padding: 0.5rem 0; font-family: monospace; font-weight: bold;"><?= h($certificate['certificate_code']) ?></td>
                </tr>
            </table>

            <div style="text-align: center;">
                <h4 style="margin-bottom: 0.75rem;">Certificate Preview</h4>
                <img src="<?= h(url($certificate['file_path'])) ?>" alt="Certificate Image" style="max-width: 100%; border: 1px solid #ddd; border-radius: 4px; padding: 4px; margin-bottom: 1rem;">
                <a href="<?= h(url($certificate['file_path'])) ?>" download class="button button-small" style="display: inline-block;">Download Certificate (PNG)</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
