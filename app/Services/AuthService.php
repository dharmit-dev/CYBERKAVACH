<?php

declare(strict_types=1);

final class AuthService
{
    public static function register(array $input): array
    {
        $errors = self::validateRegistration($input);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $userId = User::create($input);
        $user = User::findById($userId);

        if (!$user) {
            return ['ok' => false, 'errors' => ['general' => 'Unable to create account.']];
        }

        $otp = OtpService::create($userId, $user['email'], 'email_verification');
        MailService::sendOtp($user['email'], $user['full_name'], $otp, 'email verification');
        AuditService::record('registered', 'auth', $userId, 'users', $userId);

        $_SESSION['pending_verification_user_id'] = $userId;

        return ['ok' => true, 'user_id' => $userId];
    }

    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if (self::tooManyLoginAttempts($email)) {
            AuditService::loginAttempt(null, $email, false, 'rate_limited');
            return ['ok' => false, 'message' => 'Too many login attempts. Try again after 15 minutes.'];
        }

        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            AuditService::loginAttempt($user['id'] ?? null, $email, false, 'invalid_credentials');
            return ['ok' => false, 'message' => 'Invalid email or password.'];
        }

        if ($user['status'] === 'pending_email') {
            $_SESSION['pending_verification_user_id'] = (int) $user['id'];
            AuditService::loginAttempt((int) $user['id'], $email, false, 'email_not_verified');
            return ['ok' => false, 'message' => 'Please verify your email OTP before login.', 'verify_email' => true];
        }

        if ($user['status'] === 'pending_approval') {
            AuditService::loginAttempt((int) $user['id'], $email, false, 'pending_approval');
            return ['ok' => false, 'message' => 'Your account is verified and waiting for coordinator approval.'];
        }

        if ($user['status'] !== 'active') {
            AuditService::loginAttempt((int) $user['id'], $email, false, 'account_' . $user['status']);
            return ['ok' => false, 'message' => 'This account is not active. Contact the club coordinator.'];
        }

        if (empty($user['role_id']) || empty($user['role_key']) || !is_valid_role_key((string) $user['role_key'])) {
            AuditService::loginAttempt((int) $user['id'], $email, false, 'role_not_configured');
            return ['ok' => false, 'message' => 'This account role is not configured correctly. Contact the administrator.'];
        }

        session_regenerate_id(true);
        self::storeSessionUser($user);

        $stmt = db()->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);

        AuditService::loginAttempt((int) $user['id'], $email, true, 'success');
        AuditService::record('login', 'auth', (int) $user['id'], 'users', (int) $user['id']);

        return ['ok' => true, 'user' => $user];
    }

    public static function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            AuditService::record('logout', 'auth', (int) $userId, 'users', (int) $userId);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    private static function storeSessionUser(array $user): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) $user['role_id'];
        $_SESSION['role_key'] = (string) $user['role_key'];
    }

    public static function sendPasswordReset(string $email): bool
    {
        $user = User::findByEmail($email);

        if (!$user) {
            return false;
        }

        $otp = OtpService::create((int) $user['id'], $user['email'], 'password_reset');
        MailService::sendOtp($user['email'], $user['full_name'], $otp, 'password reset');
        AuditService::record('password_reset_requested', 'auth', (int) $user['id'], 'users', (int) $user['id']);
        return true;
    }

    public static function resetPassword(string $email, string $otp, string $password, string $confirmPassword): array
    {
        $email = strtolower(trim($email));
        $user = User::findByEmail($email);

        if (!$user) {
            return ['ok' => false, 'message' => 'Invalid email address.'];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        if ($password !== $confirmPassword) {
            return ['ok' => false, 'message' => 'Passwords do not match.'];
        }

        if (!OtpService::verify((int) $user['id'], $otp, 'password_reset')) {
            AuditService::record('password_reset_failed', 'auth', (int) $user['id'], 'users', (int) $user['id']);
            return ['ok' => false, 'message' => 'Invalid or expired OTP.'];
        }

        User::updatePassword((int) $user['id'], $password);

        AuditService::record('password_reset_completed', 'auth', (int) $user['id'], 'users', (int) $user['id']);

        return ['ok' => true];
    }

    private static function validateRegistration(array $input): array
    {
        $errors = [];

        if (strlen($input['full_name'] ?? '') < 2) {
            $errors['full_name'] = 'Full name is required.';
        }

        if (!filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required.';
        } elseif (User::findByEmail((string) $input['email'])) {
            $errors['email'] = 'This email is already registered.';
        }

        if (!preg_match('/^[0-9+\-\s]{8,20}$/', (string) ($input['phone'] ?? ''))) {
            $errors['phone'] = 'Enter a valid phone number.';
        }

        $allowedRoleIds = array_column(User::allowedPublicRegistrationRoles(), 'id');
        if (!in_array((int) ($input['role_id'] ?? 0), array_map('intval', $allowedRoleIds), true)) {
            $errors['role_id'] = 'Select a valid registration role.';
        }

        if (strlen((string) ($input['password'] ?? '')) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (($input['password'] ?? '') !== ($input['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        return $errors;
    }

    private static function tooManyLoginAttempts(string $email): bool
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM login_attempts
             WHERE email = :email
                AND ip_address = :ip_address
                AND success = 0
                AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)'
        );
        $stmt->execute([
            'email' => $email,
            'ip_address' => client_ip(),
        ]);

        return (int) $stmt->fetchColumn() >= 5;
    }
}
