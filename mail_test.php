<?php
require_once __DIR__ . '/app/libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/app/libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/app/libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // <-- vendos këtu gmail-in tënd
    $mail->Username = 'gloria.doda3@gmail.com';

    // <-- vendos këtu APP PASSWORD 16-char (pa hapësira)
    $mail->Password = 'yzegknyfkpumezgt';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom($mail->Username, 'Grocery Store');
    $mail->addAddress($mail->Username, 'Test');

    $mail->isHTML(true);
    $mail->Subject = 'PHPMailer test';
    $mail->Body = '<b>If you received this, SMTP works.</b>';

    $mail->send();
    echo "OK: Email sent.";
} catch (Exception $e) {
    echo "ERROR: " . $mail->ErrorInfo;
}
