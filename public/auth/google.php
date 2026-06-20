<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('CYBERKAVACH_SESSION');
    session_start();
}

// Generate state token for CSRF mitigation
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$clientId = env('GOOGLE_CLIENT_ID', '');
$redirectUri = url('auth/google-callback.php');

if ($clientId !== '') {
    // Production Google OAuth Redirect
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'state' => $state,
    ]);
    header('Location: ' . $authUrl);
    exit;
}

// Dev Mode: Render Google Auth Sandbox
$db = db();
$mockUsersStmt = $db->query("
    SELECT u.email, u.full_name, r.role_name 
    FROM users u 
    INNER JOIN roles r ON r.id = u.role_id 
    WHERE u.status = 'active'
    LIMIT 6
");
$mockUsers = $mockUsersStmt->fetchAll();

$title = 'Google Auth Sandbox | ' . app_config('name');
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="form-card" style="max-width: 520px; margin: 2rem auto;">
    <h2>Google Auth Sandbox</h2>
    <p class="lede">Local developer environment simulation for Google Single Sign-On (SSO).</p>
    
    <div style="background: #e0f2fe; color: #0369a1; padding: 1rem; border-radius: 6px; font-size: 0.9em; margin-bottom: 1.5rem; line-height: 1.4;">
        <strong>Notice:</strong> <code>GOOGLE_CLIENT_ID</code> is empty in your <code>.env</code>. You are viewing the developer authentication simulation.
    </div>

    <!-- Select Existing User -->
    <h3 style="font-size: 1.1em; margin-bottom: 0.75rem; border-bottom: 1px solid var(--line); padding-bottom: 0.25rem;">Sign In as Existing Member</h3>
    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
        <?php foreach ($mockUsers as $mu): ?>
            <a href="<?= h(url('auth/google-callback.php?state=' . $state . '&code=mock_code&email=' . urlencode($mu['email']) . '&name=' . urlencode($mu['full_name']))) ?>" 
               style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border: 1px solid var(--line); border-radius: 6px; text-decoration: none; color: var(--ink); background: #f8f9fa; transition: all 0.2s;"
               onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='var(--primary)';" 
               onmouseout="this.style.background='#f8f9fa'; this.style.borderColor='var(--line)';">
                <div>
                    <strong><?= h($mu['full_name']) ?></strong><br>
                    <span class="muted" style="font-size: 0.85em;"><?= h($mu['email']) ?></span>
                </div>
                <span class="badge" style="background: #edf6f8; color: var(--primary-dark); font-size: 0.8em;"><?= h($mu['role_name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Register New User Simulation -->
    <h3 style="font-size: 1.1em; margin-bottom: 0.75rem; border-bottom: 1px solid var(--line); padding-bottom: 0.25rem;">Provision New User via Google</h3>
    <form method="get" action="<?= h(url('auth/google-callback.php')) ?>" style="display: flex; flex-direction: column; gap: 1rem; margin: 0;">
        <input type="hidden" name="state" value="<?= h($state) ?>">
        <input type="hidden" name="code" value="mock_code">

        <div class="field">
            <label for="new_email">Mock Google Email</label>
            <input type="email" id="new_email" name="email" required placeholder="e.g. fresh_user@gmail.com">
        </div>

        <div class="field">
            <label for="new_name">Mock Full Name</label>
            <input type="text" id="new_name" name="name" required placeholder="e.g. Fresh User">
        </div>

        <button class="button" type="submit" style="background: #0f172a;">Simulate Google Register</button>
    </form>
    
    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="<?= h(url('login.php')) ?>" class="muted" style="font-size: 0.9em; text-decoration: none;">Back to login form</a>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
