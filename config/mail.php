<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('MAIL_HOST',     getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',     (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME')  ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD')  ?: '');
define('MAIL_FROM',     getenv('MAIL_FROM')      ?: getenv('MAIL_USERNAME') ?: '');
define('MAIL_FROM_NAME','Recruitment System');

function sendOTPEmail(string $toEmail, string $toName, string $otp): bool {
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

        $mail->Subject = 'Your One-Time Password';
        $mail->Body    = "Hello $toName,\n\nYour OTP is: $otp\n\nIt expires in 10 minutes.\n\nIf you did not request this, ignore this email.";

        $mail->send();
        return true;
    } catch (Exception) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}
