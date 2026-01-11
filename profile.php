<?php 
$title = "Profil";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if(isset($_GET['notype'])){
    $notificationlist=$crud->getPictureNotificationsForUser($_SESSION['profile_id'],$_SESSION['lang']);
    $notifications=$notificationlist->fetchAll();
    foreach ($notifications as $n) {
        $markread=$crud->markNotificationAsRead($n['notification_id']);
    }
}

//ha új csapatot akarna csinálni, mást nem is kell vizsgálni
if(isset($_POST['makeateam-de'])){
    $_SESSION['league']=20;
    echo '<script type="text/javascript">location.href="teamselection.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=teamselection.php" /></noscript> ';
}
if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['makeateam-dew'])){
    $_SESSION['league']=40;
    echo '<script type="text/javascript">location.href="teamselection.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=teamselection.php" /></noscript> ';
}

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['makeateam-hu'])){
    $_SESSION['league']=10;
    echo '<script type="text/javascript">location.href="teamselection.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=teamselection.php" /></noscript> ';
}

//ha jelszót változtatott
if(isset($_POST['save-new-password'])){
    $email = $_SESSION['email'];
    $oldpassword=md5($_POST['current-password'].$email);
    $newpass=$_POST['new-password'];

    $result = $webuser->getUser($email,$oldpassword);

    if(!$result){
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-danger">Jelenlegi jelszó nem helyes</div>';
            break;
            case 2: echo '<div class="alert alert-danger">Current password not correct</div>';
            break;
            case 3: echo '<div class="alert alert-danger">Das aktuelle Passwort ist nicht korrekt.</div>';
            break;
        }
        
    }elseif($_POST['new-password'] !== $_POST['repeat-new-password']){
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-danger">Nem sikerült kétszer ugyanazt az új jelszót beírni</div>';
            break;
            case 2: echo '<div class="alert alert-danger">You did not manage to enter the same new password twice</div>';
            break;
            case 3: echo '<div class="alert alert-danger">Du hast das neue Passwort nicht zweimal gleich eingegeben.</div>';
            break;
        }
    }else{
        $newpassword=$webuser->enterNewPassword($_SESSION['profile_id'],$email,$newpass);
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-success">Jelszavadat sikeresen megváltoztattad</div>';
            break;
            case 2: echo '<div class="alert alert-success">Your password was changed successfully</div>';
            break;
            case 3: echo '<div class="alert alert-success">Dein Passwort wurde erfolgreich geändert.</div>';
            break;
        }
    }
}

if(isset($_POST['submit'])){
    $newalias=$webuser->updateAlias($_SESSION['profile_id'],$_POST['alias']);

    $newsletter = $_POST['newsletter'] ?? 0; // Default to 0 if not set
    $news=$webuser->updateNewsletterSub($_SESSION['profile_id'], $newsletter);
    
    if(isset($_POST['profilepic'])){
        $newprofilepic=$webuser->updateProfilepic($_SESSION['profile_id'],$_POST['profilepic']);
    }
    $newlanguage=$webuser->updateLanguage($_SESSION['profile_id'],$_POST['language']);
    if(isset($_POST['de-teamname'])){
        $decomp=$crud->getCompetitorID($_SESSION['profile_id'],20);
        $newdename=$crud->updateTeamname($decomp['competitor_id'],$_POST['de-teamname']);
    }
    if(isset($_POST['de-favorite-team']) AND ($_POST['de-favorite-team'] != $decomp['favorite_team_id'])){
        if($decomp['favorite_team_id'] != 0){
            $removefromleague=$crud->removePLmember($decomp['favorite_team_id'],$decomp['competitor_id']);
        }
        $defavteam=$crud->updateFavoriteTeam($decomp['competitor_id'],$_POST['de-favorite-team']);
        $newleaguemember=$crud->newPLmemberbyAdmin($_POST['de-favorite-team'],$decomp['competitor_id']);
    }

    if(isset($_POST['dew-teamname'])){
        $dewcomp=$crud->getCompetitorID($_SESSION['profile_id'],40);
        $newdewname=$crud->updateTeamname($dewcomp['competitor_id'],$_POST['dew-teamname']);
    }
    if(isset($_POST['dew-favorite-team']) AND ($_POST['dew-favorite-team'] != $dewcomp['favorite_team_id'])){
        if($dewcomp['favorite_team_id'] != 0){
            $removefromleague=$crud->removePLmember($dewcomp['favorite_team_id'],$dewcomp['competitor_id']);
        }
        $dewfavteam=$crud->updateFavoriteTeam($dewcomp['competitor_id'],$_POST['dew-favorite-team']);
        $newleaguemember=$crud->newPLmemberbyAdmin($_POST['dew-favorite-team'],$dewcomp['competitor_id']);
    }

    if(isset($_POST['hu-teamname'])){
        $hucomp=$crud->getCompetitorID($_SESSION['profile_id'],10);
        $newhuname=$crud->updateTeamname($hucomp['competitor_id'],$_POST['hu-teamname']);
    }
    if(isset($_POST['hu-favorite-team']) AND ($_POST['hu-favorite-team'] != $hucomp['favorite_team_id'])){
        if($hucomp['favorite_team_id'] != 0){
            $removefromleague=$crud->removePLmember($hucomp['favorite_team_id'],$hucomp['competitor_id']);
        }
        $hufavteam=$crud->updateFavoriteTeam($hucomp['competitor_id'],$_POST['hu-favorite-team']);
        $newleaguemember=$crud->newPLmemberbyAdmin($_POST['hu-favorite-team'],$hucomp['competitor_id']);
    }
    switch($_SESSION['lang']){
        case 1: echo '<div class="alert alert-success">Sikeres adatváltozás</div>';
        break;
        case 2: echo '<div class="alert alert-success">Your data was changed successfully</div>';
        break;
        case 3: echo '<div class="alert alert-success">Deine Daten wurden erfolgreich geändert.</div>';
        break;
    }
}


