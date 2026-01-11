<?php 
$title = "Send mail";
require_once 'includes/header.php';
require_once 'db/conn.php';

$name=$_POST["name"];
$subject=$_POST["subject"];
$message=$_POST["message"];

require "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

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

$mail->setFrom("info@fantasy9pin.com", $name);
$mail->addAddress("fantasy9pin@gmail.com");
$mail->Subject = $subject;
$mail->Body = $message;

$mail->send();

echo '<script type="text/javascript">location.href="sent.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=sent.php" /></noscript> ';

?>