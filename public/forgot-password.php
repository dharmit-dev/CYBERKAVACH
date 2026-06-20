<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/guest.php';

require_guest();

if (request_method_is('POST')) {
    verify_csrf();

    $email = input_string('email');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        back_with_errors(['email' => 'Enter a valid email address.'], ['email' => $email]);
    }

    AuthService::sendPasswordReset($email);
    flash('success', 'If the email exists, a password reset OTP has been sent.');
    redirect('reset-password.php?email=' . urlencode($email));
}

$title = 'Forgot Password | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>
<form class="form-card" method="post" action="<?= h(url('forgot-password.php')) ?>" novalidate>
    <h2>Reset password</h2>
    <p class="lede">Enter your registered email address.</p>
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= h(old('email')) ?>" autocomplete="email" required>
        <?php if (isset($pageErrors['email'])): ?><div class="field-error"><?= h($pageErrors['email']) ?></div><?php endif; ?>
    </div>

    <button class="button" type="submit">Send OTP</button>

    <div class="link-row">
        <a href="<?= h(url('login.php')) ?>">Back to login</a>
    </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
