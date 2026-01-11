<?php 
$title = "Elfelejtett jelszó";
require_once 'includes/header.php';
require_once 'db/conn.php';


?>

<style>
    .passwordbox {
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

    .pass-form {
        width: 100%;
        padding: 2em;
        position: relative;
        background: rgba(black, .15);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        
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
        .pass-form{
            width: 50vw;
            max-width: 30rem;
        }  
    }
    .pass-form p{
        text-align: center;
        margin-top: 1rem;
        font-size: 11pt;
    }

        
    .flex-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap:10px;
        margin-bottom: 1em;
        width: 300px;
        
    }

    .flex-row input{
        width: 100%;
    }

    .lf--submit {
        display: block;
        padding: 0.5rem 1.5rem 0.5rem 1.5rem;
        width: 100%;
        
        background: linear-gradient(
        to right,
        #35c3c1,
        #00d6b7
        );
        border: 0;
        color: #fff;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        text-shadow: 0 1px 0 rgba(black, .2);
        margin: auto;
        
        &:focus {
        outline: none;
        transition: transform .15s ease;
        transform: scale(1.1);
        }
    }


</style>


<div class="passwordbox">
    <form class="pass-form" action="send-password-reset.php" method="post">
        <h3><?php switch($_SESSION['lang']){
                    case 1: echo "Válassz új jelszót";
                    break;
                    case 2: echo "Create a new password";
                    break;
                    case 3: echo "Neues Passwort erstellen";
                    break;
                }?></h3>
        <div class="flex-row">
           <p><?php switch($_SESSION['lang']){
                    case 1: echo "A megadott email címedre elküldjük az instrukciókat, amivel új jelszót tudsz beállítani";
                    break;
                    case 2: echo "We will send you an email with instructions for resetting your password";
                    break;
                    case 3: echo "Wir senden dir eine E-Mail mit Anweisungen zum Zurücksetzen deines Passworts.";
                    break;
                }?></p> 
        </div>
        
        <div class="flex-row">
            <label for="email">Email: </label>
            <input required type="email" name="email" id="email"> 
        </div>
        <div class="flex-row">
            <button class="lf--submit"><?php switch($_SESSION['lang']){
                    case 1: echo "Email küldése";
                    break;
                    case 2: echo "Send email";
                    break;
                    case 3: echo "E-Mail senden";
                    break;
                }?></button>
        </div>

        
    </form>
</div>






<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>