<?php 
$title = "Profilecheck";
require_once 'includes/header.php';
require_once 'db/conn.php';

$felhaszn=$webuser->getUserbyID($_SESSION['profile_id']);

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['submit'])){
    
    if($_POST['profilename'] !== $_SESSION['profilename']){
        $newname = $webuser->updateProfilename($_SESSION['profile_id'], $_POST['profilename']);
        $_SESSION['profilename'] = $_POST['profilename']; // Update session name
    }
    
    if($_POST['alias'] !== $_SESSION['alias']){
        $newalias = $webuser->updateAlias($_SESSION['profile_id'], $_POST['alias']);
        $_SESSION['alias'] = $_POST['alias']; // Update session alias
    }

    if($_POST['language'] !== $_SESSION['lang']){
        $newlang = $webuser->updateLanguage($_SESSION['profile_id'], $_POST['language']);
        $_SESSION['lang'] = $_POST['language']; // Update session language
    }
    
    
    $newsletter = $_POST['newsletter'] ?? 0; // Default to 0 if not set
    $news=$webuser->updateNewsletterSub($_SESSION['profile_id'], $newsletter);


    if(isset($_POST['profilepic'])){
        $picture_id=$_POST['profilepic'];
        $newpic = $webuser->updateProfilepic($_SESSION['profile_id'], $picture_id);
    }
    
    $deletetoken=$webuser->deleteRegistrationToken($_SESSION['email']);

    echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

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
            case 1: echo "Profil ellenőrzés";
            break;
            case 2: echo "Profile check";
            break;
            case 3: echo "Profil Überprüfung";
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
            <input required type="email" class="lf--input" id="email" name="email" value="<?php echo $_SESSION['email']?>" disabled>
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
            <input required type="text" class="lf--input" id="profilename" name="profilename" value="<?php echo $_SESSION['profilename']?>">
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
            <input type="text" class="lf--input" id="alias" name="alias" value="<?php echo $_SESSION['alias']?>">
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
                    case 1: echo "Kérek tippeket és emlékeztetőket a csapatomhoz.";
                    break;
                    case 2: echo "Keep me updated with tips and reminders for my team.";
                    break;
                    case 3: echo "Schick mir Tipps und Erinnerungen für mein Team.";
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
                case 1: echo "Válassz profilképet";
                break;
                case 2: echo "Select profile picture";
                break;
                case 3: echo "Profilbild auswählen";
                break;
            }?> </h6>
            <?php 
                $pictures=$crud->getAllPictures();
                $extrapictures=$crud->getExtraPicturesForProfile($_SESSION['profile_id']);
                while($r=$extrapictures->fetch(PDO::FETCH_ASSOC)){ 
                    $pic=$crud->getPicture($r['picture_id']);
                    if(isset($_GET['notype']) AND $_GET['notype']=="A1"){
                        $pictureIds = array_column($notifications, 'picture_id');
                        if (in_array($r['picture_id'], $pictureIds)) {
                            $newpic=true;
                        } else {
                            $newpic=false;
                        }
                    }else{$newpic=false;}
                    ?>
                    <label class="profile-picture">
                        <input type="radio" name="profilepic" value="<?=$pic['picture_id'] ?>">
                        <img class="profpic <?php if($newpic) echo 'newpic';?>" <?php if($newpic) echo 'id="newpic'.$pic['picture_id'].'"';?> src="img/profilepic/<?=$pic['link'] ?>" title="<?php $pictext=$crud->getPictureText($pic['picture_id'],$_SESSION['lang']); if($pictext) echo $pictext['description']; ?>">
                        <?php if($pictext){ ?>
                            <div class="unlock-info" style="display:none;">
                                <?php if($pictext) echo $pictext['description']; ?>
                            </div>
                        <?php } ?>
                    </label>
                <?php }
                while($r=$pictures->fetch(PDO::FETCH_ASSOC)){ 
                    $extrapiccheck=$crud->findExtraPicture($r['picture_id'],$_SESSION['profile_id']);
                    if($extrapiccheck['count']==0){?>
                    <label class="profile-picture">
                        <input type="radio" name="profilepic" value="<?=$r['picture_id'] ?>" <?php if($r['basic'] == 0){echo "disabled";} ?>>
                        <img class="profpic <?php if($r['basic'] == 0){echo "locked";} ?>" src="img/profilepic/<?=$r['link'] ?>" title="<?php if($r['basic'] == 0){$pictext=$crud->getPictureText($r['picture_id'],$_SESSION['lang']); if($pictext) echo $pictext['description'];} ?>">
                        <?php $pictext=$crud->getPictureText($r['picture_id'],$_SESSION['lang']);
                        if($pictext){ ?>
                            <div class="unlock-info" style="display:none;">
                                <?php if($pictext) echo $pictext['description']; ?>
                            </div>
                        <?php } ?>
                    </label>
                <?php }}
            ?>
        </div>
        </br>
        <?php switch($_SESSION['lang']){
            case 1:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Mentés">
            <?php ;
            break;

            case 2:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Save">
            <?php ;
            break;

            case 3:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Speichern">
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