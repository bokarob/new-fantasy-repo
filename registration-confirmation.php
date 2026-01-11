<?php 
$title = "Regisztráció";
require_once 'includes/header.php';
require_once 'db/conn.php';

$token=$_GET['token'];
$token_hash = hash("sha256", $token);

$finduser=$webuser->findRegistrationToken($token_hash);

if(!$finduser){
    die("User not found");
}else{
    $deletetoken=$webuser->deleteRegistrationToken($finduser['email']);
    $_SESSION['email'] = $finduser['email'];
    $_SESSION['alias'] = $finduser['alias'];
    $_SESSION['profilename'] = $finduser['profilename'];        
    $_SESSION['profile_id'] = $finduser['profile_id'];
    $_SESSION['authorization'] = $finduser['authorization'];
    $_SESSION['lang'] = $finduser['lang_id'];
}



?>

<style>

.messagebox{
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    position: relative;
    margin-top: 2rem;
    
    
    font-family: "Roboto", helvetica, arial, sans-serif;
    
    
    &:before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        height: 100%;
        width: 100%;
    
    }
}
.message-sent{
    width: 100%;
    padding: 2em;
    position: relative;
    background: rgba(black, .15);
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-style: italic;

    
    &:before {
        content: '';
        position: absolute;
        top: -2px; left: 0;
        height: 2px; width: 100%;
        
        background: linear-gradient(
        to right,
        #35c3c1,
        #00d6b7
        );    
    }
}
@media screen and (min-width: 600px) {
    .message-sent{
        width: 50vw;
        max-width: 30rem;
    }  
}
</style>

<div class="messagebox">
    <div class="message-sent">
        <h4><?php switch($_SESSION['lang']){
                    case 1: echo "Regisztráció megerősítve! Készen állsz, hogy összeállítsd a fantasy csapatodat!";
                    break;
                    case 2: echo "Registration is confirmed! You are all set to start assembling your fantasy team!";
                    break;
                    case 3: echo "Registrierung bestätigt! Du kannst jetzt dein Fantasy-Team zusammenstellen!";
                    break;
                }?></h4>
    </div>
</div>

<?php 
echo '<meta http-equiv="refresh" content="7;url=index.php">';
?>


<br>
<br>
<br>
<br>
<?php require_once 'includes/footer.php'; ?>