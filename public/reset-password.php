<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/guest.php';

require_guest();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if ($token === '') {
    flash('error', 'Password reset token is missing.');
    redirect('login.php');
}

if (request_method_is('POST')) {
    verify_csrf();

    $result = AuthService::resetPassword(
        $token,
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['password_confirmation'] ?? '')
    );

    if (!$result['ok']) {
        back_with_errors(['password' => $result['message']]);
    }

    flash('success', 'Password updated. You can login now.');
    redirect('login.php');
}

$title = 'Reset Password | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>
<form class="form-card" method="post" action="<?= h(url('reset-password.php')) ?>" novalidate>
    <h2>Choose new password</h2>
    <p class="lede">Use at least 8 characters.</p>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">

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
</form>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
