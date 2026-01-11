<?php 
$title = "Profil törlése";
require_once 'db/conn.php';
require_once 'includes/header.php';


if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['delete'])){
    $deleteranking=$crud->deleteTeamranking($_POST['competitor_id']);
    $deleteresult=$crud->deleteTeamresult($_POST['competitor_id']);
    $deletetransfers=$crud->deleteTeamtransfers($_POST['competitor_id']);
    $deletetroster=$crud->deleteTeamroster($_POST['competitor_id']);
    $deletecompetitor=$crud->deleteCompetitor($_POST['competitor_id']);
    echo '<script type="text/javascript">location.href="profile.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=profile.php" /></noscript> ';
}

switch($_GET['league']){
    case "hu": $league_id=10;
    break;
    case "de": $league_id=20;
    break;
    case "dew": $league_id=40;
    break;
    default: echo '<script type="text/javascript">location.href="profile.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=profile.php" /></noscript> ';
    break;
}

$comp=$crud->getCompetitorID($_SESSION['profile_id'],$league_id);
echo $comp['competitor_id'];



?>

<h2 style="text-align:center; color:red">
    <?php switch($_SESSION['lang']){
        case 1: echo "Csapat törlése";
        break;
        case 2: echo "Delete team";
        break;
        case 3: echo "Team löschen";
        break;
    }?> 
</h2>

<div class="container" style="max-width:400px">
    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
        <input type="hidden" name="profile_id" value="<?php echo $_SESSION['profile_id'] ?>"></input> <!--we needed to put this here so that we would have the ID on the editpost page and can work with it in the database -->
        <input type="hidden" name="competitor_id" value="<?php echo $comp['competitor_id'] ?>"></input> <!--we needed to put this here so that we would have the ID on the editpost page and can work with it in the database -->
        <div>
            <?php switch($_SESSION['lang']){
                case 1:
                ?>
                    <h6>Biztos vagy benne, hogy törlöd a csapatodat?</h6>
                    <p>A törölt adatokat később nem tudjuk visszaállítani. A megszerzett pontok és a jelenlegi helyezésed is elveszik.</p>
                <?php ;
                break;

                case 2:
                ?>
                    <h6>Are you sure you want to delete your team?</h6>
                    <p>The deleted data cannot be retrieved later. Your total points and current rank will be lost.</p>
                    <?php ;
                break;

                case 3:
                ?>
                    <h6>Bist du sicher, dass du dein Team löschen möchtest?</h6>
                    <p>Die gelöschten Daten können später nicht wiederhergestellt werden. Deine Gesamtpunkte und dein aktueller Rang gehen verloren.</p>
                    <?php ;
                break;
            }?>

        </div>
        <div class="row">    
        <div class="col">
                <button type="submit" class="btn btn-danger" name="delete">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Kukába velük!";
                        break;
                        case 2: echo "Delete them!";
                        break;
                        case 3: echo "Lösche sie!";
                        break;
                    }?>
                </button> 
            </div>
            
            </form>
            <div class="col">
                <a href="profile.php" class="btn btn-success">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Rendben, megtartom őket";
                        break;
                        case 2: echo "Alright, I keep them for now";
                        break;
                        case 3: echo "OK, ich behalte sie vorerst";
                        break;
                    }?>
                </a>
            </div>
        </div>    
        

</div>