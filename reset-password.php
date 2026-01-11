<?php 
$title = "Új jelszó";
require_once 'includes/header.php';
require_once 'db/conn.php';

$token=$_GET['token'];
$token_hash = hash("sha256", $token);

$finduser=$webuser->findUserByToken($token_hash);

if(!$finduser){
    die("token not found");
}

if(strtotime($finduser['reset_token_expire']) <= time()){
    die("token has expired");
}

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

    .pass-form h3{
        margin-bottom: 3rem;
    }

    .flex-row {
        display: flex;
        align-items: stretch;
        justify-content: center;
        gap:5px;
        margin-bottom: 1em;
        width: 300px;
        
    }

    .flex-row *{
        flex:1;
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
        margin-top: 1rem;
        
        &:focus {
        outline: none;
        transition: transform .15s ease;
        transform: scale(1.1);
        }
    }

</style>

<div class="passwordbox">
    
    <form class="pass-form" action="process-reset-password.php" method="post">
        <h3><?php switch($_SESSION['lang']){
                    case 1: echo "Jelszó megváltoztatása";
                    break;
                    case 2: echo "Reset password";
                    break;
                    case 3: echo "Passwort zurücksetzen";
                    break;
                }?></h3>
        <input type="hidden" name="token" value=<?= htmlspecialchars($token); ?>>
        <div class="flex-row">
            <label for="password"><?php switch($_SESSION['lang']){
                    case 1: echo "Az új jelszavad";
                    break;
                    case 2: echo "New Password";
                    break;
                    case 3: echo "Neues Passwort";
                    break;
                }?></label>
            <input required type="password" name="password" id="password">    
        </div>
        <div class="flex-row">
            <label for="password_confirmation"><?php switch($_SESSION['lang']){
                    case 1: echo "Ugyanaz ismét";
                    break;
                    case 2: echo "Repeat password";
                    break;
                    case 3: echo "Passwort wiederholen";
                    break;
                }?></label>
            <input required type="password" name="password_confirmation" id="password_confirmation">    
        </div>
        <div class="flex-row">
            <button class="lf--submit"><?php switch($_SESSION['lang']){
                    case 1: echo "Küldés";
                    break;
                    case 2: echo "Reset";
                    break;
                    case 3: echo "Speichern";
                    break;
                }?></button>    
        </div>
        
        
    </form>
</div>



<br>
<br>
<br>
<br>
<?php require_once 'includes/footer.php'; ?>