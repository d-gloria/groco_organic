<?php
// app/libs/mailer.php

require_once __DIR__ . '/../config/mail.php';

// PHPMailer manual includes
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_verification_email(string $toEmail, string $toName, string $code): bool {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Email verification';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif; font-size:14px;'>
                <p>Hi ".htmlspecialchars($toName).",</p>
                <p>To verify use the following:</p>
                <h2 style='letter-spacing:2px;'>".$code."</h2>
                <p>It expires in 10 minutes.</p>
            </div>
        ";
        $mail->AltBody = "To verify use the following: $code (expires in 10 minutes)";

        return $mail->send();

    } catch (Exception $e) {
        return false;
    }
}