$playercheck=$webuser->getUserbyID($_SESSION['profile_id']);
$email=$playercheck['email'];
$alias=$playercheck['alias'];
$picture=$crud->getPicture($playercheck['picture_id']);
$language=$crud->getCurrentLanguage($playercheck['lang_id']);
$langlist=$crud->getLanguageList();
$huteamlist= $crud->getTeamsinLeague(10);
$deteamlist= $crud->getTeamsinLeague(20);
$dewteamlist= $crud->getTeamsinLeague(40);

//megnézzük van-e magyar csapata, ha igen összegyűjtünk minden adatot
$competitorinhu=$crud->getCompetitorInLeague($_SESSION['profile_id'],10);
if($competitorinhu['count'] > 0){ 
    $hucomp=$crud->getCompetitorID($_SESSION['profile_id'],10); 
}
if(isset($hucomp)){
    $hugameweek = $crud->getGameweek(10);
    $huweek = $hugameweek['gameweek'];
    $hucredit=$hucomp['credits'];
    $hufavteam=$hucomp['favorite_team_id'];
    $huroster=$crud->getRoster($hucomp['competitor_id'],$huweek);
    $hutotalpoints=$crud->getTotalteamresult($hucomp['competitor_id'],$huweek);
    if($huweek>1){
        $hurank=$crud->getTeamrank($hucomp['competitor_id'],$huweek-1);
    };
    
}


//ugyanez némettel
$competitorinde=$crud->getCompetitorInLeague($_SESSION['profile_id'],20);
if($competitorinde['count'] > 0){ 
    $decomp=$crud->getCompetitorID($_SESSION['profile_id'],20);
}
if(isset($decomp)){
    $degameweek = $crud->getGameweek(20);
    $deweek = $degameweek['gameweek'];
    $decredit=$decomp['credits'];
    $defavteam=$decomp['favorite_team_id'];
    $deroster=$crud->getRoster($decomp['competitor_id'],$deweek);
    $detotalpoints=$crud->getTotalteamresult($decomp['competitor_id'],$deweek);
    if($deweek>1){
        $derank=$crud->getTeamrank($decomp['competitor_id'],$deweek-1);
    };
    
}

//ugyanez női némettel
$competitorindew=$crud->getCompetitorInLeague($_SESSION['profile_id'],40);
if($competitorindew['count'] > 0){ 
    $dewcomp=$crud->getCompetitorID($_SESSION['profile_id'],40);
}
if(isset($dewcomp)){
    $dewgameweek = $crud->getGameweek(40);
    $dewweek = $dewgameweek['gameweek'];
    $dewcredit=$dewcomp['credits'];
    $dewfavteam=$dewcomp['favorite_team_id'];
    $dewroster=$crud->getRoster($dewcomp['competitor_id'],$dewweek);
    $dewtotalpoints=$crud->getTotalteamresult($dewcomp['competitor_id'],$dewweek);
    if($dewweek>1){
        $dewrank=$crud->getTeamrank($dewcomp['competitor_id'],$dewweek-1);
    };
    
}

//privát liga kilépés
if(isset($_POST['exitprleague'])){
    $exitpostpart = explode('_',$_POST['exitprleague']);
    $removefromleague=$crud->removePLmember($exitpostpart[0],$exitpostpart[1]);
}



?>

