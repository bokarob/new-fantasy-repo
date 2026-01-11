<?php 
$title = "Profil";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

$playercheck=$webuser->getUserbyID($_SESSION['profile_id']);
$email=$playercheck['email'];
$alias=$playercheck['alias'];
$picture=$crud->getPicture($playercheck['picture_id']);
$language=$crud->getCurrentLanguage($playercheck['lang_id']);
$langlist=$crud->getLanguageList();

if(isset($_GET['notype'])){
    $notificationlist=$crud->getLeagueDeletionNotificationsForUser($_SESSION['profile_id'],$_SESSION['lang']);
    $notifications=$notificationlist->fetchAll();
    foreach ($notifications as $n) {
        $markread=$crud->markNotificationAsRead($n['notification_id']);
    }
}

//privát liga kilépés
if(isset($_POST['exitprleague'])){
    $exitpostpart = explode('_',$_POST['exitprleague']);
    $removefromleague=$crud->removePLmember($exitpostpart[0],$exitpostpart[1]);
}

?>

<style>
    .myleagues_main {
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

    .myleagues_main h2 {
        text-align: center;
        font-size: 28px;
        font-weight: bold;
        color: #2b7a78;
        margin-bottom: 20px;
    }

    .create-league-btn {
        display: block;
        text-align: center;
        margin: 2.5rem 0;
    }

    .create-league-btn a {
        font-size: 18px;
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
        background-color: #f1f1f182;
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

    .leaguelist table td a:hover {
        text-decoration: underline;
    }

    
    .btn-custom {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        margin-left: 10px;
        display: inline-flex;
        width: 73px;
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

    .alleligendiv {
        max-width: 700px;
        margin: auto;
        margin-top: 20px;
        text-align: center;
    }

    #alleligen {
        width: 100%;
        padding: 15px;
        background-color: #118b9f;
        color: white;
        border-radius: 8px;
        text-decoration: none;
        display: block;
        font-size: 18px;
        font-weight: bold;
        transition: background-color 0.3s ease;
    }

    #alleligen:hover {
        background-color: #3aafa9;
    }

    table td:nth-of-type(2) {
        display: inline-flex;
    }
    .leaguelist td span{
        margin-left: 5px;
    }

    @media (max-width: 700px) {
        .btn-custom {
            padding: 4px 12px;
            font-size: 12px;
            margin-left: 5px;
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


</style>

<?php 
    if(!empty($notifications)){
    ?>
      <div class="notifications">
        <?php
        foreach ($notifications as $noti) {
          ?>
            <div class='noti-item'>
              <img src='img/notification-bell.svg' alt='' class='noti-image'>
              <div class='noti-text'>
                <p><?php echo $noti['text'] . ': ' . $noti['leaguename']; ?></p>
              </div>
            </div>
        <?php }?>
      </div>
    <?php }?>

<div class="myleagues_main">
    <h2><?php switch($_SESSION['lang']){
                case 1: echo "Saját ligáim";
                break;
                case 2: echo "My Leagues";
                break;
                case 3: echo "Meine Ligen";
                break;
            }?>
    </h2>
    
    <div class="create-league-btn">
        <a href="newprivateleague.php"><?php switch($_SESSION['lang']){
                case 1: echo "Új privát liga";
                break;
                case 2: echo "Create a league";
                break;
                case 3: echo "Liga erstellen";
                break;
            }?></a>
    </div>

    <?php 
    $leaguelist=$crud->findUserInPrivateLeagues($_SESSION['profile_id']);
    if($leaguelist AND !empty($leaguelist)){  ?>
        <div class="leaguelist">
            <table>
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
                        case 20: echo "🇩🇪 <span>Bundesliga Men</span>";
                        break;
                        case 40: echo "🇩🇪 <span>Bundesliga Women</span>";
                        break;
                    }?></td>
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
<div class="alleligendiv">
    <a class="btn btn-muted" id="alleligen" href="private_leagues.php">
        <?php switch($_SESSION['lang']){
            case 1: echo "Összes privát liga";
            break;
            case 2: echo "All private Leagues";
            break;
            case 3: echo "Alle privaten Ligen";
            break;
        }?>
    </a>  
</div>





<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>