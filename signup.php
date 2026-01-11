<?php 
$title = "Regisztráció";
require_once 'includes/header.php';
require_once 'db/conn.php';

if (!isset($_SESSION['form_start_time'])) {
    $_SESSION['form_start_time'] = time();
}

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['email'])){
    $time_elapsed = time() - $_SESSION['form_start_time'];

    // Set the minimum time (e.g., 5 seconds)
    $min_time = 10;

    if ($time_elapsed < $min_time) {
        // Form was submitted too quickly, likely a bot
        unset($_SESSION['form_start_time']);
        echo '<script type="text/javascript">location.href="index.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
    }else{   
    unset($_SESSION['form_start_time']);

    $checkuser=$webuser->getUserbyemail($_POST['email']);
    if($checkuser['num']>0){
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-primary text-center">Ezzel az email címmel már regisztráltak.</div>';
            break;
            case 2: echo '<div class="alert alert-primary text-center">An account already exists with this email address.</div>';
            break;
            case 3: echo '<div class="alert alert-primary text-center">Ein Konto mit dieser E-Mail-Adresse existiert bereits.</div>';
            break;
        }
    }else{
        $email = $_POST['email'];
        $profilename = $_POST['profilename'];
        $alias = $_POST['alias'];
        $password = $_POST['password'];
        $lang_id = $_POST['language'];
        $newsletter = $_POST['newsletter'] ?? 0; // Default to 0 if not set
        if($_POST['profilepic']){
            $picture_id=$_POST['profilepic'];
        }else{$picture_id=1;}
        $new_password = md5($password.$email);

        $token=bin2hex(random_bytes(16));//creating registration hash
        $reg_hash=hash("sha256", $token); 

        $issuccess = $webuser->insertUser($email,$password,$profilename,$alias,$lang_id,$picture_id,$reg_hash,$newsletter);

        if($issuccess){

            $mail = require "mailer.php";
            $mail->setFrom("info@fantasy9pin.com", "Fantasy 9pin info");
            $mail->addAddress($email);
            switch($_SESSION['lang']){
                case 1:
                    $mail->isHTML(true); 
                    $mail->Subject = "Fantasy 9pin regisztráció";
                    $mailcontent='<html>
                                    <body>
                                        <div class="container" style="width: 100%; max-width: 600px; margin: 0 auto; padding: 20px;">
                                            <div class="header" style="background-color: #f1f1f1; padding: 10px 20px;">
                                                <h1 style="margin: 0; background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);-webkit-background-clip: text;  background-clip: text;  -webkit-text-fill-color: transparent;  margin-bottom: 0; text-align: center;">Welcome to Fantasy 9pin League</h1>
                                            </div>
                                            <div class="content" style="margin: 20px 0;">
                                                <h4 style="margin-bottom: 40px;">Kedves '.$alias.'!</h4>
                                                <p style="line-height: 1.5;">Örülünk, hogy úgy döntöttél, próbára teszed a képességeidet és elindulsz a fantasy edzővé válás útján. Nem állítjuk, hogy könnyű út ez vagy hogy rövid, de mint általában, egy egyszerű lépéssel kezdődik: erősítsd meg a regisztrációd!</p>
                                                <p style="line-height: 1.5;">Kattints a lenti linkre, hogy elkezdhesd összeállítani a saját fantasy csapatodat:</p>
                                                <a href="http://fantasy9pin.com/registration-confirmation.php?token='.$token.'" style="">
                                                    <button style="display: inline-block;  outline: 0; border: 0; background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% ); padding: 0 32px; border-radius: 6px; color: #fff; height: 48px; font-size: 18px; text-shadow: 0 1px 0 rgb(0 0 0 / 40%); margin-bottom: 50px;">REGISZTRÁCIÓ MEGERŐSÍTÉSE</button>
                                                </a>
                                                <p>Találkozunk a pályán, leendő fantasy mester..</p>
                                            </div>
                                        </div>
                                    </body>
                                    </html>';
                    $mail->Body = $mailcontent;
                break;
                case 2:
                    $mail->isHTML(true); 
                    $mail->Subject = "Fantasy 9pin registration";
                    $mailcontent='<html>
                                    <body>
                                        <div class="container" style="width: 100%; max-width: 600px; margin: 0 auto; padding: 20px;">
                                            <div class="header" style="background-color: #f1f1f1; padding: 10px 20px;">
                                                <h1 style="margin: 0; background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);-webkit-background-clip: text;  background-clip: text;  -webkit-text-fill-color: transparent;  margin-bottom: 0; text-align: center;">Welcome to Fantasy 9pin League</h1>
                                            </div>
                                            <div class="content" style="margin: 20px 0;">
                                                <h4 style="margin-bottom: 40px;">Dear '.$alias.',</h4>
                                                <p style="line-height: 1.5;">We are excited that you decided to test your skills and become a fantasy 9pin trainer. The road to win the league is long and hard, but it starts with a simple step: confirm your registration.</p>
                                                <p style="line-height: 1.5;">So in order to start assembling your fantasy team, please click on this link:</p>
                                                <a href="http://fantasy9pin.com/registration-confirmation.php?token='.$token.'" style="">
                                                    <button style="display: inline-block;  outline: 0; border: 0; background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% ); padding: 0 32px; border-radius: 6px; color: #fff; height: 48px; font-size: 18px; text-shadow: 0 1px 0 rgb(0 0 0 / 40%); margin-bottom: 50px;">CONFIRM REGISTRATION</button>
                                                </a>
                                                <p>See you down the alley, future fantasy master..</p>
                                            </div>
                                        </div>
                                    </body>
                                    </html>';
                    $mail->Body = $mailcontent;
                break;
                case 3:
                    $mail->isHTML(true); 
                    $mail->Subject = "Fantasy 9pin Registrierung";
                    $mailcontent='<html>
                                    <body>
                                        <div class="container" style="width: 100%; max-width: 600px; margin: 0 auto; padding: 20px;">
                                            <div class="header" style="background-color: #f1f1f1; padding: 10px 20px;">
                                                <h1 style="margin: 0; background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);-webkit-background-clip: text;  background-clip: text;  -webkit-text-fill-color: transparent;  margin-bottom: 0; text-align: center;">Welcome to Fantasy 9pin League</h1>
                                            </div>
                                            <div class="content" style="margin: 20px 0;">
                                                <h4 style="margin-bottom: 40px;">Liebe(r) '.$alias.',</h4>
                                                <p style="line-height: 1.5;">Wir freuen uns, dass du dich entschieden hast, deine Fähigkeiten zu testen und ein Fantasy 9pin Trainer zu werden. Der Weg zum Gewinn der Liga ist lang und hart, aber er beginnt mit einem einfachen Schritt: Bestätige deine Registrierung.</p>
                                                <p style="line-height: 1.5;">Um mit der Zusammenstellung deines Fantasy Teams zu beginnen, klicke bitte auf diesen Link:</p>
                                                <a href="http://fantasy9pin.com/registration-confirmation.php?token='.$token.'" style="">
                                                    <button style="display: inline-block;  outline: 0; border: 0; background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% ); padding: 0 32px; border-radius: 6px; color: #fff; height: 48px; font-size: 18px; text-shadow: 0 1px 0 rgb(0 0 0 / 40%); margin-bottom: 50px;">REGISTRIERUNG BESTÄTIGEN</button>
                                                </a>
                                                <p>Wir sehen uns auf der Bahn, zukünftiger Fantasy-Meister..</p>
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

            echo '<script type="text/javascript">location.href="successful-signup.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=successful-signup.php" /></noscript> ';

            // $result = $webuser->getUser($email,$new_password);
            // $_SESSION['email'] = $email;
            // $_SESSION['alias'] = $result['alias'];
            // $_SESSION['profilename'] = $result['profilename'];        
            // $_SESSION['profile_id'] = $result['profile_id'];
            // $_SESSION['authorization'] = $result['authorization'];
            // $_SESSION['lang'] = $result['lang_id'];
            
            // echo '<script type="text/javascript">location.href="index.php";</script>
            // <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
            
            
        }
        else{
            echo $e->getMessage();
        }
    }
}
} 
?>

