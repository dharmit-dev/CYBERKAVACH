<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('CYBERKAVACH_SESSION');
    session_start();
}

// 1. CSRF Verification of State token
$sessionState = $_SESSION['oauth_state'] ?? null;
$queryState = $_GET['state'] ?? null;

if (!$sessionState || !$queryState || $sessionState !== $queryState) {
    flash('error', 'OAuth CSRF verification failed. Please try again.');
    redirect('login.php');
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'] ?? '';
if ($code === '') {
    flash('error', 'Google authorization code missing.');
    redirect('login.php');
}

$clientId = env('GOOGLE_CLIENT_ID', '');
$clientSecret = env('GOOGLE_CLIENT_SECRET', '');
$redirectUri = url('auth/google-callback.php');

$email = '';
$name = '';

if ($clientId !== '') {
    // PRODUCTION: Real Google Token Exchange
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException('Failed to exchange code for tokens. Response: ' . $response);
        }

        $tokenData = json_decode((string) $response, true);
        $accessToken = $tokenData['access_token'] ?? '';

        if ($accessToken === '') {
            throw new RuntimeException('Access token not found in Google response.');
        }

        // Fetch User profile from Google API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $profileResponse = curl_exec($ch);
        $profileHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($profileHttpCode !== 200) {
            throw new RuntimeException('Failed to fetch Google profile. Response: ' . $profileResponse);
        }

        $profileData = json_decode((string) $profileResponse, true);
        $email = strtolower(trim($profileData['email'] ?? ''));
        $name = trim($profileData['name'] ?? '');

    } catch (Throwable $e) {
        error_log('Google SSO Connection Error: ' . $e->getMessage());
        flash('error', 'SSO connection error: ' . $e->getMessage());
        redirect('login.php');
    }
} else {
    // DEV MODE: Read simulated parameters from Sandbox GET request
    $email = strtolower(trim((string) ($_GET['email'] ?? '')));
    $name = trim((string) ($_GET['name'] ?? ''));
}

if ($email === '') {
    flash('error', 'Google authentication did not return a valid email address.');
    redirect('login.php');
}

$db = db();

try {
    // Check if the user already exists in the local database
    $stmtUser = $db->prepare("
        SELECT u.*, r.role_key 
        FROM users u 
        INNER JOIN roles r ON r.id = u.role_id 
        WHERE u.email = :email
        LIMIT 1
    ");
    $stmtUser->execute(['email' => $email]);
    $user = $stmtUser->fetch();

    if ($user) {
        // User exists: process status matching
        if ($user['status'] === 'pending_email') {
            // Google SSO validates email ownership, so auto-verify and activate
            $db->prepare("UPDATE users SET status = 'active', email_verified_at = NOW(), updated_at = NOW() WHERE id = :id")
               ->execute(['id' => $user['id']]);
            $user['status'] = 'active';
        }

        if ($user['status'] === 'blocked') {
            AuditService::loginAttempt((int) $user['id'], $email, false, 'sso_blocked');
            flash('error', 'Your account has been blocked. Contact coordinator support.');
            redirect('login.php');
        }

        if ($user['status'] === 'rejected') {
            AuditService::loginAttempt((int) $user['id'], $email, false, 'sso_rejected');
            flash('error', 'Your account application was rejected.');
            redirect('login.php');
        }

        if ($user['status'] === 'pending_approval') {
            AuditService::loginAttempt((int) $user['id'], $email, false, 'sso_pending_approval');
            flash('error', 'Your account is verified and waiting for coordinator approval.');
            redirect('login.php');
        }

        // Authenticate user session
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) $user['role_id'];
        $_SESSION['role_key'] = (string) $user['role_key'];

        $db->prepare("UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id")
           ->execute(['id' => $user['id']]);

        AuditService::loginAttempt((int) $user['id'], $email, true, 'sso_success');
        AuditService::record('login', 'auth', (int) $user['id'], 'users', (int) $user['id']);

        flash('success', 'Logged in via Google SSO.');
        redirect(role_dashboard_path($user['role_key']));

    } else {
        // User does not exist: Provision fresh user account
        $stmtRole = $db->prepare("SELECT id FROM roles WHERE role_key = 'guest_participant' LIMIT 1");
        $stmtRole->execute();
        $roleId = $stmtRole->fetchColumn();
        if (!$roleId) {
            throw new RuntimeException('Default guest role not found in database.');
        }

        // Locked randomized password hash for SSO account safety
        $lockedHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $db->beginTransaction();

        $stmtInsert = $db->prepare("
            INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at, created_at, updated_at) 
            VALUES (:role_id, :full_name, :email, '+0000000000', :password_hash, 'active', NOW(), NOW(), NOW())
        ");
        $stmtInsert->execute([
            'role_id' => (int) $roleId,
            'full_name' => $name !== '' ? $name : 'SSO Member',
            'email' => $email,
            'password_hash' => $lockedHash
        ]);
        $newUserId = (int)$db->lastInsertId();

        // Create empty profile
        $db->prepare("INSERT INTO user_profiles (user_id, created_at) VALUES (:uid, NOW())")
           ->execute(['uid' => $newUserId]);

        $db->commit();

        // Authenticate
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['role_id'] = (int) $roleId;
        $_SESSION['role_key'] = 'guest_participant';

        AuditService::loginAttempt($newUserId, $email, true, 'sso_register_success');
        AuditService::record('registered', 'auth', $newUserId, 'users', $newUserId);
        AuditService::record('login', 'auth', $newUserId, 'users', $newUserId);

        flash('success', 'Registered and logged in via Google SSO.');
        redirect(role_dashboard_path('guest_participant'));
    }

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('SSO Callback Processing Error: ' . $e->getMessage());
    flash('error', 'Error processing Google login: ' . $e->getMessage());
    redirect('login.php');
}
