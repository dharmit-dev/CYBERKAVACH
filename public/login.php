<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/guest.php';

require_guest();

if (request_method_is('POST')) {
    verify_csrf();

    $email = input_string('email');
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $_SESSION['_old']['email'] = $email;
        flash('error', 'Enter a valid email and password.');
        redirect('login.php');
    }

    $result = AuthService::login($email, $password);

    if (!$result['ok']) {
        $_SESSION['_old']['email'] = $email;
        flash('error', $result['message']);

        if (!empty($result['verify_email'])) {
            redirect('verify-email.php');
        }

        redirect('login.php');
    }

    redirect(role_dashboard_path($result['user']['role_key'] ?? null));
}

$title = 'Login | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>
<form class="form-card" method="post" action="<?= h(url('login.php')) ?>" novalidate>
    <h2>Login</h2>
    <p class="lede">Access your CyberKavach club dashboard.</p>
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= h(old('email')) ?>" autocomplete="email" required>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
    </div>

    <button class="button" type="submit">Login</button>

    <div class="link-row">
        <a href="<?= h(url('register.php')) ?>">Create account</a>
        <a href="<?= h(url('forgot-password.php')) ?>">Forgot password?</a>
    </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
