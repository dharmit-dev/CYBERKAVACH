<?php

declare(strict_types=1);

final class CertificateService
{
    public static function saveTemplate(string $name, array $file, array $settings): array
    {
        $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk (permission error or disk full).',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
            ];
            $msg = $errorMessages[$uploadError] ?? 'Unknown upload error code: ' . $uploadError;
            return ['ok' => false, 'message' => 'Template file upload failed: ' . $msg];
        }

        if ((int) $file['size'] > 5 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Template must be 5MB or smaller.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'message' => 'Template must be JPG or PNG.'];
        }

        $filename = 'template-' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        $relativePath = 'uploads/certificate-templates/' . $filename;
        $absolutePath = PUBLIC_PATH . '/' . $relativePath;

        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return ['ok' => false, 'message' => 'Unable to save template file.'];
        }

        $stmt = db()->prepare(
            'INSERT INTO certificate_templates (name, file_path, text_settings, created_at)
             VALUES (:name, :file_path, :text_settings, NOW())'
        );
        $stmt->execute([
            'name' => $name,
            'file_path' => $relativePath,
            'text_settings' => json_encode($settings),
        ]);

        return ['ok' => true];
    }

    public static function generateBatch(int $templateId, ?int $eventId, array $recipients): array
    {
        $stmt = db()->prepare('SELECT * FROM certificate_templates WHERE id = :id');
        $stmt->execute(['id' => $templateId]);
        $template = $stmt->fetch();

        if (!$template) {
            return ['ok' => false, 'message' => 'Template not found.'];
        }

        $settings = json_decode($template['text_settings'], true);
        $templatePath = PUBLIC_PATH . '/' . $template['file_path'];

        if (!file_exists($templatePath)) {
            return ['ok' => false, 'message' => 'Template file not found on disk.'];
        }

        $certificatesDir = PUBLIC_PATH . '/uploads/certificates';
        if (!is_dir($certificatesDir)) {
            mkdir($certificatesDir, 0775, true);
        }

        $zipDir = PUBLIC_PATH . '/uploads/certificates/batches';
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0775, true);
        }

        // Setup ZIP
        $zipName = 'batch-' . bin2hex(random_bytes(8)) . '.zip';
        $zipPath = $zipDir . '/' . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'message' => 'Unable to create ZIP archive.'];
        }

        // Find standard TrueType fonts across platforms
        $fontPath = 'C:/Windows/Fonts/arial.ttf';
        if (!file_exists($fontPath)) {
            $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        }
        if (!file_exists($fontPath)) {
            $fontPath = null;
        }

        $generatedCount = 0;
        $errors = [];

        foreach ($recipients as $recipient) {
            $name = trim($recipient['name'] ?? '');
            $email = trim($recipient['email'] ?? '');
            $userId = isset($recipient['user_id']) ? (int) $recipient['user_id'] : null;

            if ($name === '' || $email === '') {
                continue;
            }

            // Create unique certificate code and signature
            $code = 'CK-CERT-' . strtoupper(bin2hex(random_bytes(6)));
            $secretKey = env('COORDINATOR_BOOTSTRAP_KEY', 'cyberkavach-secret');
            $signature = hash_hmac('sha256', $code . '|' . $name . '|' . $email, $secretKey);

            // Re-load template image
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($templatePath);
            if ($mime === 'image/png') {
                $image = imagecreatefrompng($templatePath);
            } else {
                $image = imagecreatefromjpeg($templatePath);
            }

            if (!$image) {
                $errors[] = "Failed to load template image for recipient: $name";
                continue;
            }

            // Dark zinc gray color for text
            $textColor = imagecolorallocate($image, 24, 24, 27);

            // Render recipient name
            self::drawTextOnImage($image, $name, (int) ($settings['name_x'] ?? 100), (int) ($settings['name_y'] ?? 150), (int) ($settings['name_size'] ?? 24), $textColor, $fontPath);

            // Render event title
            $eventTitle = $recipient['event_title'] ?? 'CyberKavach Event';
            self::drawTextOnImage($image, $eventTitle, (int) ($settings['event_x'] ?? 100), (int) ($settings['event_y'] ?? 220), (int) ($settings['event_size'] ?? 18), $textColor, $fontPath);

            // Render Certificate Code/ID
            self::drawTextOnImage($image, 'ID: ' . $code, (int) ($settings['code_x'] ?? 100), (int) ($settings['code_y'] ?? 300), (int) ($settings['code_size'] ?? 12), $textColor, $fontPath);

            // Render Date
            $issueDate = date('F d, Y');
            self::drawTextOnImage($image, 'Date: ' . $issueDate, (int) ($settings['date_x'] ?? 100), (int) ($settings['date_y'] ?? 340), (int) ($settings['date_size'] ?? 12), $textColor, $fontPath);

            // Save certificate image
            $certFilename = 'cert-' . $code . '.png';
            $certRelativePath = 'uploads/certificates/' . $certFilename;
            $certAbsolutePath = PUBLIC_PATH . '/' . $certRelativePath;

            imagepng($image, $certAbsolutePath);
            imagedestroy($image);

            // Add to ZIP
            $zip->addFile($certAbsolutePath, $certFilename);

            // Insert into certificates
            $stmt = db()->prepare(
                'INSERT INTO certificates (certificate_code, template_id, event_id, user_id, recipient_name, recipient_email, cryptographic_signature, file_path, created_at)
                 VALUES (:code, :template_id, :event_id, :user_id, :recipient_name, :recipient_email, :signature, :file_path, NOW())'
            );
            $stmt->execute([
                'code' => $code,
                'template_id' => $templateId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'recipient_name' => $name,
                'recipient_email' => strtolower($email),
                'signature' => $signature,
                'file_path' => $certRelativePath,
            ]);

            // Dispatch notification email
            $downloadUrl = url('certificates/verify.php?code=' . urlencode($code));
            $mailSubject = app_config('name') . ' - Certificate Issued';
            $mailMessage = "Hello {$name},\n\nWe are pleased to issue your participation certificate for the event: {$eventTitle}.\n\nCertificate ID: {$code}\n\nYou can verify and download your certificate here:\n{$downloadUrl}\n\nBest regards,\n" . app_config('name');
            MailService::sendEmail($email, $mailSubject, $mailMessage);

            $generatedCount++;
        }

        $zip->close();

        return [
            'ok' => true,
            'generated_count' => $generatedCount,
            'zip_url' => url('uploads/certificates/batches/' . $zipName),
            'errors' => $errors,
        ];
    }

    private static function drawTextOnImage($image, string $text, int $x, int $y, int $size, int $color, ?string $fontPath): void
    {
        if ($fontPath !== null) {
            imagettftext($image, $size, 0, $x, $y, $color, $fontPath, $text);
        } else {
            $gdSize = 5;
            if ($size < 12) {
                $gdSize = 2;
            } elseif ($size < 18) {
                $gdSize = 3;
            } elseif ($size < 24) {
                $gdSize = 4;
            }
            imagestring($image, $gdSize, $x, $y - 10, $text, $color);
        }
    }
}
