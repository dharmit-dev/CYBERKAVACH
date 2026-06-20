<?php

declare(strict_types=1);

final class OtpService
{
    public static function create(int $userId, string $email, string $purpose): string
    {
        // Rate Limiting: Max 3 OTP codes created by client IP in the last hour
        $ip = client_ip();
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM otp_codes 
             WHERE ip_address = :ip 
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute(['ip' => $ip]);
        $count = (int) $stmt->fetchColumn();
        if ($count >= 3) {
            throw new RuntimeException('Too many verification codes requested. Please try again in an hour.');
        }

        self::invalidateExisting($userId, $purpose);

        $otp = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + ((int) app_config('otp_expiry_minutes') * 60));

        $stmt = db()->prepare(
            'INSERT INTO otp_codes (user_id, email, otp_hash, purpose, expires_at, is_used, ip_address, user_agent, created_at)
             VALUES (:user_id, :email, :otp_hash, :purpose, :expires_at, 0, :ip_address, :user_agent, NOW())'
        );

        $stmt->execute([
            'user_id' => $userId,
            'email' => strtolower($email),
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'ip_address' => $ip,
            'user_agent' => user_agent(),
        ]);

        return $otp;
    }

    public static function verify(int $userId, string $otp, string $purpose): bool
    {
        $stmt = db()->prepare(
            'SELECT * FROM otp_codes
             WHERE user_id = :user_id
                AND purpose = :purpose
                AND is_used = 0
                AND expires_at >= NOW()
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        if (!password_verify($otp, $row['otp_hash'])) {
            $failedAttempts = (int) $row['failed_attempts'] + 1;
            if ($failedAttempts >= 3) {
                // Auto-lock OTP on 3rd failure
                $update = db()->prepare('UPDATE otp_codes SET failed_attempts = :failed_attempts, is_used = 1 WHERE id = :id');
                $update->execute([
                    'failed_attempts' => $failedAttempts,
                    'id' => $row['id'],
                ]);
            } else {
                $update = db()->prepare('UPDATE otp_codes SET failed_attempts = :failed_attempts WHERE id = :id');
                $update->execute([
                    'failed_attempts' => $failedAttempts,
                    'id' => $row['id'],
                ]);
            }
            return false;
        }

        $update = db()->prepare('UPDATE otp_codes SET is_used = 1, used_at = NOW() WHERE id = :id');
        $update->execute(['id' => $row['id']]);

        return true;
    }

    private static function invalidateExisting(int $userId, string $purpose): void
    {
        $stmt = db()->prepare(
            'UPDATE otp_codes
             SET is_used = 1
             WHERE user_id = :user_id AND purpose = :purpose AND is_used = 0'
        );
        $stmt->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
        ]);
    }
}

