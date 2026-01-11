<?php 
$title = "Leagues";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if(isset($_GET['notype'])){
    $notificationlist=$crud->getLeagueApplicationNotificationsForUser($_SESSION['profile_id'],$_SESSION['lang']);
    $notifications=$notificationlist->fetchAll();
    foreach ($notifications as $n) {
        $markread=$crud->markNotificationAsRead($n['notification_id']);
        echo '<script type="text/javascript">location.href="editprivateleague.php?leagueid='.$n['privateleague_id'].'";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=editprivateleague.php?leagueid='.$n['privateleague_id'].'" /></noscript> ';
        break;
    }
}

if(!isset($_GET['leagueid']) AND !isset($_POST['privateleague_id'])){
    echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
}

//liga név változtatás
if(isset($_POST['submit'])){
    $editleague=$crud->editPrivateLeague($_POST['privateleague_id'],trim($_POST['leaguename']));
    echo '<script type="text/javascript">location.href="privateleague.php?leagueid='.$_POST['privateleague_id'].'";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=privateleague.php?leagueid='.$_POST['privateleague_id'].'" /></noscript> ';
}

//új tag felvétele
if(isset($_POST['invite'])){
    $newmember=$crud->newPLmemberbyAdmin($_POST['privateleague_id'],$_POST['invite']);
    if($newmember){
        $comp=$crud->getCompetitor($_POST['invite']);
        $membernoti=$crud->newPictureNotification('D1',$comp['profile_id'],1,$_POST['privateleague_id']);
    }
}

//tag törlése
if(isset($_POST['remove'])){
    $removemember=$crud->removePLmember($_POST['privateleague_id'],$_POST['remove']);
    if($removemember){
        $comp=$crud->getCompetitor($_POST['remove']);
        $membernoti=$crud->newPictureNotification('D5',$comp['profile_id'],1,$_POST['privateleague_id']);
    }
}

//jelentkezők jóváhagyása és elutasítása
if(isset($_POST['confirm'])){
    $confirmmember=$crud->confirmPLmember($_POST['privateleague_id'],$_POST['confirm']);
    if($confirmmember){
        $comp=$crud->getCompetitor($_POST['confirm']);
        $membernoti=$crud->newPictureNotification('D3',$comp['profile_id'],1,$_POST['privateleague_id']);
    }
}

if(isset($_POST['decline'])){
    $removemember=$crud->removePLmember($_POST['privateleague_id'],$_POST['decline']);
    if($removemember){
        $comp=$crud->getCompetitor($_POST['decline']);
        $membernoti=$crud->newPictureNotification('D4',$comp['profile_id'],1,$_POST['privateleague_id']);
    }
}

//liga törlése
if(isset($_POST['deleteleague'])){
    $deletemembers=$crud->deletePLmembersFromLeague($_POST['deleteleague']);
    if($deletemembers){
        $deleteleague=$crud->deletePrivateLeague($_POST['deleteleague']);
        if($deleteleague){
            echo '<script type="text/javascript">location.href="myleagues.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=myleagues.php" /></noscript> ';
        }
    }
}

//adatokat összeszedni a ligáról. Ez az utolsó lépés
if(isset($_POST['privateleague_id'])){
    $pr_league=$crud->getPLbyID($_POST['privateleague_id']);
}elseif(!empty($_GET['leagueid'])){
    $pr_league=$crud->getPLbyID($_GET['leagueid']);
}

