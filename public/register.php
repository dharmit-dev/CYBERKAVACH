<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/guest.php';

require_guest();

$roles = User::allowedPublicRegistrationRoles();

if (request_method_is('POST')) {
    verify_csrf();

    $input = [
        'full_name' => input_string('full_name'),
        'email' => input_string('email'),
        'phone' => input_string('phone'),
        'role_id' => (int) input_string('role_id'),
        'password' => (string) ($_POST['password'] ?? ''),
        'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
    ];

    $result = AuthService::register($input);

    if (!$result['ok']) {
        $safeOld = $input;
        unset($safeOld['password'], $safeOld['password_confirmation']);
        back_with_errors($result['errors'], $safeOld);
    }

    flash('success', 'Registration received. Check your email for the verification OTP.');
    redirect('verify-email.php');
}

$title = 'Register | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>
<form class="form-card" method="post" action="<?= h(url('register.php')) ?>" novalidate>
    <h2>Create account</h2>
    <p class="lede">Public registration is available for club members and student participants.</p>
    <?= csrf_field() ?>

    <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= h(old('full_name')) ?>" autocomplete="name" required>
        <?php if (isset($pageErrors['full_name'])): ?><div class="field-error"><?= h($pageErrors['full_name']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= h(old('email')) ?>" autocomplete="email" required>
        <?php if (isset($pageErrors['email'])): ?><div class="field-error"><?= h($pageErrors['email']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= h(old('phone')) ?>" autocomplete="tel" required>
        <?php if (isset($pageErrors['phone'])): ?><div class="field-error"><?= h($pageErrors['phone']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="role_id">Register as</label>
        <select id="role_id" name="role_id" required>
            <option value="">Select role</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= h((string) $role['id']) ?>" <?= old('role_id') === (string) $role['id'] ? 'selected' : '' ?>>
                    <?= h($role['role_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($pageErrors['role_id'])): ?><div class="field-error"><?= h($pageErrors['role_id']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
        <?php if (isset($pageErrors['password'])): ?><div class="field-error"><?= h($pageErrors['password']) ?></div><?php endif; ?>
    </div>

    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
        <?php if (isset($pageErrors['password_confirmation'])): ?><div class="field-error"><?= h($pageErrors['password_confirmation']) ?></div><?php endif; ?>
    </div>

    <button class="button" type="submit">Register</button>

    <div class="link-row">
        <a href="<?= h(url('login.php')) ?>">Already have an account?</a>
    </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
