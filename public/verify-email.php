<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/guest.php';

require_guest();

$pendingUserId = (int) ($_SESSION['pending_verification_user_id'] ?? 0);
$pendingUser = $pendingUserId > 0 ? User::findById($pendingUserId) : null;

if (!$pendingUser || $pendingUser['status'] !== 'pending_email') {
    flash('error', 'No pending email verification was found.');
    redirect('login.php');
}

if (request_method_is('POST')) {
    verify_csrf();

    $otp = input_string('otp');

    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        back_with_errors(['otp' => 'Enter the 6-digit OTP.']);
    }

    if (!OtpService::verify((int) $pendingUser['id'], $otp, 'email_verification')) {
        AuditService::record('email_verification_failed', 'auth', (int) $pendingUser['id'], 'users', (int) $pendingUser['id']);
        back_with_errors(['otp' => 'Invalid or expired OTP.']);
    }

    User::verifyEmailAndMarkPendingApproval((int) $pendingUser['id']);
    ApprovalService::submit(
        'user_account_approval',
        'user',
        (int) $pendingUser['id'],
        (int) $pendingUser['id'],
        'Account approval: ' . $pendingUser['full_name'],
        'Email verified by applicant. Account is ready for coordinator review.'
    );
    AuditService::record('email_verified', 'auth', (int) $pendingUser['id'], 'users', (int) $pendingUser['id']);

    unset($_SESSION['pending_verification_user_id']);
    flash('success', 'Email verified. Your account is now pending coordinator approval.');
    redirect('login.php');
}

$title = 'Verify Email | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>
<form class="form-card" method="post" action="<?= h(url('verify-email.php')) ?>" novalidate>
    <h2>Verify email</h2>
    <p class="lede">Enter the OTP sent to <?= h($pendingUser['email']) ?>.</p>
    <?= csrf_field() ?>

    <div class="field">
        <label for="otp">6-digit OTP</label>
        <input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
        <?php if (isset($pageErrors['otp'])): ?><div class="field-error"><?= h($pageErrors['otp']) ?></div><?php endif; ?>
    </div>

    <button class="button" type="submit">Verify email</button>

    <div class="link-row">
        <form method="post" action="<?= h(url('resend-otp.php')) ?>">
            <?= csrf_field() ?>
            <button class="link-button" type="submit">Resend OTP</button>
        </form>
        <a href="<?= h(url('login.php')) ?>">Back to login</a>
    </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
