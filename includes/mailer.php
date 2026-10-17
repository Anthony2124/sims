<?php
require_once __DIR__ . '/../config.php';

function send_mail($to, $subject, $body, $altBody = '') {
    // Try using PHPMailer via Composer if available
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = defined('GMAIL_SMTP_HOST') ? GMAIL_SMTP_HOST : 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = GMAIL_SMTP_USER;
            $mail->Password = GMAIL_SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port = defined('GMAIL_SMTP_PORT') ? GMAIL_SMTP_PORT : 587;
            $mail->Timeout = 10; // Add timeout so it fails fast instead of hanging
            $mail->setFrom(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@sims.local', 'SIMS');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback to PHP mail()
    $headers = 'From: ' . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@sims.local') . "\r\n" .
               "MIME-Version: 1.0\r\n" .
               "Content-type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}
