<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/guest.php';

require_guest();

$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));

if (request_method_is('POST')) {
    verify_csrf();

    $otp = input_string('otp');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');

    if ($email === '') {
        back_with_errors(['email' => 'Email address is required.'], ['email' => $email]);
    }

    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        back_with_errors(['otp' => 'Enter the 6-digit OTP.'], ['email' => $email]);
    }

    $result = AuthService::resetPassword($email, $otp, $password, $passwordConfirm);

    if (!$result['ok']) {
        back_with_errors(['otp' => $result['message']], ['email' => $email]);
    }

    flash('success', 'Password updated. You can login now.');
    redirect('login.php');
}

$title = 'Reset Password | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>
<form class="form-card" method="post" action="<?= h(url('reset-password.php')) ?>" novalidate>
    <h2>Choose new password</h2>
    <p class="lede">Enter the OTP sent to your email to verify and reset your password.</p>
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= h(old('email', $email)) ?>" required>
        <?php if (isset($pageErrors['email'])): ?><div class="field-error"><?= h($pageErrors['email']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="otp">6-digit OTP</label>
        <input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
        <?php if (isset($pageErrors['otp'])): ?><div class="field-error"><?= h($pageErrors['otp']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="password">New password</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
        <?php if (isset($pageErrors['password'])): ?><div class="field-error"><?= h($pageErrors['password']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
    </div>

    <button class="button" type="submit">Update password</button>

    <div class="link-row">
        <a href="<?= h(url('login.php')) ?>">Back to login</a>
    </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
