<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/guest.php';

require_guest();

if (!request_method_is('POST')) {
    redirect('verify-email.php');
}

verify_csrf();

$pendingUserId = (int) ($_SESSION['pending_verification_user_id'] ?? 0);
$user = $pendingUserId > 0 ? User::findById($pendingUserId) : null;

if (!$user || $user['status'] !== 'pending_email') {
    flash('error', 'No pending email verification was found.');
    redirect('login.php');
}

$lastResend = (int) ($_SESSION['last_otp_resend_time'] ?? 0);
if (time() - $lastResend < 60) {
    flash('error', 'Please wait 60 seconds before requesting another OTP.');
    redirect('verify-email.php');
}

try {
    $otp = OtpService::create((int) $user['id'], $user['email'], 'email_verification');
    MailService::sendOtp($user['email'], $user['full_name'], $otp, 'email verification');
    AuditService::record('email_otp_resent', 'auth', (int) $user['id'], 'users', (int) $user['id']);

    $_SESSION['last_otp_resend_time'] = time();

    flash('success', 'A new OTP has been sent.');
} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
}
redirect('verify-email.php');
