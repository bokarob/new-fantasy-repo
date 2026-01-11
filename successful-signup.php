<?php 
$title = "Regisztráció";
require_once 'includes/header.php';
require_once 'db/conn.php';


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
        <h5><?php switch($_SESSION['lang']){
                    case 1: echo "Sikeresen regisztráltál. Küldtünk egy megerősítő linket a megadott email címedre!";
                    break;
                    case 2: echo "Successful registration. We sent a confirmation link to your email address.";
                    break;
                    case 3: echo "Registrierung erfolgreich. Wir haben einen Bestätigungslink an deine E-Mail-Adresse gesendet.";
                    break;
                }?></h5>
    </div>
</div>



<br>
<br>
<br>
<br>
<?php require_once 'includes/footer.php'; ?>