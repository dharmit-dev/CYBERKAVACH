<?php

declare(strict_types=1);

final class MailService
{
    public static function sendOtp(string $email, string $name, string $otp, string $purpose): bool
    {
        $subject = app_config('name') . ' OTP Verification';
        $message = "Hello {$name},\n\nYour {$purpose} OTP is: {$otp}\n\nThis code expires soon. If you did not request this, ignore this email.";

        $result = self::sendEmail($email, $subject, $message);
        return $result['ok'];
    }

    public static function sendPasswordReset(string $email, string $name, string $resetUrl): bool
    {
        $subject = app_config('name') . ' Password Reset';
        $message = "Hello {$name},\n\nUse this link to reset your password:\n{$resetUrl}\n\nThis link expires soon.";

        $result = self::sendEmail($email, $subject, $message);
        return $result['ok'];
    }

    public static function sendEmail(string $to, string $subject, string $message): array
    {
        $mailHost = env('MAIL_HOST', '');

        if ($mailHost === '') {
            self::logDevelopmentEmail($to, $subject, $message);
            return ['ok' => true, 'message' => 'Email logged to development file.'];
        }

        try {
            self::sendSmtp($to, $subject, $message);
            return ['ok' => true, 'message' => 'Email sent successfully via SMTP.'];
        } catch (Throwable $e) {
            error_log('SMTP Delivery Error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Failed to send email via SMTP. Please try again later.'];
        }
    }

    private static function sendSmtp(string $to, string $subject, string $message): void
    {
        $host = env('MAIL_HOST', 'smtp.gmail.com');
        $port = (int) env('MAIL_PORT', '587');
        $username = env('MAIL_USERNAME', '');
        $password = env('MAIL_PASSWORD', '');
        $fromEmail = env('MAIL_FROM_ADDRESS', 'no-reply@example.com');
        $fromName = env('MAIL_FROM_NAME', app_config('name'));
        $encryption = env('MAIL_ENCRYPTION', 'tls');

        $protocol = ($encryption === 'ssl' || $port === 465) ? 'ssl://' : 'tcp://';

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $socket = @stream_socket_client($protocol . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server: $errstr ($errno)");
        }
        
        $read = function() use ($socket) {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) == ' ') break;
            }
            return $data;
        };

        $read();

        fwrite($socket, "EHLO localhost\r\n");
        $read();

        if ($encryption === 'tls' && $port !== 465) {
            fwrite($socket, "STARTTLS\r\n");
            $res = $read();
            if (strpos($res, '220') === 0) {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fwrite($socket, "EHLO localhost\r\n");
                $read();
            }
        }

        if ($username !== '' && $password !== '') {
            fwrite($socket, "AUTH LOGIN\r\n");
            $read();
            
            fwrite($socket, base64_encode($username) . "\r\n");
            $read();
            
            fwrite($socket, base64_encode($password) . "\r\n");
            $res = $read();
            if (strpos($res, '235') !== 0) {
                throw new RuntimeException("SMTP Authentication failed.");
            }
        }

        fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
        $read();

        fwrite($socket, "RCPT TO: <$to>\r\n");
        $read();

        fwrite($socket, "DATA\r\n");
        $read();

        $headers = [
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>",
            "To: <$to>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Date: " . date("r")
        ];

        $messageLines = str_replace("\n.", "\n..", str_replace("\r", "", $message));
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $messageLines . "\r\n.\r\n";
        
        fwrite($socket, $payload);
        $res = $read();
        
        if (strpos($res, '250') !== 0) {
            throw new RuntimeException("SMTP failed to accept DATA: $res");
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
    }

    private static function logDevelopmentEmail(string $to, string $subject, string $message): void
    {
        $line = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $message
        );

        file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX);
    }
}
