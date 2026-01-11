<?php 
$title = "Új jelszó";
require_once 'includes/header.php';
require_once 'db/conn.php';

$token=$_POST['token'];
$token_hash = hash("sha256", $token);

$finduser=$webuser->findUserByToken($token_hash);

if(!$finduser){
    switch($_SESSION['lang']){
        case 1: die("Valami hiba van, kezdd előről a jelszóigénylést");
        break;
        case 2: die("Token not found, start the password request again");
        break;
        case 3: die("Token nicht gefunden, fang die Passwortanfrage nochmal an");
        break;
    }
    
}

if(strtotime($finduser['reset_token_expire']) <= time()){
    switch($_SESSION['lang']){
        case 1: die("Lejárt a jelszó igényléshez kapott link időkorlátja. Kezdd előről a folyamatot");
        break;
        case 2: die("Token has expired, please start the password request again");
        break;
        case 3: die("Token ist abgelaufen, bitte starte die Passwortanfrage nochmal.");
        break;
    }
    
}

if($_POST['password'] !== $_POST['password_confirmation']){
    switch($_SESSION['lang']){
        case 1: die("A két beírt jelszó nem egyezik. Lépj vissza az előző oldalra és próbáld újra.");
        break;
        case 2: die("The passwords you entered are not matching. Navigate back and try again.");
        break;
        case 3: die("Die Passwörter stimmen nicht überein. Geh zurück und versuch's nochmal.");
        break;
    }
    
}

$password_hash=md5($_POST['password'].$finduser['email']);

$newpassword=$webuser->updatePassword($finduser['profile_id'],$password_hash);

if(!$newpassword){
    die("Not successful");
}else{
    echo '<script type="text/javascript">location.href="password-changed.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=password-changed.php" /></noscript> ';
}

?>