//ha nem te vagy az admin, bye bye
if($_SESSION['profile_id']!==$pr_league['admin']){
    $compcheck=$crud->getCompetitorID($_SESSION['profile_id'],$pr_league['league_id']);
    $membercheck=$crud->checkMembership($pr_league['privateleague_id'],$compcheck['competitor_id']);
    if($membercheck['count']==1){
        echo '<script type="text/javascript">location.href="privateleague.php?leagueid='.$pr_league['privateleague_id'].'";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=privateleague.php?leagueid='.$pr_league['privateleague_id'].'" /></noscript> ';
    }else{
        echo '<script type="text/javascript">location.href="myleagues.php";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=myleagues.php" /></noscript> ';
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

    table{
        max-width: 700px;
        margin: auto;
        font-size: 12px;
    }
    table tr:hover{
        background-color: #f0f1f3;
    }
    table th:nth-of-type(1),table td:nth-of-type(1) {
        width: 200px;
        word-break: break-all;
    }
    table th:nth-of-type(2),table td:nth-of-type(2) {
        width: 200px;
    }
    table th{
        font-weight: bold;
        font-size: 13px;
    }

    .removebutton, .confirmbutton{
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        margin-left: 10px;
        background-color: #ff6b6b;
        color: white;
    }

    .confirmbutton{
        background-color: #00d6b7;
    }

    .addbutton{
        padding: 2px 12px;
        border-radius: 6px;
        margin-left: 10px;
        background-color: #17ade8;
        color: white;
        padding-left: 14px;
    }

    .deletediv{
        width: fit-content;
        margin: auto;
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
        margin-top: 2rem;
        gap: 1rem;
    }

    .deleteleague{
        width: 100px;
        font-size: 12px;
    }
    
    .back-button {
        font-size: 12px;
        display: inline-block;
        background-color: #0B81FF;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        transition: background-color 0.3s;
    }

    @media (max-width:500px){
        table th:nth-of-type(1),table td:nth-of-type(1) {
            width: 170px;
        }
        table th:nth-of-type(2),table td:nth-of-type(2) {
            width: 130px;
        }

        .applicantlist .removebutton, .applicantlist .confirmbutton{
            padding: 2px 12px;
            margin-left: 4px;
        }


    }
</style>


<div class="regbox">
    <div class="header">
        <h1 class="text-center">
            <?php switch($_SESSION['lang']){
                case 1: echo "Saját liga módosítása";
                break;
                case 2: echo "Edit private league";
                break;
                case 3: echo "Private Liga bearbeiten";
                break;
            }?> 
        </h1>
    </div>
    

    <form name="registration" class="reg-form" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
        <input type="text" value="<?php echo $pr_league['privateleague_id'] ?>" name="privateleague_id" style="display:none">
        <div class="flex-row">
            <label for="leaguename" class="lf--label">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Név";
                    break;
                    case 2: echo "Name";
                    break;
                    case 3: echo "Name";
                    break;
                }?> 
            </label>
            <input required type="text" class="lf--input" id="leaguename" name="leaguename" value="<?php echo $pr_league['leaguename'] ?>">
        </div>
        <div class="flex-row">
            <label for="league" class="lf--label">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Liga";
                    break;
                    case 2: echo "League";
                    break;
                    case 3: echo "Liga";
                    break;
                }?>
            </label>
            <select disabled type="text" class="form-select" id="league" name="league">
                    <option selected value="<?php echo $pr_league['league_id']?>"><?php switch($pr_league['league_id']){
                    case 10: echo "🇭🇺 Szuperliga";
                    break;
                    case 20: echo "🇩🇪 Bundesliga Men";
                    break;
                    case 40: echo "🇩🇪 Bundesliga Women";
                    break;
                }?> </option>
            </select>
        </div>
        <?php switch($_SESSION['lang']){
            case 1:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Mentés">
            <?php ;
            break;

            case 2:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Save changes">
            <?php ;
            break;

            case 3:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Änderungen speichern">
            <?php ;
            break;

        }?>
        <div class="deletediv">
            <input type="submit" class="btn-check" name="deleteleague" value="<?php echo $pr_league['privateleague_id']?>" id="deleteleague" onchange="this.form.submit()" onclick="return confirm('Delete <?php echo $pr_league['leaguename']?>?');" > 
            <label class="btn btn-danger deleteleague" for="deleteleague"><?php switch($_SESSION['lang']){
                    case 1: echo "Liga törlése";
                    break;
                    case 2: echo "Delete league";
                    break;
                    case 3: echo "Liga löschen";
                    break;
                }?>
            </label>
            <a href="myleagues.php" class="btn back-button">&#11178 
                <?php switch($_SESSION['lang']){
                    case 1: echo "Vissza";
                    break;
                    case 2: echo "Back";
                    break;
                    case 3: echo "Zurück";
                    break;
                }?>
            </a>
        </div>
        

        <?php
        $reqlist=$crud->listApplicantsofLeague($pr_league['privateleague_id']);
        if($reqlist->rowCount() > 0){
        ?>
        <h4>
            <?php switch($_SESSION['lang']){
                case 1: echo "Jelentkezések";
                break;
                case 2: echo "Applicants";
                break;
                case 3: echo "Bewerber";
                break;
            }?>
        </h4>
        <table class="applicantlist">
            <tr>
                <th><?php switch($_SESSION['lang']){
                    case 1: echo "Csapatnév";
                    break;
                    case 2: echo "Team name";
                    break;
                    case 3: echo "Teamname";
                    break;
                    }?>
                </th>
                <th><?php switch($_SESSION['lang']){
                    case 1: echo "Fantasy edző";
                    break;
                    case 2: echo "Fantasy trainer";
                    break;
                    case 3: echo "Fantasy Trainer";
                    break;
                    }?>
                </th>
                <th></th>
            </tr>
            <?php 
            foreach ($reqlist as $t) {
                if($t['profile_id']!==$pr_league['admin']){
                ?>
                <tr>
                    <td><?php echo $t['teamname']?></td>
                    <td><?php $username=$webuser->getUserbyID($t['profile_id']); echo $username['alias']?></td>
                    <td>
                        <button type="submit" name="confirm" class="confirmbutton" value="<?php echo $t['competitor_id'];?>">
                            <i class="bi bi-person-check-fill"></i>
                        </button>
                    </td>
                    <td>
                        <button type="submit" name="decline" class="removebutton" value="<?php echo $t['competitor_id'];?>">
                            <i class="bi bi-person-x-fill"></i>
                        </button>
                    </td>
                </tr>
            <?php }}} ?>
        </table>

        <?php 
        $complist=$crud->listMembersofLeague($pr_league['privateleague_id']);
        if($complist->rowCount() > 1){
        ?>
        <hr>
        <h4>Members</h4>
        <table class="memberlist">
            <tr>
                <th><?php switch($_SESSION['lang']){
                    case 1: echo "Csapatnév";
                    break;
                    case 2: echo "Team name";
                    break;
                    case 3: echo "Teamname";
                    break;
                    }?>
                </th>
                <th><?php switch($_SESSION['lang']){
                    case 1: echo "Fantasy edző";
                    break;
                    case 2: echo "Fantasy trainer";
                    break;
                    case 3: echo "Fantasy Trainer";
                    break;
                    }?>
                </th>
                <th></th>
            </tr>
            <?php 
            foreach ($complist as $r) {
                if($r['profile_id']!==$pr_league['admin']){
                ?>
                <tr>
                    <td><?php echo $r['teamname']?></td>
                    <td><?php $username=$webuser->getUserbyID($r['profile_id']); echo $username['alias']?></td>
                    <td>
                        <button type="submit" name="remove" class="removebutton" value="<?php echo $r['competitor_id'];?>">
                            <i class="bi bi-person-fill-slash"></i>
                        </button>
                    </td>
                </tr>
            <?php }}} ?>
        </table>
        <hr>
        <h4>All teams</h4>
        <table class="competitorlist">
            <tr>
                <th><?php switch($_SESSION['lang']){
                    case 1: echo "Csapatnév";
                    break;
                    case 2: echo "Team name";
                    break;
                    case 3: echo "Teamname";
                    break;
                    }?>
                </th>
                <th><?php switch($_SESSION['lang']){
                    case 1: echo "Fantasy edző";
                    break;
                    case 2: echo "Fantasy trainer";
                    break;
                    case 3: echo "Fantasy Trainer";
                    break;
                    }?>
                </th>
                <th></th>
            </tr>
            <?php 
            $complist=$crud->listCompetitorsForLeagues($pr_league['league_id']);
            foreach ($complist as $r) { 
                $check=$crud->checkMembership($pr_league['privateleague_id'],$r['competitor_id']);
                if($check['count']==0){
                ?>
                <tr>
                    <td><?php echo $r['teamname']?></td>
                    <td><?php echo $r['alias']?></td>
                    <td>
                        <button type="submit" name="invite" class="addbutton" value="<?php echo $r['competitor_id'];?>">
                            <i class="bi bi-person-plus-fill"></i>
                        </button>
                    </td>
                </tr>
            <?php }} ?>
        </table>
    </form>
</div>
<script src="scroll.js"></script>

<br>
<br>
<br>
<br>
<?php   require_once 'includes/footer_new2.php';
    