<style>

    .profile-main {
        max-width: 700px;
        margin: auto;
        padding: 20px;
        background-color: #e1f7f4;
        border: 1px solid #ccc;
        border-radius: 8px;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
    }

    .profile-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }

    .profile-picture {
        margin-right: 20px;
    }

    .profile-picture img {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid #ccc;
        object-fit: cover;
        transition: border-color 0.3s;
    }

    .profile-picture img:hover {
        border-color: #007bff;
    }

    .profile-name p {
        font-size: 9pt;
        font-style: italic;
        margin-bottom: 0;
    }

    .profile-name input {
        width: 100%;
        padding: 2px 8px;
        border-radius: 4px;
        border: 1px solid #ccc;
        font-weight: bold;
    }

    .picselection {
        margin-top: 15px;
        margin-bottom: 20px;
        background-color: #fff;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
    }

    .picselection h6 {
        margin-bottom: 10px;
    }

    .picselection label {
        margin-right: 10px;
    }

    .picselection img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid transparent;
        object-fit: cover;
        transition: border-color 0.3s;
    }

    .picselection img.locked {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .picselection .newpic{
        border: 2px solid red;
    }

    .hidden{
        display:none;
    }

    .locked{
        filter: grayscale(100%);
        cursor: not-allowed;
    }

    .picselection input[type="radio"]:checked + img {
        border-color: #007bff;
    }

    [type=radio] { 
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    /* .profile-details {
        margin-bottom: 20px;
    } */

    .profile-details div {
        margin-bottom: 10px;
    }

    .profile-details label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .profile-details input[type="text"],
    .profile-details select {
        width: 100%;
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }

    #password-section {
        display: flex;
        align-items: center;
    }

    #password-section button {
        margin-left: 10px;
        padding: 5px 10px;
        border-radius: 4px;
        background-color: #007bff;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    #password-placeholder{
        margin-left: 10px;
        border-radius: 4px;
        border: 1px solid #ccc;
        padding: 4px;
    }

    #password-section button:hover {
        background-color: #0056b3;
    }

    #change-password-section {
        display: none;
        margin-top: 10px;
    }

    #change-password-section input[type="password"] {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }

    #change-password-section button {
        margin-left: 10px;
        padding: 5px 10px;
        border-radius: 4px;
        background-color: #e57067;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    #save-new-password{
        margin-left: 10px;
        padding: 5px 10px;
        border-radius: 4px;
        background-image: linear-gradient(#42A1EC, #0070C9);
        color: #fff;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    #change-password-section button:hover {
        background-color: #df5146;
    }

    .teams-section {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin:auto;
        max-width: 700px;
    }

    .teams-section .team {
        background-color: #fff;
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 8px;
        min-height: 200px;
        width: 100%;
        margin-left: auto;
        margin-right: auto;
        margin-top: 5px;
    }

    .teams-section h4 {
        text-align: center;
        margin-bottom: 1.5rem;
        font-weight: bold;
    }

    .teams-section label {
        display: block;
        margin-bottom: 5px;
    }

    .teams-section input[type="text"] {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }

    .teams-section h6 {
        margin: 10px 0;
    }

    .teams-section ul {
        list-style-type: disc;
        padding-left: 20px;
    }

    .teams-section ul li {
        margin-bottom: 5px;
    }

    .make-team {
        margin-top: 15px;
        text-align: center;
    }

    .make-team input[type="submit"] {
        padding: 8px 15px;
        border-radius: 4px;
        background-color: #28a745;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .make-team input[type="submit"]:hover {
        background-color: #218838;
    }

    .save {
        text-align: center;
        max-width: 700px;
        margin: auto;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .save input[type="submit"] {
        padding: 12px 20px;
        border-radius: 8px;
        background-color: #1bc1db;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s;
        font-size: 20px;
        font-variant-caps: all-small-caps;
        width: 100%;
    }

    .save input[type="submit"]:hover {
        background-color: #19b2ca;
    }

    .profile-delete-section{
        display: flex;
    }

    .deleteprofile{
        display: inline-block;
        padding: 8px 16px;
        margin:auto;
        margin-top: 2rem;
        color: #fff;
        background-color: grey; 
        border: none;
        border-radius: 4px;
        text-decoration: none; 
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background-color 0.3s, box-shadow 0.3s;
    }
    .deleteprofile:hover {
        background-color: #c82333; 
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.2); 
    }

    .teams-section a{
        background-color: #e57067;
        font-size: 10px;
    }

    .myleagues {
        max-width: 700px;
        margin: auto;
        padding: 20px;
        background-color: #e1f7f4;
        border: 1px solid #ccc;
        border-radius: 8px;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .myleagues h5 {
        margin-bottom: 2.5rem;
    }

    .create-league-btn {
        display: block;
        text-align: center;
        margin: 2.5rem 0;
        margin-top:0;
    }

    .create-league-btn a {
        font-size: 14px;
        padding: 15px 30px;
        background-color: #2b7a78;
        color: white;
        border: none;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        transition: background-color 0.3s ease;
    }

    .create-league-btn a:hover {
        background-color: #3aafa9;
    }

    .leaguelist table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .leaguelist table tr {
        border-bottom: 1px solid #ccc;
    }

    .leaguelist table td {
        padding: 10px;
        text-align: left;
        font-size: 16px;
    }

    .leaguelist table td a {
        color: #0b2423;
        text-decoration: none;
        font-weight: bold;
    }

    table td:nth-of-type(2) {
        display: inline-flex;
    }

    .leaguelist td span{
        margin-left: 5px;
        margin-right: 3px;
    }

    .leaguelist table td a:hover {
        text-decoration: underline;
    }

    .btn-custom {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        margin-left: 10px;
        display: inline-flex;
        width: 75px;
        justify-content: center;
    }

    .btn-edit {
        background-color: #3aafa9;
        color: white;
    }

    .btn-edit:hover {
        background-color: #308986;
    }

    .btn-waiting {
        background-color: #ffcc00;
        color: black;
    }

    .btn-exit {
        background-color: #ff6b6b;
        color: white;
    }

    .btn-exit:hover {
        background-color: #ff4d4d;
    }
    .btn-exit i{
        margin-left: 4px;
    }

    
    @media (max-width: 700px) {
        .teams-section {
            grid-template-columns: 1fr;
        }

        .profile-header {
            align-items: flex-start;
        }

        #password-section {
            flex-direction: column;
            align-items: flex-start;
        }

        #password-section button {
            margin-left: 0;
            margin-top: 10px;
        }

        #password-placeholder{
            margin-left: 0;
        }

        .team{
            width: 100%;
        }
        .btn-custom {
            padding: 4px 12px;
            font-size: 12px;
            margin-left: 5px;
            display: inline-flex;
        }
        .leaguelist table td {
            padding: 5px;
            font-size: 12px;
        }
        table td:nth-of-type(1) {
            word-wrap: break-word;
            word-break: break-all;
        }
        .leaguelist td span{
            display: none;
        }
    }

    .notifications{
        max-width: 700px;
        margin: auto;
        margin-top: 10px;
    }

    .noti-item {
        margin-bottom: 5px;
        padding: 5px;
        border: 1px solid lightgray;
        border-radius: 8px;
        display: flex;
        align-items: center;
        background-color: #fafad2;
    }

    .noti-image {
        width: 100%;
        height: 30px;
        max-width: 30px;
        object-fit: cover;
        border-radius: 50%;
        float: left;
        margin-right: 10px;
    }

    .noti-item h3 {
        margin: 0 0 10px 0;
        font-size: 18px;
    }

    .noti-item p {
        margin: 0;
        font-size: 13px;
        font-style: italic;
        color: #555;
    }

    .unlock-info {
        position: absolute;
        top: -50px;
        left: 50%;
        transform: translateX(-50%);
        background-color: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 10px;
        border-radius: 5px;
        font-size: 12px;
        z-index: 100;
        width: max-content;
        white-space: nowrap;
        display: none; 
    }
    
    .profile-picture {
        position: relative; 
    }
    
    .unlock-info::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        border-width: 10px;
        border-style: solid;
        border-color: rgba(0, 0, 0, 0.8) transparent transparent transparent;
    }

    .teams-section select[name="de-favorite-team"],
    .teams-section select[name="hu-favorite-team"],
    .teams-section select[name="dew-favorite-team"] {
        min-width: 120px;
        border-radius: 4px;
        border: 1px solid #ccc;
        margin-bottom: 3rem;
        margin-top: 0.5rem;
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
        background-color: #fff;
        transition: background-color 0.2s;
    }

    .teams-section select[name="de-favorite-team"]:disabled,
    .teams-section select[name="hu-favorite-team"]:disabled,
    .teams-section select[name="dew-favorite-team"]:disabled {
        background-color: #e9ecef;
    }

