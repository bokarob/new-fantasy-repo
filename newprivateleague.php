<?php 
$title = "Leagues";
require_once 'includes/header.php';
require_once 'db/conn.php';

$saved=false;

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if(isset($_POST['privateleague_id'])){
    $pr_league=$crud->getPLbyID($_POST['privateleague_id']);
    $saved=true;
}

if(isset($_POST['invite'])){
    $newmember=$crud->newPLmemberbyAdmin($_POST['privateleague_id'],$_POST['invite']);
    if($newmember){
        $comp=$crud->getCompetitor($_POST['invite']);
        $membernoti=$crud->newPictureNotification('D1',$comp['profile_id'],1,$_POST['privateleague_id']);
    }
}elseif(isset($_POST['remove'])){
    $removemember=$crud->removePLmember($_POST['privateleague_id'],$_POST['remove']);
    if($removemember){
        $comp=$crud->getCompetitor($_POST['remove']);
        $membernoti=$crud->newPictureNotification('D5',$comp['profile_id'],1,$_POST['privateleague_id']);
    }
}elseif($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['leaguename']) AND isset($_POST['league'])){
    $competitor=$crud->getCompetitorID($_SESSION['profile_id'],$_POST['league']);
    if(!$competitor OR empty($competitor['competitor_id'])){
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-primary text-center">Ebben a ligában még nincs csapatod, ezért saját ligát sem tudsz létrehozni.</div>';
            break;
            case 2: echo '<div class="alert alert-primary text-center">Cannot create private league because you do not have a team in this league yet.</div>';
            break;
            case 3: echo '<div class="alert alert-primary text-center">Du kannst keine private Liga erstellen, da du noch kein Team in dieser Liga hast.</div>';
            break;
        }
    }else{
        $newleague=$crud->newPrivateLeague(trim($_POST['leaguename']),$_POST['league'],$_SESSION['profile_id']);
        if($newleague){
            $createdleague=$crud->getPLbyName(trim($_POST['leaguename']),$_POST['league'],$_SESSION['profile_id']);
            $newmember=$crud->newPLmemberbyAdmin($createdleague['privateleague_id'],$competitor['competitor_id']);
            if($newmember){
                $saved=true;
                $pr_league=$crud->getPLbyID($createdleague['privateleague_id']);
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
        margin-bottom: 1rem;
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
        background-color: #52B918;
        color: white;
        text-decoration: none;
        border-radius: 6px;
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
    <h1 class="text-center">
        <?php switch($_SESSION['lang']){
            case 1: echo "Saját liga készítése";
            break;
            case 2: echo "Create a private league";
            break;
            case 3: echo "Private Liga erstellen";
            break;
        }?> 
    </h1>

    <form name="registration" class="reg-form" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
        <?php if($saved){ ?>
        <input type="text" value="<?php if(isset($pr_league['privateleague_id']))echo $pr_league['privateleague_id'] ?>" name="privateleague_id" style="display:none">
        <?php } ?>
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
            <input required type="text" class="lf--input" id="leaguename" name="leaguename" <?php if(isset($_POST['leaguename'])) echo 'value="'.$_POST['leaguename'].'" disabled'; if(isset($pr_league['leaguename']))echo 'value="'.$pr_league['leaguename'].'" disabled' ?>>
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
            <select required type="text" class="form-select" id="league" name="league">
                <?php if(isset($_POST['leaguename'])) { ?>
                    <option selected value="<?php echo $_POST['league']?>"><?php switch($_POST['league']){
                    case 10: echo "🇭🇺 Szuperliga";
                    break;
                    case 20: echo "🇩🇪 Bundesliga Men";
                    break;
                    case 40: echo "🇩🇪 Bundesliga Women";
                    break;
                }?> </option>
                    <?php }elseif(isset($pr_league['league_id'])) { ?>
                        <option selected value="<?php echo $pr_league['league_id']?>"><?php switch($pr_league['league_id']){
                            case 10: echo "🇭🇺 Szuperliga";
                            break;
                            case 20: echo "🇩🇪 Bundesliga Men";
                            break;
                            case 40: echo "🇩🇪 Bundesliga Women";
                            break;
                        }?> </option>
                    <?php }else{?>
                <option disabled selected value> </option>
                <option value=10 >🇭🇺 Szuperliga</option>
                <option value=20 >🇩🇪 Bundesliga Men</option>
                <option value=40 >🇩🇪 Bundesliga Women</option>
                <?php } ?>
            </select>
        </div>
        <?php if(!$saved){
            switch($_SESSION['lang']){
            case 1:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Csapatok hozzáadása">
            <?php ;
            break;

            case 2:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Add teams">
            <?php ;
            break;

            case 3:
            ?>
            <input type="submit" class="lf--submit" name="submit" value="Teams hinzufügen">
            <?php ;
            break;

            } ?>
            
        <?php }?>
    <?php 
    if($saved){
    ?>
        <div class="deletediv">
            <a href="privateleague.php?leagueid=<?=$pr_league['privateleague_id']?>" class="btn back-button"> 
                <?php switch($_SESSION['lang']){
                    case 1: echo "Kész";
                    break;
                    case 2: echo "Ready";
                    break;
                    case 3: echo "Fertig";
                    break;
                }?>
            </a>
        </div>

        <?php 
        $complist=$crud->listMembersofLeague($pr_league['privateleague_id']);
        if($complist->rowCount() > 1){
        ?>
        <hr>
        <h4>
            <?php switch($_SESSION['lang']){
                case 1: echo "Tagok";
                break;
                case 2: echo "Members";
                break;
                case 3: echo "Mitglieder";
                break;
            }?>
        </h4>
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
        <h4>
            <?php switch($_SESSION['lang']){
                case 1: echo "Csapatok";
                break;
                case 2: echo "All teams";
                break;
                case 3: echo "Alle Teams";
                break;
            }?>
        </h4>
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
    
    <?php } ?>
</form>


<script src="scroll.js"></script>

<br>
<br>
<br>
<br>
<?php   require_once 'includes/footer_new2.php';