<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';

$roles = User::coordinatorRoles();
$errors = [];

if (request_method_is('POST')) {
    verify_csrf();

    $setupKey = input_string('setup_key');
    $configuredKey = trim((string) env('COORDINATOR_BOOTSTRAP_KEY', ''));
    $hasCoordinators = User::activeCoordinatorCount() > 0;

    if ($hasCoordinators) {
        if ($configuredKey === '') {
            $errors['setup_key'] = 'Bootstrap key is not configured in .env.';
        } elseif ($setupKey === '' || !hash_equals($configuredKey, $setupKey)) {
            $errors['setup_key'] = 'Valid bootstrap key is required after coordinators exist.';
        }
    }

    $roleIds = array_map('intval', array_column($roles, 'id'));
    $roleId = (int) input_string('role_id');

    if (!in_array($roleId, $roleIds, true)) {
        $errors['role_id'] = 'Select a coordinator role.';
    }

    $email = input_string('email');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required.';
    } elseif (User::findByEmail($email)) {
        $errors['email'] = 'Email already exists.';
    }

    if (strlen(input_string('full_name')) < 2) {
        $errors['full_name'] = 'Full name is required.';
    }

    if (!preg_match('/^[0-9+\-\s]{8,20}$/', input_string('phone'))) {
        $errors['phone'] = 'Valid phone is required.';
    }

    if (strlen((string) ($_POST['password'] ?? '')) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($errors === []) {
        $userId = User::createCoordinator([
            'role_id' => $roleId,
            'full_name' => input_string('full_name'),
            'email' => $email,
            'phone' => input_string('phone'),
            'password' => (string) $_POST['password'],
        ]);

        AuditService::record(
            'coordinator_bootstrapped',
            'auth',
            $userId,
            'users',
            $userId
        );

        flash('success', 'Coordinator account created.');
        redirect('login.php');
    }
}

$title = 'Coordinator Bootstrap | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<form class="form-card" method="post" action="<?= h(url('admin/bootstrap-coordinator.php')) ?>" novalidate>
    <h2>Create coordinator</h2>
    <p class="lede">Use this only for initial setup or controlled coordinator creation.</p>

    <?= csrf_field() ?>

    <div class="field">
        <label for="setup_key">Bootstrap key</label>
        <input id="setup_key" name="setup_key" autocomplete="off">
        <?php if (isset($errors['setup_key'])): ?>
            <div class="field-error"><?= h($errors['setup_key']) ?></div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="role_id">Coordinator role</label>
        <select id="role_id" name="role_id" required>
            <option value="">Select role</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= h((string) $role['id']) ?>">
                    <?= h($role['role_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['role_id'])): ?>
            <div class="field-error"><?= h($errors['role_id']) ?></div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" required>
        <?php if (isset($errors['full_name'])): ?>
            <div class="field-error"><?= h($errors['full_name']) ?></div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" required>
        <?php if (isset($errors['email'])): ?>
            <div class="field-error"><?= h($errors['email']) ?></div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" required>
        <?php if (isset($errors['phone'])): ?>
            <div class="field-error"><?= h($errors['phone']) ?></div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        <?php if (isset($errors['password'])): ?>
            <div class="field-error"><?= h($errors['password']) ?></div>
        <?php endif; ?>
    </div>

    <button class="button" type="submit">Create coordinator</button>
</form>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