</style>

<?php 
    if(!empty($notifications)){
    ?>
      <div class="notifications">
        <?php
        foreach ($notifications as $noti) {
          ?>
            <div class='noti-item'>
              <img src='img/profilepic/<?= $noti['link'] ?>' alt='' class='noti-image'>
              <div class='noti-text'>
                <p><?php echo $noti['text'] . ': ' . $noti['description']; ?></p>
              </div>
            </div>
        <?php }?>
      </div>
    <?php }?>

<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">

    <div class="profile-main">
        <div class="profile-header">
            <div class="profile-picture">
                <img src="img/profilepic/<?= $picture['link'] ?>" class="profilepic" id="profilepic"alt="">
            </div>
            <div class="profile-name">
                <p id="description"><?php switch($_SESSION['lang']){
                        case 1: echo "Fantasy edző név";
                        break;
                        case 2: echo "Fantasy alias";
                        break;
                        case 3: echo "Fantasy Trainer Name";
                        break;
                    }?></p>
                <input type="text" id="alias" name="alias" class="alias" value="<?= $alias ?>">
            </div>
        </div>    
        <div class="picselection hidden" id="picselection">
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
        

        <div class="profile-details" id="profile-details">
            <div>
                <div class="language-section">
                    <label for="default-language"><?php switch($_SESSION['lang']){
                            case 1: echo "Nyelv:";
                            break;
                            case 2: echo "Default language:";
                            break;
                            case 3: echo "Sprache:";
                            break;
                        }?></label>
                    <select id="default-language" name="language">
                        <option value="<?= $language['lang_id'] ?>" selected><?php echo $language['emoji']." ".$language['language'];?></option>   
                        <?php while($r=$langlist->fetch(PDO::FETCH_ASSOC)) {
                            if($r['lang_id']!=$_SESSION['lang']){?>
                            <option value="<?php echo $r['lang_id'] ?>"><?php echo $r['emoji']." ".$r['language'] ?> </option>
                        <?php } }  ?>
                    </select>
                </div>
                <div class="email-section">
                    <label for="email">E-mail: </label>
                    <input type="text" name="email" id="email" placeholder="<?= $email?>" readonly>
                </div>
            </div>
            <div id="password-section">
                <label for="password"><?php switch($_SESSION['lang']){
                        case 1: echo "Jelszó: ";
                        break;
                        case 2: echo "Password: ";
                        break;
                        case 3: echo "Passwort: ";
                        break;
                    }?></label>
                <input type="password" id="password-placeholder" placeholder="********" readonly>
                <button id="change-password-btn"><?php switch($_SESSION['lang']){
                        case 1: echo "Jelszó változtatás";
                        break;
                        case 2: echo "Change Password";
                        break;
                        case 3: echo "Passwort ändern";
                        break;
                    }?></button>
            </div>
            <div id="change-password-section" style="display: none;">
                <label for="password"><?php switch($_SESSION['lang']){
                        case 1: echo "Jelszó változtatás";
                        break;
                        case 2: echo "Change Password";
                        break;
                        case 3: echo "Passwort ändern";
                        break;
                    }?></label>
                <input type="password" name="current-password" id="current-password" placeholder="<?php switch($_SESSION['lang']){
                        case 1: echo "Jelenlegi jelszó";
                        break;
                        case 2: echo "Current password";
                        break;
                        case 3: echo "Aktuelles Passwort";
                        break;
                    }?>">
                <input type="password" name="new-password" id="new-password" placeholder="<?php switch($_SESSION['lang']){
                        case 1: echo "Új jelszó";
                        break;
                        case 2: echo "New password";
                        break;
                        case 3: echo "Neues Passwort";
                        break;
                    }?>">
                <input type="password" name="repeat-new-password" id="repeat-new-password" placeholder="<?php switch($_SESSION['lang']){
                        case 1: echo "Új jelszó újra";
                        break;
                        case 2: echo "Repeat new password";
                        break;
                        case 3: echo "Neues P/w wiederholen";
                        break;
                    }?>">
                <input type="submit" name="save-new-password" id="save-new-password" value="<?php switch($_SESSION['lang']){
                        case 1: echo "Mentés";
                        break;
                        case 2: echo "Save";
                        break;
                        case 3: echo "Speichern";
                        break;
                    }?>" >
                <button id="cancel-password-btn"><?php switch($_SESSION['lang']){
                        case 1: echo "Mégsem";
                        break;
                        case 2: echo "Cancel";
                        break;
                        case 3: echo "Abbrechen";
                        break;
                    }?></button>
            </div>
            <!-- Newsletter subscription pill switch -->
            <div style="display: flex; align-items: center; margin-top: 2rem;">
                <div id="newsletter-pill" style="width:50px; height:28px; background:#35c3c1; border-radius:14px; position:relative; cursor:pointer; transition:background 0.2s; margin-right:12px;">
                    <div id="newsletter-knob" style="width:24px; height:24px; background:#fff; border-radius:50%; position:absolute; top:2px; left:2px; transition:left 0.2s;"></div>
                </div>
                <input type="hidden" id="newsletter" name="newsletter" value="<?= $playercheck['newsletter_subscribe'] == 1 ? '1' : '0' ?>">
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
                let subscribed = input.value === "1";
                function updateSwitch() {
                    if (subscribed) {
                        knob.style.left = '2px';
                        pill.style.background = '#35c3c1';
                        label.style.color = '#222';
                        input.value = '1';
                    } else {
                        knob.style.left = '24px';
                        pill.style.background = '#ccc';
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
        </div>
    </div>

    <div class="teams-section">
        <div class="team">
            <h4>🇭🇺 Szuperliga</h4>
            <?php 
            if(isset($hucomp)){
            ?>
            <label for="hu-teamname"><?php switch($_SESSION['lang']){
                    case 1: echo "Csapatnév: ";
                    break;
                    case 2: echo "Team name: ";
                    break;
                    case 3: echo "Teamname: ";
                    break;
                }?></label>
            <input type="text" name="hu-teamname" id="hu-teamname" value="<?= $hucomp['teamname']?>">
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Helyezés:";
                    break;
                    case 2: echo "Current rank:";
                    break;
                    case 3: echo "Aktueller Platz:";
                    break;
                }?> <?php if(isset($hurank) AND ($hurank)){echo $hurank['rank'];}else{echo " - ";}?></h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Összes pont: ";
                    break;
                    case 2: echo "Total points: ";
                    break;
                    case 3: echo "Gesamtpunkte: ";
                    break;
                }?> <?= $hutotalpoints['totalpoints'] ?></h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Megmaradt keret: ";
                    break;
                    case 2: echo "Remaining budget: ";
                    break;
                    case 3: echo "Verbleibendes Budget: ";
                    break;
                }?> <?= $hucredit ?>M</h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Kedvenc csapat: ";
                    break;
                    case 2: echo "Favorite team: ";
                    break;
                    case 3: echo "Lieblingsverein: ";
                    break;
                }?>
                <select name="hu-favorite-team" id="hu-favorite-team" style="min-width:120px; background-color:<?= ($hucomp['favorite_team_changed'] == 1 ? '#e9ecef' : '#fff') ?>;" <?= ($hucomp['favorite_team_changed'] == 1 ? 'disabled' : '') ?>>
                    <option value="0"<?php if(empty($hufavteam) || $hufavteam==0) echo ' selected'; ?>>
                        <?php
                        switch($_SESSION['lang']){
                            case 1: echo "Válassz csapatot";
                            break;
                            case 2: echo "Select team";
                            break;
                            case 3: echo "Team wählen";
                            break;
                        }
                        ?>
                    </option>
                    <?php
                    while($team = $huteamlist->fetch(PDO::FETCH_ASSOC)){
                        $selected = ($hufavteam == $team['team_id']) ? ' selected' : '';
                        echo '<option value="'.$team['team_id'].'"'.$selected.'>'.$team['name'].'</option>';
                    }
                    ?>
                </select>
            </h6>
            
            
            <a class="btn btn-danger float-end" href="delete-team.php?league=hu">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Csapat törlése";
                    break;
                    case 2: echo "Delete team";
                    break;
                    case 3: echo "Team löschen";
                    break;
                }?>
            </a>
            <?php }else{ ?>
                <div class="make-team" >
                    <input type="submit" name="makeateam-hu" value="<?php switch($_SESSION['lang']){
                    case 1: echo "Új csapat készítése";
                    break;
                    case 2: echo "Make a team";
                    break;
                    case 3: echo "Team erstellen";
                    break;
                }?>">
                </div>
        
            <?php } ?>
        </div>
        <div class="team">
            <h4>🇩🇪 Bundesliga Men</h4>
            <?php 
            if(isset($decomp)){
            ?>
            <label for="de-teamname"><?php switch($_SESSION['lang']){
                    case 1: echo "Csapatnév:";
                    break;
                    case 2: echo "Team name:";
                    break;
                    case 3: echo "Teamname:";
                    break;
                }?></label>
            <input type="text" name="de-teamname" id="de-teamname" value="<?= $decomp['teamname']?>">
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Helyezés:";
                    break;
                    case 2: echo "Current rank:";
                    break;
                    case 3: echo "Aktueller Platz:";
                    break;
                }?> <?php if(isset($derank) AND ($derank)){echo $derank['rank'];}else{echo " - ";}?></h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Összes pont: ";
                    break;
                    case 2: echo "Total points: ";
                    break;
                    case 3: echo "Gesamtpunkte: ";
                    break;
                }?> <?= $detotalpoints['totalpoints'] ?></h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Megmaradt keret: ";
                    break;
                    case 2: echo "Remaining budget: ";
                    break;
                    case 3: echo "Verbleibendes Budget: ";
                    break;
                }?><?= $decredit ?>M</h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Kedvenc csapat: ";
                    break;
                    case 2: echo "Favorite team: ";
                    break;
                    case 3: echo "Lieblingsverein: ";
                    break;
                }?>
                <select name="de-favorite-team" id="de-favorite-team" style="min-width:120px; background-color:<?= ($decomp['favorite_team_changed'] == 1 ? '#e9ecef' : '#fff') ?>;" <?= ($decomp['favorite_team_changed'] == 1 ? 'disabled' : '') ?>>
                    <option value="0"<?php if(empty($defavteam) || $defavteam==0) echo ' selected'; ?>>
                        <?php
                        switch($_SESSION['lang']){
                            case 1: echo "Válassz csapatot";
                            break;
                            case 2: echo "Select team";
                            break;
                            case 3: echo "Team wählen";
                            break;
                        }
                        ?>
                    </option>
                    <?php
                    while($team = $deteamlist->fetch(PDO::FETCH_ASSOC)){
                        $selected = ($defavteam == $team['team_id']) ? ' selected' : '';
                        echo '<option value="'.$team['team_id'].'"'.$selected.'>'.$team['name'].'</option>';
                    }
                    ?>
                </select>
            </h6>
            
            

            <a class="btn btn-danger float-end" href="delete-team.php?league=de">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Csapat törlése";
                    break;
                    case 2: echo "Delete team";
                    break;
                    case 3: echo "Team löschen";
                    break;
                }?>
            </a>

            <?php }else{ ?>
                <div class="make-team" >
                    <input type="submit" name="makeateam-de" value="<?php switch($_SESSION['lang']){
                    case 1: echo "Új csapat készítése";
                    break;
                    case 2: echo "Make a team";
                    break;
                    case 3: echo "Team erstellen";
                    break;
                }?>">
                </div>
                
            <?php } ?>
        </div>
        <div class="team">
            <h4>🇩🇪 Bundesliga Women</h4>
            <?php 
            if(isset($dewcomp)){
            ?>
            <label for="dew-teamname"><?php switch($_SESSION['lang']){
                    case 1: echo "Csapatnév:";
                    break;
                    case 2: echo "Team name:";
                    break;
                    case 3: echo "Teamname:";
                    break;
                }?></label>
            <input type="text" name="dew-teamname" id="dew-teamname" value="<?= $dewcomp['teamname']?>">
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Helyezés:";
                    break;
                    case 2: echo "Current rank:";
                    break;
                    case 3: echo "Aktueller Platz:";
                    break;
                }?> <?php if(isset($dewrank) AND ($dewrank)){echo $dewrank['rank'];}else{echo " - ";}?></h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Összes pont: ";
                    break;
                    case 2: echo "Total points: ";
                    break;
                    case 3: echo "Gesamtpunkte: ";
                    break;
                }?> <?= $dewtotalpoints['totalpoints'] ?></h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Megmaradt keret: ";
                    break;
                    case 2: echo "Remaining budget: ";
                    break;
                    case 3: echo "Verbleibendes Budget: ";
                    break;
                }?><?= $dewcredit ?>M</h6>
            <h6><?php switch($_SESSION['lang']){
                    case 1: echo "Kedvenc csapat: ";
                    break;
                    case 2: echo "Favorite team: ";
                    break;
                    case 3: echo "Lieblingsverein: ";
                    break;
                }?>
                <select name="dew-favorite-team" id="dew-favorite-team" style="min-width:120px; background-color:<?= ($dewcomp['favorite_team_changed'] == 1 ? '#e9ecef' : '#fff') ?>;" <?= ($dewcomp['favorite_team_changed'] == 1 ? 'disabled' : '') ?>>
                    <option value="0"<?php if(empty($dewfavteam) || $dewfavteam==0) echo ' selected'; ?>>
                        <?php
                        switch($_SESSION['lang']){
                            case 1: echo "Válassz csapatot";
                            break;
                            case 2: echo "Select team";
                            break;
                            case 3: echo "Team wählen";
                            break;
                        }
                        ?>
                    </option>
                    <?php
                    while($team = $dewteamlist->fetch(PDO::FETCH_ASSOC)){
                        $selected = ($dewfavteam == $team['team_id']) ? ' selected' : '';
                        echo '<option value="'.$team['team_id'].'"'.$selected.'>'.$team['name'].'</option>';
                    }
                    ?>
                </select>
            </h6>
            
            

            <a class="btn btn-danger float-end" href="delete-team.php?league=dew">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Csapat törlése";
                    break;
                    case 2: echo "Delete team";
                    break;
                    case 3: echo "Team löschen";
                    break;
                }?>
            </a>

            <?php }else{ ?>
                <div class="make-team" >
                    <input type="submit" name="makeateam-dew" value="<?php switch($_SESSION['lang']){
                    case 1: echo "Új csapat készítése";
                    break;
                    case 2: echo "Make a team";
                    break;
                    case 3: echo "Team erstellen";
                    break;
                }?>">
                </div>
                
            <?php } ?>
        </div>
    </div>

    <div class="myleagues">
        <h5><?php switch($_SESSION['lang']){
                    case 1: echo "Saját ligáim";
                    break;
                    case 2: echo "My Leagues";
                    break;
                    case 3: echo "Meine Ligen";
                    break;
                }?></h5>
        <div class="create-league-btn">
            <a href="newprivateleague.php">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Új privát liga";
                    break;
                    case 2: echo "Create a league";
                    break;
                    case 3: echo "Liga erstellen";
                    break;
                }?>
            </a>
        </div>

        <?php 
        $leaguelist=$crud->findUserInPrivateLeagues($_SESSION['profile_id']);
        if($leaguelist AND !empty($leaguelist)){  ?>
            <div class="leaguelist">
                <table>
                    <tr>
                        <th><?php switch($_SESSION['lang']){
                            case 1: echo "Név";
                            break;
                            case 2: echo "Name";
                            break;
                            case 3: echo "Name";
                            break;
                        }?></th>
                        <th><?php switch($_SESSION['lang']){
                            case 1: echo "Bajnokság";
                            break;
                            case 2: echo "League";
                            break;
                            case 3: echo "Liga";
                            break;
                        }?></th>
                        <th><?php switch($_SESSION['lang']){
                            case 1: echo "Helyezés";
                            break;
                            case 2: echo "Rank";
                            break;
                            case 3: echo "Platz";
                            break;
                        }?></th>
                        <th></th>
                    <?php
                    while($r = $leaguelist->fetch(PDO::FETCH_ASSOC)){
                    ?>
                    <tr>
                        <td>
                            <?php if($r['confirmed']==1){ ?>
                            <a href="privateleague.php?leagueid=<?php echo $r['privateleague_id']?>"><?php echo $r['leaguename']?></a>
                            <?php }else{echo $r['leaguename'];} ?>
                        </td>
                        <td><?php switch($r['league_id']){
                            case 10: echo "🇭🇺 <span>Szuperliga</span>";
                            break;
                            case 20: echo "🇩🇪 <span>Bundesliga</span> Men";
                            break;
                            case 40: echo "🇩🇪 <span>Bundesliga</span> Women";
                            break;
                        }?></td>
                        <td>
                            <?php
                                $rankedPL = $crud->rankMembersofLeague($r['privateleague_id']);
                                $counter = 1;
                                foreach($rankedPL as $ranked){
                                    if($ranked['competitor_id'] == $r['competitor_id']){
                                        echo $counter . ". ";
                                        break;
                                    }
                                    $counter++;
                                }
                            ?>
                        </td>
                        <td>
                            <?php if($r['admin']==$r['profile_id']){
                                switch($_SESSION['lang']){
                                    case 1: echo '<a class="btn-custom btn-edit" href="editprivateleague.php?leagueid='.$r['privateleague_id'].'">Módosít</a>';
                                    break;
                                    case 2: echo '<a class="btn-custom btn-edit" href="editprivateleague.php?leagueid='.$r['privateleague_id'].'">Edit</a>';
                                    break;
                                    case 3: echo '<a class="btn-custom btn-edit" href="editprivateleague.php?leagueid='.$r['privateleague_id'].'">Bearbeiten</a>';
                                    break;
                            }} ?>
                            <?php if($r['confirmed']==0){ ?>
                                <button class="btn-custom btn-waiting" disabled><i class="bi bi-hourglass-split"></i></button>
                            <?php }elseif($r['admin']!==$r['profile_id']){ ?>
                                <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
                                    <input type="submit" class="btn-check" name="exitprleague" value="<?php echo $r['privateleague_id']."_".$r['competitor_id']?>" id="exitprleague" onchange="this.form.submit()" onclick="return confirm('Exit <?php echo $r['leaguename']?>?');" > 
                                    <label class="btn-custom btn-exit" for="exitprleague">Exit<i class="bi bi-box-arrow-right"></i></label>
                                </form>
                            <?php }?>
                        </td>
                    </tr>
                    <?php }?>
                </table>
            </div>
        <?php }?>
    </div>

    <div class="myleagues">
        <h5><?php switch($_SESSION['lang']){
                    case 1: echo "Szurkolói ligák";
                    break;
                    case 2: echo "Fan Leagues";
                    break;
                    case 3: echo "Fan Ligen";
                    break;
                }?></h5>

        <?php 
        $leaguelist=$crud->findUserInFanLeagues($_SESSION['profile_id']);
        if($leaguelist AND !empty($leaguelist)){  ?>
            <div class="leaguelist">
                <table>
                    <tr>
                        <th><?php switch($_SESSION['lang']){
                            case 1: echo "Név";
                            break;
                            case 2: echo "Name";
                            break;
                            case 3: echo "Name";
                            break;
                        }?></th>
                        <th><?php switch($_SESSION['lang']){
                            case 1: echo "Bajnokság";
                            break;
                            case 2: echo "League";
                            break;
                            case 3: echo "Liga";
                            break;
                        }?></th>
                        <th><?php switch($_SESSION['lang']){
                            case 1: echo "Helyezés";
                            break;
                            case 2: echo "Rank";
                            break;
                            case 3: echo "Platz";
                            break;
                        }?></th>
                    <?php
                    while($r = $leaguelist->fetch(PDO::FETCH_ASSOC)){
                    ?>
                    <tr>
                        <td>
                            <?php if($r['confirmed']==1){ ?>
                            <a href="fanleague.php?leagueid=<?php echo $r['privateleague_id']?>"><?php echo $r['leaguename']?></a>
                            <?php }else{echo $r['leaguename'];} ?>
                        </td>
                        <td><?php switch($r['league_id']){
                            case 10: echo "🇭🇺 <span>Szuperliga</span>";
                            break;
                            case 20: echo "🇩🇪 <span>Bundesliga </span> Men";
                            break;
                            case 40: echo "🇩🇪 <span>Bundesliga </span> Women";
                            break;
                        }?></td>
                        <td>
                            <?php
                                $rankedPL = $crud->rankMembersofLeague($r['privateleague_id']);
                                $counter = 1;
                                foreach($rankedPL as $ranked){
                                    if($ranked['competitor_id'] == $r['competitor_id']){
                                        echo $counter . ". ";
                                        break;
                                    }
                                    $counter++;
                                }
                            ?>
                        </td>
                    </tr>
                    <?php }?>
                </table>
            </div>
        <?php }?>
    </div>
    
    <div class="save">
        <input type="submit" name="submit" id="submit" value="<?php switch($_SESSION['lang']){
                    case 1: echo "Mentés";
                    break;
                    case 2: echo "Save changes";
                    break;
                    case 3: echo "Änderungen speichern";
                    break;
                }?>">    
    </div>
    <div class="profile-delete-section">
        <a class="deleteprofile" href="delete-profile.php">
            <?php switch($_SESSION['lang']){
                case 1: echo "Profil törlése";
                break;
                case 2: echo "Delete profile";
                break;
                case 3: echo "Profil löschen";
                break;
            }?>
        </a>
    </div>
    
    

</form>


<script src="profilescript.js"></script>

<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>