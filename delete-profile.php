<?php 
$title = "Profil törlése";
require_once 'db/conn.php';
require_once 'includes/header.php';


if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['delete'])){
    $competitors=$crud->getAllCompetitorForProfile($_POST['profile_id']);
    while($r = $competitors->fetch(PDO::FETCH_ASSOC)){
        $deleteranking=$crud->deleteTeamranking($r['competitor_id']);
        $deleteresult=$crud->deleteTeamresult($r['competitor_id']);
        $deletetransfers=$crud->deleteTeamtransfers($r['competitor_id']);
        $deletetroster=$crud->deleteTeamroster($r['competitor_id']);
        $deletecompetitor=$crud->deleteCompetitor($r['competitor_id']);
    }
    
    $deleteprofile=$crud->deleteProfile($_POST['profile_id']);

    session_destroy();
    echo '<script type="text/javascript">location.href="logout.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=logout.php" /></noscript> ';
}

?>

<style>
    .deleteprofilediv{
        position: relative;
        padding: 20px;
        border: 10px solid transparent; /* Transparent border for space */
        border-image: repeating-linear-gradient(
            45deg,
            yellow 0%,
            yellow 5%,
            black 5%,
            black 10%
        ) 20;
        background-clip: padding-box;
        max-width: fit-content;
        margin: auto;
        margin-top: 2rem;
    }
</style>

<div class="deleteprofilediv">
    <h2 style="text-align:center; color:red">
        <?php switch($_SESSION['lang']){
            case 1: echo "Profil törlése";
            break;
            case 2: echo "Delete profile";
            break;
            case 3: echo "Profil löschen";
            break;
        }?> 
    </h2>

    <div class="container" style="max-width:400px">
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
            <input type="hidden" name="profile_id" value="<?php echo $_SESSION['profile_id'] ?>"></input> <!--we needed to put this here so that we would have the ID on the editpost page and can work with it in the database -->
            <div>
                <?php switch($_SESSION['lang']){
                    case 1:
                    ?>
                        <h6>Biztos vagy benne, hogy törlöd a profilodat?</h6>
                        <p>A törölt adatokat később nem tudjuk visszaállítani. Még most szólunk. Nem lenne jobb egyet aludni rá?</p>
                    <?php ;
                    break;

                    case 2:
                    ?>
                        <h6>Are you sure you want to delete your profile?</h6>
                        <p>The deleted data cannot be retrieved later. Just saying. Wouldn't it be better to sleep on it first?</p>
                        <?php ;
                    break;
                    
                    case 3:
                    ?>
                        <h6>Bist du sicher, dass du dein Profil löschen möchtest?</h6>
                        <p>Die gelöschten Daten können später nicht wiederhergestellt werden. Wäre es nicht besser, zuerst darüber zu schlafen?</p>
                        <?php ;
                    break;
                }?>

            </div>
            <div class="row">    
            <div class="col">
                <button type="submit" class="btn btn-danger" name="delete">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Én innen lelépek";
                        break;
                        case 2: echo "I'm outta here!";
                        break;
                        case 3: echo "Ich bin dann mal weg!";
                        break;
                    }?>
                </button> 
            </div>
            
        </form>
        <div class="col">
            <a href="profile.php" class="btn btn-success">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Na jó, maradok még";
                    break;
                    case 2: echo "Alright, I stay for now";
                    break;
                    case 3: echo "OK, ich bleibe vorerst";
                    break;
                }?>
            </a>
        </div>
    </div>    
</div>