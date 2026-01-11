<?php
$title = "Új jelszó";
require_once 'includes/header.php';
require_once 'db/conn.php';

$email = $_POST['email'];

$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256", $token);
$expiry = date("Y-m-d H:i:s", time() + 60*30);

$checkemail=$webuser->getUserbyemail($email);
if($checkemail['num']>0){
    $sendtoken=$webuser->updateToken($email,$token_hash,$expiry);
    $checkuserdetails=$webuser->getUserdetailsbyemail($email);
    $alias=$checkuserdetails['alias'];

    $mail = require "mailer.php";
    $mail->setFrom("info@fantasy9pin.com", "Fantasy 9pin info");
    $mail->addAddress($email);

    switch($_SESSION['lang']){
        case 1:
            $mail->isHTML(true); 
            $mail->Subject = "Jelszó visszaállítás";
            $mailcontent='<html>
                            <body>
                                <div class="container" style="width: 100%; max-width: 600px; margin: 0 auto; padding: 20px;">
                                    <div class="header" style="background-color: #f1f1f1; padding: 10px 20px;">
                                        <h1 style="margin: 0; background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);-webkit-background-clip: text;  background-clip: text;  -webkit-text-fill-color: transparent;  margin-bottom: 0; text-align: center;">Fantasy 9pin</h1>
                                    </div>
                                    <div class="content" style="margin: 20px 0;">
                                        <h4 style="margin-bottom: 30px;">Kedves '.$alias.',</h4>
                                        <p style="line-height: 1.5;">Kattints az alábbi linkre, hogy új jelszót tudj beállítani:</p>
                                        <a href="http://fantasy9pin.com/reset-password.php?token='.$token.'">
                                            <button style="display: inline-block;  outline: 0; border: 0; background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% ); padding: 0 32px; border-radius: 6px; color: #fff; height: 48px; font-size: 18px; text-shadow: 0 1px 0 rgb(0 0 0 / 40%); margin-bottom: 50px;">JELSZÓ VISSZAÁLLÍTÁSA</button>
                                        </a>
                                        <p>Ez a token limitált ideig érvényes. Jobb ha <strong>'.$expiry.'</strong> előtt cselekszel!</p>
                                    </div>
                                </div>
                            </body>
                            </html>';
            $mail->Body = $mailcontent;
        break;
        case 2:
            $mail->isHTML(true); 
            $mail->Subject = "Password reset";
            $mailcontent='<html>
                            <body>
                                <div class="container" style="width: 100%; max-width: 600px; margin: 0 auto; padding: 20px;">
                                    <div class="header" style="background-color: #f1f1f1; padding: 10px 20px;">
                                        <h1 style="margin: 0; background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);-webkit-background-clip: text;  background-clip: text;  -webkit-text-fill-color: transparent;  margin-bottom: 0; text-align: center;">Fantasy 9pin</h1>
                                    </div>
                                    <div class="content" style="margin: 20px 0;">
                                        <h4 style="margin-bottom: 30px;">Dear '.$alias.',</h4>
                                        <p style="line-height: 1.5;">Click on the below link to reset your password:</p>
                                        <a href="http://fantasy9pin.com/reset-password.php?token='.$token.'">
                                            <button style="display: inline-block;  outline: 0; border: 0; background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% ); padding: 0 32px; border-radius: 6px; color: #fff; height: 48px; font-size: 18px; text-shadow: 0 1px 0 rgb(0 0 0 / 40%); margin-bottom: 50px;">RESET MY PASSWORD</button>
                                        </a>
                                        <p>This token will be valid until <strong>'.$expiry.'</strong></p>
                                    </div>
                                </div>
                            </body>
                            </html>';
            $mail->Body = $mailcontent;
        break;
        case 3:
            $mail->isHTML(true); 
            $mail->Subject = "Passwort zurücksetzen";
            $mailcontent='<html>
                            <body>
                                <div class="container" style="width: 100%; max-width: 600px; margin: 0 auto; padding: 20px;">
                                    <div class="header" style="background-color: #f1f1f1; padding: 10px 20px;">
                                        <h1 style="margin: 0; background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);-webkit-background-clip: text;  background-clip: text;  -webkit-text-fill-color: transparent;  margin-bottom: 0; text-align: center;">Fantasy 9pin</h1>
                                    </div>
                                    <div class="content" style="margin: 20px 0;">
                                        <h4 style="margin-bottom: 30px;">Liebe(r) '.$alias.',</h4>
                                        <p style="line-height: 1.5;">Klicke auf den untenstehenden Link, um dein Passwort zurückzusetzen:</p>
                                        <a href="http://fantasy9pin.com/reset-password.php?token='.$token.'">
                                            <button style="display: inline-block;  outline: 0; border: 0; background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% ); padding: 0 32px; border-radius: 6px; color: #fff; height: 48px; font-size: 18px; text-shadow: 0 1px 0 rgb(0 0 0 / 40%); margin-bottom: 50px;">RESET MY PASSWORD</button>
                                        </a>
                                        <p>Dieser Token ist gültig bis: <strong>'.$expiry.'</strong></p>
                                    </div>
                                </div>
                            </body>
                            </html>';
            $mail->Body = $mailcontent;
        break;
    }
    
    try {
        $mail->send();
    } catch (Exception $e) {
        echo "Mail could not be sent. Mailer error: {$mail->ErrorInfo}";
    }

}

echo '<script type="text/javascript">location.href="password-sent.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=password-sent.php" /></noscript> ';



?>