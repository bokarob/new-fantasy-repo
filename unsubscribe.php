<?php 
$title = "Unsubscribe";
require_once 'includes/header.php';
require_once 'db/conn.php';

$token=$_GET['token'];

$finduser=$webuser->findUserByUnsubscribeToken($token);

if(!$finduser){
    switch($_SESSION['lang']){
        case 1: die("Valami hiba van, kérjük ellenőrizd a linket");
        break;
        case 2: die("Token not found, please check if you have the correct link");
        break;
        case 3: die("Token nicht gefunden, bitte überprüfe den Link");
        break;
    }
    
}else{
    $unsubscribe=$webuser->updateNewsletterSub($finduser['profile_id'],0);
    $_SESSION['lang'] = $finduser['lang_id'];

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
                    case 1: echo "Sikeresen leiratkoztál hírlevelünkről. Sok sikert a nehezített terepen! Ha mégis hiányoznának az extra infók, bármikor újra feliratkozhatsz.";
                    break;
                    case 2: echo "You have successfully unsubscribed from our newsletter. Good luck on the difficult path! If you miss the extra info, you can subscribe again at any time.";
                    break;
                    case 3: echo "Du hast dich erfolgreich von unserem Newsletter abgemeldet. Viel Erfolg auf dem schwierigen Weg! :) Wenn du die zusätzlichen Infos vermisst, kannst du dich jederzeit wieder anmelden.";
                    break;
                }?></h4>
    </div>
</div>

<?php } ?>


<br>
<br>
<br>
<br>
<?php require_once 'includes/footer.php'; ?>