<style>
    span{
        font-size:11px;
    }
    .disclaimer{
        text-align: right;
        font-size:12px;
    }

    .regbox {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        position: relative;
        margin-top: 2rem;
        
        
        font-family: "Roboto", helvetica, arial, sans-serif;
        font-size: 1.5em;
        
        &:before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            height: 100%;
            width: 100%;
        
        }
    }

    .reg-form {
        width: 100%;
        padding: 2em;
        position: relative;
        background: rgba(black, .15);
        
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
        
        @media screen and (min-width: 800px) {
            width: 50vw;
            max-width: 40em;
        }
        @media (max-width: 490px){
            padding: 1em;
        }
    }

    .flex-row {
        display: flex;
        margin-bottom: 1em;
        border: 1px solid black;
    }
  
    .lf--label {
        width: 10em;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        background: #f5f6f8;
        cursor: pointer;
        font-size: 1rem;
        flex-basis: 6rem;
        flex-shrink: 0;
        flex-grow: 0;

        @media (max-width: 490px){
            font-size: 0.7rem;
        }
    }
    .lf--input {
        flex: 1;
        padding: 1em;
        border: 0;
        color: #8f8f8f;
        font-size: 1rem;
        @media (max-width: 490px){
            font-size: 0.7rem;
        }

        &:focus {
        outline: none;
        transition: transform .15s ease;
        transform: scale(1.1);
        }
    }
    .lf--submit {
        display: block;
        padding: 1em;
        width: 100%;
        
        background: linear-gradient(
        to right,
        #35c3c1,
        #00d6b7
        );
        border: 0;
        color: #fff;
        cursor: pointer;
        font-size: .75em;
        font-weight: 600;
        text-shadow: 0 1px 0 rgba(black, .2);
        max-width: 15em;
        margin: auto;
        
        &:focus {
        outline: none;
        transition: transform .15s ease;
        transform: scale(1.1);
        }
    }

    ::placeholder { color: #8f8f8f; }


    [type=radio] { 
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    }

    [type=radio] + img {
    cursor: pointer;
    }

    [type=radio]:checked + img {
    outline: 2px solid #f00;
    }

    .picselection h6{
        margin-top:1.5rem;
    }
    .profpic{
        width: 50px;
        height: 50px;
        border-radius: 50%;
        padding: 5px;
    }

    .locked{
        filter: grayscale(100%);
    }

</style>

<div class="regbox">
    <h1 class="text-center">
        <?php switch($_SESSION['lang']){
            case 1: echo "Regisztráció";
            break;
            case 2: echo "Sign up";
            break;
            case 3: echo "Registrierung";
            break;
        }?> 
    </h1>

    <form name="registration" class="reg-form" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
        <div class="flex-row">
            <label for="email" class="lf--label">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Email cím";
                    break;
                    case 2: echo "Email address";
                    break;
                    case 3: echo "E-Mail-Adresse";
                    break;
                }?> 
            </label>
            <input required type="email" class="lf--input" id="email" name="email">
        </div>
        <div class="flex-row">
            <label for="profilename" class="lf--label">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Név";
                    break;
                    case 2: echo "Name";
                    break;
                    case 3: echo "Name";
                    break;
                }?>
            </label>
            <input required type="text" class="lf--input" id="profilename" name="profilename">
        </div>
        <div class="flex-row">
            <label for="alias" class="lf--label">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Fantasy edző név";
                    break;
                    case 2: echo "Fantasy alias";
                    break;
                    case 3: echo "Fantasy Trainer Name";
                    break;
                }?>
            </label>
            <input type="text" class="lf--input" id="alias" name="alias">
        </div>
        <div class="flex-row">
            <label for="password" class="lf--label">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Jelszó";
                    break;
                    case 2: echo "Password";
                    break;
                    case 3: echo "Passwort";
                    break;
                }?>
            </label>
            <input required type="password" class="lf--input" id="password" name="password">
        </div>
        <div class="flex-row">
            <?php 
                $currlang=$crud->getCurrentLanguage($_SESSION['lang']);
                $langlist=$crud->getLanguageList();
            ?>
            <label for="language" class="lf--label">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Nyelv";
                    break;
                    case 2: echo "Default language";
                    break;
                    case 3: echo "Sprache";
                    break;
                }?>
            </label>
            <select required type="text" class="form-select" id="language" name="language">
                <option value="<?php echo $currlang['lang_id'] ?>" selected><?php echo $currlang['emoji']." ".$currlang['language'];?></option>
                
                <?php while($r=$langlist->fetch(PDO::FETCH_ASSOC)) {
                    if($r['lang_id']!=$_SESSION['lang']){?>
                    <option value="<?php echo $r['lang_id'] ?>"><?php echo $r['emoji']." ".$r['language'] ?> </option>
                <?php } }  ?> 
            </select>
        </div>
        <!-- Newsletter subscription pill switch -->
        <div style="display: flex; align-items: center; margin: 1em 0 1em 0;">
            <div id="newsletter-pill" style="width:50px; height:28px; background:#35c3c1; border-radius:14px; position:relative; cursor:pointer; transition:background 0.2s; margin-right:12px;">
                <div id="newsletter-knob" style="width:24px; height:24px; background:#fff; border-radius:50%; position:absolute; top:2px; left:2px; transition:left 0.2s;"></div>
            </div>
            <input type="hidden" id="newsletter" name="newsletter" value="1">
            <span id="newsletter-label" style="font-size:1rem; font-weight:600; color:#222;">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Feliratkozom a hírlevélre";
                    break;
                    case 2: echo "Subscribe to newsletter";
                    break;
                    case 3: echo "Newsletter abonnieren";
                    break;
                }?>
            </span>
        </div>
        <script>
            // Newsletter pill switch logic
            const pill = document.getElementById('newsletter-pill');
            const knob = document.getElementById('newsletter-knob');
            const input = document.getElementById('newsletter');
            const label = document.getElementById('newsletter-label');
            let subscribed = true;
            function updateSwitch() {
                if (subscribed) {
                    knob.style.left = '2px';
                    pill.style.background = '#35c3c1';
                    label.style.color = '#222';
                    input.value = '1';
                } else {
                    knob.style.left = '24px';
                    pill.style.background = '#ccc';
                    label.style.color = '#222';
                    input.value = '0';
                }
            }
            pill.onclick = function() {
                subscribed = !subscribed;
                updateSwitch();
            };
            updateSwitch();
        </script>
        <!-- End newsletter pill switch -->
        <div class="picselection">
            <h6><?php switch($_SESSION['lang']){
                case 1: echo "Profilkép";
                break;
                case 2: echo "Profile picture";
                break;
                case 3: echo "Profilbild";
                break;
            }?> </h6>
            <?php 
                $pictures=$crud->getAllPictures();
                while($r=$pictures->fetch(PDO::FETCH_ASSOC)){ ?>
                    <label>
                        <input type="radio" name="profilepic" value="<?=$r['picture_id'] ?>" <?php if($r['basic'] == 0){echo "disabled";} ?>>
                        <img class="profpic <?php if($r['basic'] == 0){echo "locked";} ?>" src="img/profilepic/<?=$r['link'] ?>" title="<?php if($r['basic'] == 0){$pictext=$crud->getPictureText($r['picture_id'],$_SESSION['lang']); if($pictext) echo $pictext['description'];} ?>">
                    </label>
                <?php }
            ?>
        </div>
        </br>
        <?php switch($_SESSION['lang']){
            case 1:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Regisztráció">
            <?php ;
            break;

            case 2:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Sign up">
            <?php ;
            break;

            case 3:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Registrieren">
            <?php ;
            break;

        }?>
    </form>

</div>



<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>