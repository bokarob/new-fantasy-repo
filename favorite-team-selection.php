<?php 
$title = "KedvencCsapat";
require_once 'includes/header.php';
require_once 'db/conn.php';


//csak olyan oldalról jutunk el ide, ahol már választottunk ligát
if(!isset($_SESSION['league'])){
    echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
}
//ha nincs bejelentkezve a felhasználó, akkor irány a főoldal
$gameweek = $crud->getGameweek($_SESSION['league']);
$week = $gameweek['gameweek'];
if(!isset($_SESSION['profile_id']) OR $week>=18) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
//ha már van kedvenc csapata, akkor nyomás a profil  oldalra
$competitor=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
if($competitor['favorite_team_id'] > 0){
    echo '<script type="text/javascript">location.href="profile.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=profile.php" /></noscript> ';
}


$teamlist=$crud->getTeamsinLeague($_SESSION['league']);

if(isset($_POST['favorite_team']) && isset($_SESSION['competitor_id'])){
    $team_id = $_POST['favorite_team'];
    
    $updateTeam = $crud->updateFavoriteTeam($_SESSION['competitor_id'], $team_id);
    if($updateTeam){
        $fanleague = $crud->newPLmemberbyAdmin($team_id,$_SESSION['competitor_id']);
        if($fanleague){
            // Redirect to myteam page after successful update
            echo '<script type="text/javascript">location.href="myteam.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=myteam.php" /></noscript> ';
        }
        
    } else {
        echo '<div class="alert alert-danger">Hiba történt a csapat kiválasztásakor. Kérjük, próbáld újra.</div>';
    }
}


?>
<style>
.fav-team-selection {
    align-items: center;
    padding: 2rem;
    border-radius: 8px;
    max-width: 600px;
    margin: auto;
}
.team-radio-group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
    margin-top: 2rem;
    align-items: center;
}
.team-radio-label {
    display: flex;
    align-items: center;
    border: 2px solid #35c3c1;
    border-radius: 8px;
    padding: 0.7rem 1.2rem;
    background: #f9f9f9;
    width: 100%;
    max-width: 400px;
    min-width: 220px;
    min-height: 60px;
    font-size: 1.1rem;
    transition: background 0.2s, border-color 0.2s;
    cursor: default;
    box-sizing: border-box;
    caret-color: transparent
}
.team-radio-label input[type="radio"] {
    display: none;
}
.team-radio-label.selected {
    background: #e0f7fa;
    border-color: #0097a7;
    caret-color: transparent
}
.team-radio-label img {
    height: 32px;
    margin-right: 1rem;
}
@media (max-width: 700px) {
    .team-radio-group {
        gap: 1rem;
    }
    .team-radio-label {
        max-width: none;
        min-width: 0;
        font-size: 1rem;
    }
}
</style>

<form method="post" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>">
    <div class="fav-team-selection">
        <h2 class="text-center" style="margin-bottom: 1rem;">
            <?php switch($_SESSION['lang']){
                case 1: echo "Válaszd ki a kedvenc csapatod"; break;
                case 2: echo "Select your favorite team"; break;
                case 3: echo "Wähle dein Lieblingsteam"; break;
            }?></h2>
        <span>
            <?php switch($_SESSION['lang']){
                case 1: echo "A választásod alapján hozzáadunk a kedvenc csapatod szurkolói ligájához. Nem kötelező választani. Később a profil oldalon meg tudod változtatni a kedvenc csapatodat (egy alkalommal)"; break;
                case 2: echo "Based on your choice, we will add you to the fan league of your favorite team. It is not mandatory to choose. You can change your favorite team later on the profile page (once)"; break;
                case 3: echo "Basierend auf deiner Wahl fügen wir dich der Fanliga deines Lieblingsteams hinzu. Es ist nicht verpflichtend zu wählen. Du kannst dein Lieblingsteam später auf der Profilseite ändern (einmalig)"; break;
            }?>
        </span>
    
        <div class="team-radio-group">
            <?php while($team = $teamlist->fetch(PDO::FETCH_ASSOC)): ?>
                <label class="team-radio-label" id="team-label-<?php echo $team['team_id']; ?>">
                    <input type="radio" name="favorite_team" value="<?php echo $team['team_id']; ?>" required>
                    <img src="img/teamlogo/<?php echo $team['logo']; ?>" alt="<?php echo $team['name']; ?>">
                    <span><?php echo $team['name']; ?></span>
                </label>
            <?php endwhile; ?>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: center; margin-bottom:2rem;">
            <button type="submit" class="btn btn-info" style="flex:1; max-width:200px; height:48px;">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Kiválasztom"; break;
                    case 2: echo "Select"; break;
                    case 3: echo "Auswählen"; break;
                }?>
            </button>
            <button type="button" class="btn btn-secondary" style="flex:1; max-width:200px; height:48px;"
                onclick="window.location.href='myteam.php'">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Nem választok"; break;
                    case 2: echo "No favorite"; break;
                    case 3: echo "Kein Favorit"; break;
                }?>
            </button>
        </div>
    </div>
</form>

<script>
// Highlight selected team button
document.querySelectorAll('.team-radio-label input[type="radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.team-radio-label').forEach(function(label) {
            label.classList.remove('selected');
        });
        if(radio.checked) {
            radio.parentElement.classList.add('selected');
        }
    });
});
</script>

