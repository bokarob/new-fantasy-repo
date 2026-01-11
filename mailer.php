<?php 

require "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->SMTPAuth = true ;
$mail->CharSet = "UTF-8";
$mail->Encoding = 'base64';

$mail->Host = "smtp.hostinger.com";
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;
$mail->Username = "info@fantasy9pin.com";
$mail->Password = "Rece.1v.311";

$mail->isHTML(true);

return $mail;

?>