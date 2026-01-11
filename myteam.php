<?php 
$title = "Csapatom";
require_once 'includes/header.php';
require_once 'db/conn.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['league'])){
    switch ($_POST['league']) {
        case '10':
            $_SESSION['league']=10;
            break;    
        case '20':
            $_SESSION['league']=20;
            break;
        case '40':
            $_SESSION['league']=40;
            break;
        
    }
  }

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
  <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

//ha választottunk ligát, akkor betöltjük az egész oldalt. Az oldal végén van else -> akkor ligaválasztó gombok
if(isset($_SESSION['league'])){

$gameweek = $crud->getGameweek($_SESSION['league']);
$week = $gameweek['gameweek'];
$deadline=$crud->checkDeadline($_SESSION['league'], $week);

$open=$gameweek['open'];

$competitorinleague=$crud->getCompetitorInLeague($_SESSION['profile_id'],$_SESSION['league']);
if($competitorinleague['count'] < 1) {echo '<script type="text/javascript">location.href="teamselection.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=teamselection.php" /></noscript> ';}else{$teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']); $_SESSION['competitor_id']=$teamrequest['competitor_id'];}

$rostercheck=$crud->existRoster($_SESSION['competitor_id'],$week);
if($rostercheck['num'] == 0){
    $rostercheck=$crud->existRoster($_SESSION['competitor_id'],$week+1);
    if($rostercheck['num'] == 0){
        echo '<script type="text/javascript">location.href="teamselection.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=teamselection.php" /></noscript> ';
    }
}

$teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);



if($deadline['0']==0){$checkroster=$crud->getRoster($teamrequest['competitor_id'],$week); if(empty($checkroster)){$weekcheck=$week+1;}else{$weekcheck=$week;}}else{$weekcheck=$week;};

switch($_SESSION['lang']){
    case 1:
        if($week>18){ 
            $buttondisable=true;
        }else{
        if($deadline['0']==1){$buttondisable=false;}
        elseif($deadline['0']==0 AND empty($checkroster)){echo '<div class="alert alert-primary text-center">Az aktuális fordulóra már nem tudsz változtatni a csapatodon. A változtatások a következő fordulóra lesznek érvényesek. </div>'; $buttondisable=false;}
        elseif($deadline['0']==0 AND !empty($checkroster)){echo '<div class="alert alert-primary text-center">Az aktuális fordulóra már nem tudsz változtatni a csapatodon. </div>'; $buttondisable=true;}
        }
    break;
    case 2:
        if($week>18){ 
            $buttondisable=true;
        }else{
        if($deadline['0']==1){$buttondisable=false;}
        elseif($deadline['0']==0 AND empty($checkroster)){echo '<div class="alert alert-primary text-center">You cannot make changes for the current gameweek. The changes you make will be valid for the next gameweek.</div>'; $buttondisable=false;}
        elseif($deadline['0']==0 AND !empty($checkroster)){echo '<div class="alert alert-primary text-center">You cannot make changes for the current gameweek anymore.</div>'; $buttondisable=true;}
        }
    break;
    case 3:
        if($week>18){ 
            $buttondisable=true;
        }else{
        if($deadline['0']==1){$buttondisable=false;}
        elseif($deadline['0']==0 AND empty($checkroster)){echo '<div class="alert alert-primary text-center">Du kannst keine Änderungen mehr für die aktuelle Spielwoche vornehmen. Die Änderungen, die du machst, gelten dann für die nächste Spielwoche.</div>'; $buttondisable=false;}
        elseif($deadline['0']==0 AND !empty($checkroster)){echo '<div class="alert alert-primary text-center">Du kannst jetzt keine Änderungen mehr für die aktuelle Spielwoche machen.</div>'; $buttondisable=true;}
        }
    break;
}

$teamname=$teamrequest['teamname'];
$weekpoints=$crud->getWeeklyteamresult($teamrequest['competitor_id'],$week-1);

$totalpoints=$crud->getTotalteamresult($teamrequest['competitor_id'],$week);

$teamresults=$crud->getTeamresultcount($teamrequest['competitor_id'],$week);

$maxteamresults=$crud->getTeamresultMax($teamrequest['competitor_id']);

$teamrank=$crud->getTeamrank($teamrequest['competitor_id'],$week-1);
$roster=$crud->getRoster($teamrequest['competitor_id'],$weekcheck);


if (isset($_POST['captain'])){
    switch($_POST['captain']){
        case 'player1cap':
            $newcaptain=$crud->updateCaptain($teamrequest['competitor_id'],$roster['player1'],$weekcheck);
            break;
        case 'player2cap':
            $newcaptain=$crud->updateCaptain($teamrequest['competitor_id'],$roster['player2'],$weekcheck);
            break;
        case 'player3cap':
            $newcaptain=$crud->updateCaptain($teamrequest['competitor_id'],$roster['player3'],$weekcheck);
            break;
        case 'player4cap':
            $newcaptain=$crud->updateCaptain($teamrequest['competitor_id'],$roster['player4'],$weekcheck);
            break;
        case 'player5cap':
            $newcaptain=$crud->updateCaptain($teamrequest['competitor_id'],$roster['player5'],$weekcheck);
            break; 
        case 'player6cap':
            $newcaptain=$crud->updateCaptain($teamrequest['competitor_id'],$roster['player6'],$weekcheck);
            break;
    }
}

if (isset($_POST['subst'])){
    switch($_POST['subst']){
        case 'player1subst':
            $substitute=$crud->updateSubstitute1($teamrequest['competitor_id'],$roster['player1'],$weekcheck);
            break;
        case 'player2subst':
            $substitute=$crud->updateSubstitute2($teamrequest['competitor_id'],$roster['player2'],$weekcheck);
            break;
        case 'player3subst':
            $substitute=$crud->updateSubstitute3($teamrequest['competitor_id'],$roster['player3'],$weekcheck);
            break;
        case 'player4subst':
            $substitute=$crud->updateSubstitute4($teamrequest['competitor_id'],$roster['player4'],$weekcheck);
            break;
        case 'player5subst':
            $substitute=$crud->updateSubstitute5($teamrequest['competitor_id'],$roster['player5'],$weekcheck);
            break;
        case 'player6subst':
            $substitute=$crud->updateSubstitute6($teamrequest['competitor_id'],$roster['player6'],$weekcheck);
            break;
        case 'player7subst':
            $substitute=$crud->switchSubstitutes($teamrequest['competitor_id'],$roster['player7'],$weekcheck);
            break;
    }
}

if (isset($_POST['trade'])){
    switch($_POST['trade']){
        case 'player1trade':
            $_SESSION['transf1']=$roster['player1'];
            break;
        case 'player2trade':
            $_SESSION['transf1']=$roster['player2'];
            break;
        case 'player3trade':
            $_SESSION['transf1']=$roster['player3'];
            break;
        case 'player4trade':
            $_SESSION['transf1']=$roster['player4'];
            break;
        case 'player5trade':
            $_SESSION['transf1']=$roster['player5'];
            break;
        case 'player6trade':
            $_SESSION['transf1']=$roster['player6'];
            break;
        case 'player7trade':
            $_SESSION['transf1']=$roster['player7'];
            break;
        case 'player8trade':
            $_SESSION['transf1']=$roster['player8'];
            break;
    }
    echo '<script type="text/javascript">location.href="transfer.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=transfer.php" /></noscript> ';
}

$roster=$crud->getRoster($teamrequest['competitor_id'],$weekcheck);

if($_SERVER['REQUEST_METHOD'] == 'POST') echo '<script type="text/javascript">location.href="redirect.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=redirect.php" /></noscript> '; 
// ez azért kellett, hogy a POST adatokat kitöröljük és ne adja be újra, ha valaki F5-öt nyom. A redirect.php simán visszairányít ide, de már POST adatok nélkül

$checkuser=$webuser->getUserbyID($_SESSION['profile_id']);
$picture=$crud->getPicture($checkuser['picture_id']);

?>



<style>
    #csapatompage{
        margin-top: 2rem;
    }

    .profilepic {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 20px;
        margin-left:2rem;
        margin-bottom: 0.5rem;
    }

    #profiledata{
        display: flex;
    }

    .profilepic img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        
    }
    .teamname{
        display: flex;
        align-items: center;
    }

    #teamname {
        font-size:2.5rem;
        margin-bottom: 0.5rem;
        font-weight: 400;
    }
    #teamstats{
        margin-left:0;
        padding-left: 0;
        /* background-image: linear-gradient(rgba(255, 255, 255, 0) 10%, rgba(255, 255, 255, 0.5) 68%, rgb(255, 255, 255) 100%), linear-gradient(to right, #75f9da 100%, #e1f7f4 82%); */
        background-image: linear-gradient(rgba(255, 255, 255, 0) 20px, rgba(255, 255, 255, 0.5) 75px, rgb(255, 255, 255) 120px), linear-gradient(to right, #01cae4 100%, #0146fe 82%);
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }

    #teamstats h3 {
        font-size: 1.5rem;
    }

    #playerbox{
        display:flex;
        flex-direction: row;
    }
    #logobox{
        display:flex;
        justify-content: center;
        align-items: center;
    }
    hr.divider {
        border: 2px solid red;
        border-radius: 1px;
        margin-top: 3rem;
        margin-bottom: 3rem;
    }

    #detailedresults{
        width: 100%;
    }

    #resulttable{
        margin-top: 1rem;
        text-align: center;
    }

    #resulttable th{
        padding-left: 10px;
        padding-right: 10px;
        font-size: 12pt;
    }
    #resulttable td{
        padding-top: 10px;
    }

    .subspic{
        width:30px;
    }

    .tradeinput{
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .tradeinput + img {
        cursor: pointer;
    }

    /* az átlag pontot kivesszük */
    table td:nth-of-type(7) {
        display: none;
    }
    table th:nth-of-type(7) {
        display: none;
    }

    @media (max-width: 900px) {
        table td:nth-of-type(8) {
            display: none;
        }
        table th:nth-of-type(8) {
            display: none;
        }
        #team {
            display:none;
        }
        #csk {
            font-size: 0;
            visibility:hidden;
        }
        #teamname {
            font-size:1.8rem;
        }
        #csapatompage{
            margin-left:0;
            padding:0;
            margin-top: 1rem;
        }
        #teamstats h3 {
            font-size: 1rem;
        }
        #teamstats h2 {
            font-size: 1.5rem;
        }

        .btn {
            font-size:11px;
            padding:6px;
        }
        
    }

    @media (max-width: 600px) {
        #csapatlista td:nth-of-type(4) {
            display: none;
        }
        #csapatlista th:nth-of-type(4) {
            display: none;
        }

        table th {
            font-size:11px;
        }
        #csk {
            font-size: 0;
            visibility: hidden;
        }
        
    }

    @media (max-width: 450px) {
        table td:nth-of-type(5) {
            display: none;
        }
        table th:nth-of-type(5) {
            display: none;
        }
        #csapatlista td:nth-of-type(<?php if($week==1){echo "6";}else{echo "3";}?>) {
            display: none;
        }
        #csapatlista th:nth-of-type(<?php if($week==1){echo "6";}else{echo "3";}?>) {
            display: none;
        }
        table td:nth-of-type(7) {
            display: none;
        }
        table th:nth-of-type(7) {
            display: none;
        }
        table td {
            font-size:12px;
        }
        #resulttable th{
            font-size: 10pt;
        }
        .subspic{
            width:20px;
        }
        #teamname {
            font-size:1.5rem;
        }
        .profilepic {
            width: 60px;
            height: 60px;
        }
        
    }

</style>

<?php require_once 'includes/minileagueselect.php'; ?>

<div class="container-fluid" id="csapatompage">
    <div class="row justify-content-md-center">
        <div class="col" id="profiledata">
            <div class="profilepic">
                <a href="profile.php">
                    <img src="img/profilepic/<?= $picture['link'] ?>" id="profilepic"alt="">
                </a>
            </div>
            <div class="teamname">
                <h2  id="teamname"><?php echo $teamname;?></h2>
            </div>
        </div>
    </div>
    <div class="row row-cols-2 text-center" id="teamstats">
    <div class="col">
        <h3>
            <?php switch($_SESSION['lang']){
                case 1: echo "Helyezés:";
                break;
                case 2: echo "Rank:";
                break;
                case 3: echo "Platz:";
                break;
            }?>
        </h3>
        <h2 class="fw-bold">
            <?php if(!$teamrank){echo " - ";}else {echo $teamrank['rank'];}?>
        </h2>
    </div>
    <div class="col">
        <h3>
            <?php switch($_SESSION['lang']){
                case 1: echo "Összes pont:";
                break;
                case 2: echo "Total points:";
                break;
                case 3: echo "Gesamtpunkte:";
                break;
            }?> 
        </h3>
        <h2 class="fw-bold">
            <?php if($totalpoints['totalpoints']==0){echo " - ";}else{echo $totalpoints['totalpoints'];}?>
        </h2>
    </div>
    <div class="col">
        <h3>
            <?php switch($_SESSION['lang']){
                case 1: echo "Heti pont:";
                break;
                case 2: echo "Weekly points:";
                break;
                case 3: echo "Wochenpunkte:";
                break;
            }?> 
        </h3>
        <h2 class="fw-bold">
            <?php if(!$weekpoints){echo " - ";}else{echo $weekpoints['weeklypoints'];}?>
        </h2>
    </div>
    <div class="col">
        <h3>
            <?php switch($_SESSION['lang']){
                case 1: echo "Átlag pont:";
                break;
                case 2: echo "Avg points:";
                break;
                case 3: echo "Durchschn. Punkte:";
                break;
            }?> 
        </h3>
        <h2 class="fw-bold">
            <?php if($totalpoints['totalpoints']==0){echo " - ";}else{echo round($totalpoints['totalpoints']/$teamresults['count'],1);}?>
        </h2>
    </div>
  </div>

<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="myteam">
    <table class="table table-hover table-fixed" style="text-align: center; vertical-align: middle" id="csapatlista">
	<thead>
		<tr>
            <?php switch($_SESSION['lang']){
                case 1:
                ?>
                <th></th> <!-- (ez üres a kapitány logónak) -->
                <th style="text-align: left">Kezdőcsapat</th>
                <th><abbr title="Ár">Ár</abbr></th>
                <th><abbr title="Lejátszott meccs">Meccsek</abbr></th>
                <th><abbr title="Összes szerzett pont">Össz</abbr></th>
                <th><abbr title="Előző fordulóban szerzett pontok">Heti</abbr></th>
                <th><abbr title="Átlag pont">Átlag</abbr></th>
                <th><abbr title="Következő meccs">Köv. meccs</abbr></th>
                <th id="csk">Csapatkapitány</th>
			    <th>Csere</th>
			    <th>Elad</th>
                <?php ;
                break;

                case 2:
                ?>
                <th></th> <!-- (ez üres a kapitány logónak) -->
                <th style="text-align: left">Starting team</th>
                <th><abbr title="Price">Price</abbr></th>
                <th><abbr title="Games played">Games</abbr></th>
                <th><abbr title="Total fantasy points">Total</abbr></th>
                <th><abbr title="Points in the previous gameweek">Week</abbr></th>
                <th><abbr title="Average points">Avg</abbr></th>
                <th><abbr title="Next match">Next match</abbr></th>
                <th id="csk">Captain</th>
			    <th>Subst.</th>
                <th>Trade</th>
                <?php ;
                break;

                case 3:
                    ?>
                    <th></th> <!-- (ez üres a kapitány logónak) -->
                    <th style="text-align: left">Startmannschaft</th>
                    <th><abbr title="Preis">Preis</abbr></th>
                    <th><abbr title="Spiele">Sp.</abbr></th>
                    <th><abbr title="Gesamtpunkte">GP</abbr></th>
                    <th><abbr title="Wochenpunkte - Punkte in der vorherigen Spielwoche">WP</abbr></th>
                    <th><abbr title="Durchschnittspunkte">Ø</abbr></th>
                    <th><abbr title="Nächstes Spiel">Nächstes Spiel</abbr></th>
                    <th id="csk">Kapitän</th>
                    <th><abbr title="Auswechslung">Ausw.</abbr></th>
			        <th>Tausch</th>
                    <?php ;
                break;
                
                    
                    
                    
                    

            }?>
            
		</tr>
	</thead>
	<tbody>
		<tr>
			<td> <!-- vector graphic a kapitány logónak -->
				<?php if($roster['player1'] == $roster['captain']){ ?>
                    <i class="bi bi-c-circle-fill"></i>
                <?php } ?>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team1=$player->getPlayerteam($roster['player1']);?>
                        <img src="img/teamlogo/<?php echo $team1['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player1'];?>" style="text-decoration:none; color:black;"><?php $player1name=$player->getPlayerbyID($roster['player1']); echo $player1name['playername'];?> </a></div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team1=$player->getPlayerteam($roster['player1']); echo $team1['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value1=$player->getPrice($roster['player1'],$week); if(isset($value1['price'])){echo $value1['price'];}else{echo " - ";}?></td>
			<td id="fullw"> <?php $p1games=$player->getPlayedgames($roster['player1']); if($p1games['playedgames']==0){echo 0;}else{echo $p1games['playedgames'];}?> </td>
			<td> <?php $p1total=$player->getTotalPlayerpoints($roster['player1']); if($p1games['playedgames']==0){echo 0;}else{echo $p1total['totalpoints'];}?></td>
			<td> <?php $p1weekly=$player->getWeeklyPlayerpoints($roster['player1'],$week-1); if($p1games['playedgames']==0){echo 0;}else{echo $p1weekly['weekpoints'];}?></td>
			<td> <?php if($p1games['playedgames']==0){echo 0;}else{echo number_format($p1total['totalpoints'] / $p1games['playedgames'],1);} ?> </td>
            <td id="fullw"> <?php if($team1['team_id']==99 OR $week>18){echo "-";}else{$p1home=$crud->checkHometeam($team1['team_id'],$week,$_SESSION['league']); $p1opp=$crud->getNextopponent($team1['team_id'],$week,$_SESSION['league']); if(!$p1opp){echo " - ";}else{if ($p1home['num']==1){echo $p1opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p1opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p1opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p1opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player1cap" id="player1cap" onchange="this.form.submit()" autocomplete="off" <?php if($buttondisable){ echo "disabled";}?> 
                <?php if($roster['player1'] == $roster['captain']) echo 'checked disabled'; ?> >
                <label class="btn btn-outline-secondary" for="player1cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
			<td>
                <label>
                    <input type="submit" class="tradeinput" name="subst" value="player1subst" id="player1subst" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";} if($roster['player1'] == $roster['captain']) echo 'disabled'; ?>>
                    <img class="subspic" src="img\subst.svg" alt="">
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player1trade" id="player1trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player1'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>
            </td>
		</tr>
        <tr> <!-- player2 -->
			<td> <!-- vector graphic a kapitány logónak -->
				<?php if($roster['player2'] == $roster['captain']){ ?>
                    <i class="bi bi-c-circle-fill"></i>
                <?php } ?>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team2=$player->getPlayerteam($roster['player2']);?>
                        <img src="img/teamlogo/<?php echo $team2['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player2'];?>" style="text-decoration:none; color:black;"><?php $player2name=$player->getPlayerbyID($roster['player2']); echo $player2name['playername'];?></a> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team2=$player->getPlayerteam($roster['player2']); echo $team2['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value2=$player->getPrice($roster['player2'],$week); if(isset($value2['price'])){echo $value2['price'];}else{echo " - ";}?></td>
			<td> <?php $p2games=$player->getPlayedgames($roster['player2']); if($p2games['playedgames']==0){echo 0;}else{echo $p2games['playedgames'];}?> </td>
			<td> <?php $p2total=$player->getTotalPlayerpoints($roster['player2']); if($p2games['playedgames']==0){echo 0;}else{echo $p2total['totalpoints'];}?></td>
			<td> <?php $p2weekly=$player->getWeeklyPlayerpoints($roster['player2'],$week-1); if($p2games['playedgames']==0){echo 0;}else{echo $p2weekly['weekpoints'];}?></td>
			<td> <?php if($p2games['playedgames']==0){echo 0;}else{echo number_format($p2total['totalpoints'] / $p2games['playedgames'],1);}; ?> </td>
            <td><?php if($team2['team_id']==99 OR $week>18){echo "-";}else{$p2home=$crud->checkHometeam($team2['team_id'],$week,$_SESSION['league']); $p2opp=$crud->getNextopponent($team2['team_id'],$week,$_SESSION['league']); if(!$p2opp){echo " - ";}else{if ($p2home['num']==1){echo $p2opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p2opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p2opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p2opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player2cap" id="player2cap" onchange="this.form.submit()" autocomplete="off" <?php if($buttondisable){ echo "disabled";}?>
                <?php if($roster['player2'] == $roster['captain']) echo 'checked disabled'; ?>>
                <label class="btn btn-outline-secondary" for="player2cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
			<td>
                <label>
                    <input type="submit" class="tradeinput" name="subst" value="player2subst" id="player2subst" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";} if($roster['player2'] == $roster['captain']) echo 'disabled'; ?>>
                    <img class="subspic" src="img\subst.svg" alt="">
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player2trade" id="player2trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player2'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>  
            </td>
		</tr>
        <tr> <!-- player3 -->
			<td> <!-- vector graphic a kapitány logónak -->
				<?php if($roster['player3'] == $roster['captain']){ ?>
                    <i class="bi bi-c-circle-fill"></i>
                <?php } ?>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team3=$player->getPlayerteam($roster['player3']);?>
                        <img src="img/teamlogo/<?php echo $team3['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player3'];?>" style="text-decoration:none; color:black;"><?php $player3name=$player->getPlayerbyID($roster['player3']); echo $player3name['playername'];?> </a></div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team3=$player->getPlayerteam($roster['player3']); echo $team3['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value3=$player->getPrice($roster['player3'],$week); if(isset($value3['price'])){echo $value3['price'];}else{echo " - ";}?></td>
			<td> <?php $p3games=$player->getPlayedgames($roster['player3']); if($p3games['playedgames']==0){echo 0;}else{echo $p3games['playedgames'];}?> </td>
			<td> <?php $p3total=$player->getTotalPlayerpoints($roster['player3']); if($p3games['playedgames']==0){echo 0;}else{echo $p3total['totalpoints'];}?></td>
			<td> <?php $p3weekly=$player->getWeeklyPlayerpoints($roster['player3'],$week-1); if($p3games['playedgames']==0){echo 0;}else{echo $p3weekly['weekpoints'];}?></td>
			<td> <?php if($p3games['playedgames']==0){echo 0;}else{echo number_format($p3total['totalpoints'] / $p3games['playedgames'],1);}; ?> </td>
            <td><?php if($team3['team_id']==99 OR $week>18){echo "-";}else{$p3home=$crud->checkHometeam($team3['team_id'],$week,$_SESSION['league']); $p3opp=$crud->getNextopponent($team3['team_id'],$week,$_SESSION['league']); if(!$p3opp){echo " - ";}else{if ($p3home['num']==1){echo $p3opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p3opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p3opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p3opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player3cap" id="player3cap" onchange="this.form.submit()" autocomplete="off" <?php if($buttondisable){ echo "disabled";}?>
                <?php if($roster['player3'] == $roster['captain']) echo 'checked disabled'; ?>>
                <label class="btn btn-outline-secondary" for="player3cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
			<td>
                <label>
                    <input type="submit" class="tradeinput" name="subst" value="player3subst" id="player3subst" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";} if($roster['player3'] == $roster['captain']) echo 'disabled'; ?>>
                    <img class="subspic" src="img\subst.svg" alt="">
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player3trade" id="player3trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player3'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>   
            </td>
		</tr>
        <tr> <!-- player4 -->
			<td> <!-- vector graphic a kapitány logónak -->
				<?php if($roster['player4'] == $roster['captain']){ ?>
                    <i class="bi bi-c-circle-fill"></i>
                <?php } ?>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team4=$player->getPlayerteam($roster['player4']);?>
                        <img src="img/teamlogo/<?php echo $team4['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player4'];?>" style="text-decoration:none; color:black;"><?php $player4name=$player->getPlayerbyID($roster['player4']); echo $player4name['playername'];?> </a></div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team4=$player->getPlayerteam($roster['player4']); echo $team4['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value4=$player->getPrice($roster['player4'],$week); if(isset($value4['price'])){echo $value4['price'];}else{echo " - ";}?></td>
			<td> <?php $p4games=$player->getPlayedgames($roster['player4']); if($p4games['playedgames']==0){echo 0;}else{echo $p4games['playedgames'];}?> </td>
			<td> <?php $p4total=$player->getTotalPlayerpoints($roster['player4']); if($p4games['playedgames']==0){echo 0;}else{echo $p4total['totalpoints'];}?></td>
			<td> <?php $p4weekly=$player->getWeeklyPlayerpoints($roster['player4'],$week-1); if($p4games['playedgames']==0){echo 0;}else{echo $p4weekly['weekpoints'];}?></td>
			<td> <?php if($p4games['playedgames']==0){echo 0;}else{echo number_format($p4total['totalpoints'] / $p4games['playedgames'],1);}; ?> </td>
            <td><?php if($team4['team_id']==99 OR $week>18){echo "-";}else{$p4home=$crud->checkHometeam($team4['team_id'],$week,$_SESSION['league']); $p4opp=$crud->getNextopponent($team4['team_id'],$week,$_SESSION['league']); if(!$p4opp){echo " - ";}else{if ($p4home['num']==1){echo $p4opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p4opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p4opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p4opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player4cap" id="player4cap" onchange="this.form.submit()" autocomplete="off" <?php if($buttondisable){ echo "disabled";}?>
                <?php if($roster['player4'] == $roster['captain']) echo 'checked disabled'; ?>>
                <label class="btn btn-outline-secondary" for="player4cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
			<td>
                <label>
                    <input type="submit" class="tradeinput" name="subst" value="player4subst" id="player4subst" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";} if($roster['player4'] == $roster['captain']) echo 'disabled'; ?>>
                    <img class="subspic" src="img\subst.svg" alt="">
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player4trade" id="player4trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player4'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>     
            </td>
		</tr>
        <tr> <!-- player5 -->
			<td> <!-- vector graphic a kapitány logónak -->
				<?php if($roster['player5'] == $roster['captain']){ ?>
                    <i class="bi bi-c-circle-fill"></i>
                <?php } ?>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team5=$player->getPlayerteam($roster['player5']);?>
                        <img src="img/teamlogo/<?php echo $team5['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player5'];?>" style="text-decoration:none; color:black;"><?php $player5name=$player->getPlayerbyID($roster['player5']); echo $player5name['playername'];?></a> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team5=$player->getPlayerteam($roster['player5']); echo $team5['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value5=$player->getPrice($roster['player5'],$week); if(isset($value5['price'])){echo $value5['price'];}else{echo " - ";}?></td>
			<td> <?php $p5games=$player->getPlayedgames($roster['player5']); if($p5games['playedgames']==0){echo 0;}else{echo $p5games['playedgames'];}?> </td>
			<td> <?php $p5total=$player->getTotalPlayerpoints($roster['player5']); if($p5games['playedgames']==0){echo 0;}else{echo $p5total['totalpoints'];}?></td>
			<td> <?php $p5weekly=$player->getWeeklyPlayerpoints($roster['player5'],$week-1); if($p5games['playedgames']==0){echo 0;}else{echo $p5weekly['weekpoints'];}?></td>
			<td> <?php if($p5games['playedgames']==0){echo 0;}else{echo number_format($p5total['totalpoints'] / $p5games['playedgames'],1);}; ?> </td>
            <td><?php if($team5['team_id']==99 OR $week>18){echo "-";}else{$p5home=$crud->checkHometeam($team5['team_id'],$week,$_SESSION['league']); $p5opp=$crud->getNextopponent($team5['team_id'],$week,$_SESSION['league']); if(!$p5opp){echo " - ";}else{if ($p5home['num']==1){echo $p5opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p5opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p5opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p5opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player5cap" id="player5cap" onchange="this.form.submit()" autocomplete="off" <?php if($buttondisable){ echo "disabled";}?>
                <?php if($roster['player5'] == $roster['captain']) echo 'checked disabled'; ?>>
                <label class="btn btn-outline-secondary" for="player5cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
			<td>
                <label>
                    <input type="submit" class="tradeinput" name="subst" value="player5subst" id="player5subst" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";} if($roster['player5'] == $roster['captain']) echo 'disabled'; ?>>
                    <img class="subspic" src="img\subst.svg" alt="">
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player5trade" id="player5trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player5'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>   
            </td>
		</tr>
        <tr> <!-- player6 -->
			<td> <!-- vector graphic a kapitány logónak -->
				<?php if($roster['player6'] == $roster['captain']){ ?>
                    <i class="bi bi-c-circle-fill"></i>
                <?php } ?>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team6=$player->getPlayerteam($roster['player6']);?>
                        <img src="img/teamlogo/<?php echo $team6['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player6'];?>" style="text-decoration:none; color:black;"><?php $player6name=$player->getPlayerbyID($roster['player6']); echo $player6name['playername'];?> </a></div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team6=$player->getPlayerteam($roster['player6']); echo $team6['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value6=$player->getPrice($roster['player6'],$week); if(isset($value6['price'])){echo $value6['price'];}else{echo " - ";}?></td>
			<td> <?php $p6games=$player->getPlayedgames($roster['player6']); if($p6games['playedgames']==0){echo 0;}else{echo $p6games['playedgames'];}?> </td>
			<td> <?php $p6total=$player->getTotalPlayerpoints($roster['player6']); if($p6games['playedgames']==0){echo 0;}else{echo $p6total['totalpoints'];}?></td>
			<td> <?php $p6weekly=$player->getWeeklyPlayerpoints($roster['player6'],$week-1); if($p6games['playedgames']==0){echo 0;}else{echo $p6weekly['weekpoints'];}?></td>
			<td> <?php if($p6games['playedgames']==0){echo 0;}else{echo number_format($p6total['totalpoints'] / $p6games['playedgames'],1);}; ?> </td>
            <td><?php if($team6['team_id']==99 OR $week>18){echo "-";}else{$p6home=$crud->checkHometeam($team6['team_id'],$week,$_SESSION['league']); $p6opp=$crud->getNextopponent($team6['team_id'],$week,$_SESSION['league']); if(!$p6opp){echo " - ";}else{if ($p6home['num']==1){echo $p6opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p6opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p6opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p6opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player6cap" id="player6cap" onchange="this.form.submit()" autocomplete="off" <?php if($buttondisable){ echo "disabled";}?>
                <?php if($roster['player6'] == $roster['captain']) echo 'checked disabled'; ?>>
                <label class="btn btn-outline-secondary" for="player6cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
			<td>
                <label>
                    <input type="submit" class="tradeinput" name="subst" value="player6subst" id="player6subst" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";} if($roster['player6'] == $roster['captain']) echo 'disabled'; ?>>
                    <img class="subspic" src="img\subst.svg" alt="">
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player6trade" id="player6trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player6'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>
            </td>
		</tr>
	</tbody>
<!-- tartalékok -->
	<thead>
		<tr>
            <?php switch($_SESSION['lang']){
                case 1:
                ?>
                <th></th> <!-- (ez üres a kapitány logónak) -->
                <th style="text-align: left">Tartalékok</th>
                <th><abbr title="Ár">Ár</abbr></th>
                <th><abbr title="Lejátszott meccs">Meccsek</abbr></th>
                <th><abbr title="Összes szerzett pont">Össz</abbr></th>
                <th><abbr title="Előző fordulóban szerzett pontok">Heti</abbr></th>
                <th><abbr title="Átlag pont">Átlag</abbr></th>
                <th><abbr title="Következő meccs">Köv. meccs</abbr></th>
                <th></th>
			    <th></th>
                <?php ;
                break;

                case 2:
                ?>
                <th></th> <!-- (ez üres a kapitány logónak) -->
                <th style="text-align: left">Substitutes</th>
                <th><abbr title="Price">Price</abbr></th>
                <th><abbr title="Games played">Games</abbr></th>
                <th><abbr title="Total fantasy points">Total</abbr></th>
                <th><abbr title="Points in the previous gameweek">Week</abbr></th>
                <th><abbr title="Average points">Avg</abbr></th>
                <th><abbr title="Next match">Next match</abbr></th>
                <th></th>
			    <th></th>
                <?php ;
                break;

                case 3:
                    ?>
                    <th></th> <!-- (ez üres a kapitány logónak) -->
                    <th style="text-align: left">Ersatzspieler</th>
                    <th><abbr title="Preis">Preis</abbr></th>
                    <th><abbr title="Spiele">Sp.</abbr></th>
                    <th><abbr title="Gesamtpunkte">GP</abbr></th>
                    <th><abbr title="Wochenpunkte - Punkte in der vorherigen Spielwoche">WP</abbr></th>
                    <th><abbr title="Durchschnittspunkte">Ø</abbr></th>
                    <th><abbr title="Nächstes Spiel">Nächstes Spiel</abbr></th>
                    <th></th>
                    <th></th>
                    <?php ;
                break;
            }?>
		</tr>
	</thead>
	<tbody>
        <tr> <!-- player7 -->
			<td>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team7=$player->getPlayerteam($roster['player7']);?>
                        <img src="img/teamlogo/<?php echo $team7['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player7'];?>" style="text-decoration:none; color:black;"><?php $player7name=$player->getPlayerbyID($roster['player7']); echo $player7name['playername'];?></a> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team7=$player->getPlayerteam($roster['player7']); echo $team7['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value7=$player->getPrice($roster['player7'],$week); if(isset($value7['price'])){echo $value7['price'];}else{echo " - ";}?></td>
			<td> <?php $p7games=$player->getPlayedgames($roster['player7']); if($p7games['playedgames']==0){echo 0;}else{echo $p7games['playedgames'];}?> </td>
			<td> <?php $p7total=$player->getTotalPlayerpoints($roster['player7']); if($p7games['playedgames']==0){echo 0;}else{echo $p7total['totalpoints'];}?></td>
			<td> <?php $p7weekly=$player->getWeeklyPlayerpoints($roster['player7'],$week-1); if($p7games['playedgames']==0){echo 0;}else{echo $p7weekly['weekpoints'];}?></td>
			<td> <?php if($p7games['playedgames']==0){echo 0;}else{echo number_format($p7total['totalpoints'] / $p7games['playedgames'],1);}; ?> </td>
            <td><?php if($team7['team_id']==99 OR $week>18){echo "-";}else{$p7home=$crud->checkHometeam($team7['team_id'],$week,$_SESSION['league']); $p7opp=$crud->getNextopponent($team7['team_id'],$week,$_SESSION['league']); if(!$p7opp){echo " - ";}else{if ($p7home['num']==1){echo $p7opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p7opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p7opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p7opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player7cap" id="player7cap" onchange="this.form.submit()" autocomplete="off" disabled>
                <label class="btn btn-outline-secondary" for="player7cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
			<td rowspan="2">
                <label>
                    <input type="submit" class="tradeinput" name="subst" value="player7subst" id="player7subst" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";} if($roster['player7'] == $roster['captain']) echo 'disabled'; ?>>
                    <img class="subspic" src="img\subst.svg" alt="">
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player7trade" id="player7trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player7'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>    
            </td>
		</tr>
        <tr> <!-- player8 -->
			<td>
			</td>
			<td style="text-align: left">
                <div id="playerbox">
                    <div id="logobox">
                        <?php $team8=$player->getPlayerteam($roster['player8']);?>
                        <img src="img/teamlogo/<?php echo $team8['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                    </div>
                    <div>
                        <div id="playername"><a href="playerdata.php?id=<?php echo $roster['player8'];?>" style="text-decoration:none; color:black;"><?php $player8name=$player->getPlayerbyID($roster['player8']); echo $player8name['playername'];?> </a> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team8=$player->getPlayerteam($roster['player8']); echo $team8['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value8=$player->getPrice($roster['player8'],$week); if(isset($value8['price'])){echo $value8['price'];}else{echo " - ";}?></td>
			<td> <?php $p8games=$player->getPlayedgames($roster['player8']); if($p8games['playedgames']==0){echo 0;}else{echo $p8games['playedgames'];}?> </td>
			<td> <?php $p8total=$player->getTotalPlayerpoints($roster['player8']); if($p8games['playedgames']==0){echo 0;}else{echo $p8total['totalpoints'];}?></td>
			<td> <?php $p8weekly=$player->getWeeklyPlayerpoints($roster['player8'],$week-1); if($p8games['playedgames']==0){echo 0;}else{echo $p8weekly['weekpoints'];}?></td>
			<td> <?php if($p8games['playedgames']==0){echo 0;}else{echo number_format($p8total['totalpoints'] / $p8games['playedgames'],1);}; ?> </td>
            <td><?php if($team8['team_id']==99 OR $week>18){echo "-";}else{$p8home=$crud->checkHometeam($team8['team_id'],$week,$_SESSION['league']); $p8opp=$crud->getNextopponent($team8['team_id'],$week,$_SESSION['league']); if(!$p8opp){echo " - ";}else{if ($p8home['num']==1){echo $p8opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p8opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p8opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p8opp['short']." (A)";}}}?></td>
			<td>
                <input type="radio" class="btn-check" name="captain" value="player8cap" id="player8cap" onchange="this.form.submit()" autocomplete="off" disabled>
                <label class="btn btn-outline-secondary" for="player8cap">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "CSK";
                        break;
                        case 2: echo "Capt.";
                        break;
                        case 3: echo "Kpt.";
                        break;
                    }?>
                </label>
			</td>
            <td>
                <label>
                    <input type="submit" class="tradeinput" name="trade" value="player8trade" id="player8trade" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}if($roster['player8'] == $roster['captain']) echo 'disabled'; ?>>
                    <img src="img\trade.webp" class="subspic" alt="">
                </label>  
            </td>
		</tr>
	</tbody>
</table>
</form>
    <div>
        <hr class="divider" id="cut">
    </div>





<div id="detailedresults">
    <h6>
        <?php switch($_SESSION['lang']){
            case 1: echo "Előző fordulókban elért eredmények";
            break;
            case 2: echo "Previous results";
            break;
            case 3: echo "Ergebnisse in früheren Spielwochen";
            break;
        }?>
    </h6>
    <table id="resulttable" class="table myaccordion table-hover">
        <tr>
            <?php switch($_SESSION['lang']){
                case 1:
                ?>
                    <th>Forduló</th>
                    <th>Heti pont</th>
                    <th>Összes pont</th>
                    <th colspan="2">Helyezés</th>
                <?php ;
                break;

                case 2:
                ?>
                    <th>Gameweek</th>
                    <th>Weekly points</th>
                    <th>Total points</th>
                    <th colspan="2">Rank</th>  
                <?php ;
                break;

                case 3:
                ?>
                    <th>Spielwoche</th>
                    <th>Wochenpunkte</th>
                    <th>Gesamtpunkte</th>
                    <th colspan="2">Platz</th>  
                <?php ;
                break;
            }?>  
            
        </tr>
        <?php 
        $detailed=$crud->getDetailedTeamresult($_SESSION['competitor_id']);
        $totalteampoints=0;
        while($r = $detailed->fetch(PDO::FETCH_ASSOC)){ ?>
            <tr class="collapsed" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $r['gameweek']?>" aria-expanded="false" aria-controls="collapse<?php echo $r['gameweek']?>">
                <td><?php echo $r['gameweek']?>.</td>
                <td><?php echo $r['weeklypoints']?></td>
                <td><?php $totalteampoints=$totalteampoints+$r['weeklypoints']; echo number_format($totalteampoints,1, ",", " ")?></td>
                <td style="text-align:right"><?php echo $r['rank']?></td>
                <td><?php if(!isset($previousrank)){ echo '<i class="bi bi-dash-circle-fill"></i>';}elseif(($previousrank) AND $previousrank > $r['rank']){echo '<i class="bi bi-arrow-up-circle-fill text-success"></i>';}elseif(($previousrank) AND $previousrank < $r['rank']){echo '<i class="bi bi-arrow-down-circle-fill text-danger"></i>';}else{echo '<i class="bi bi-dash-circle-fill"></i>';}?></td>
            </tr>
            <tr>
                <td id="collapse<?php echo $r['gameweek']?>" class="collapse acc" colspan="5" data-parent="#resulttable">
                    <?php 
                        $rosterfordetail=$crud->getRoster($_SESSION['competitor_id'],$r['gameweek']);
                        $p1fordetail=$player->getPlayerbyID($rosterfordetail['player1']);
                        $p2fordetail=$player->getPlayerbyID($rosterfordetail['player2']);
                        $p3fordetail=$player->getPlayerbyID($rosterfordetail['player3']);
                        $p4fordetail=$player->getPlayerbyID($rosterfordetail['player4']);
                        $p5fordetail=$player->getPlayerbyID($rosterfordetail['player5']);
                        $p6fordetail=$player->getPlayerbyID($rosterfordetail['player6']);
                        $p7fordetail=$player->getPlayerbyID($rosterfordetail['player7']);
                        $p8fordetail=$player->getPlayerbyID($rosterfordetail['player8']);
                    ?>
                    <strong>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Kezdőcsapat:";
                            break;
                            case 2: echo "Starting team:";
                            break;
                            case 3: echo "Startmannschaft:";
                            break;
                        }?> 
                    </strong><span><?php if($rosterfordetail['player1']==$rosterfordetail['captain']){echo "<strong>".$p1fordetail['playername']."</strong>";}else{echo $p1fordetail['playername'];} ?>, 
                    <?php if($rosterfordetail['player2']==$rosterfordetail['captain']){echo "<strong>".$p2fordetail['playername']."</strong>";}else{echo $p2fordetail['playername'];} ?>, 
                    <?php if($rosterfordetail['player3']==$rosterfordetail['captain']){echo "<strong>".$p3fordetail['playername']."</strong>";}else{echo $p3fordetail['playername'];} ?>, 
                    <?php if($rosterfordetail['player4']==$rosterfordetail['captain']){echo "<strong>".$p4fordetail['playername']."</strong>";}else{echo $p4fordetail['playername'];} ?>, 
                    <?php if($rosterfordetail['player5']==$rosterfordetail['captain']){echo "<strong>".$p5fordetail['playername']."</strong>";}else{echo $p5fordetail['playername'];} ?>, 
                    <?php if($rosterfordetail['player6']==$rosterfordetail['captain']){echo "<strong>".$p6fordetail['playername']."</strong>";}else{echo $p6fordetail['playername'];} ?>, 
                    <?php switch($_SESSION['lang']){
                            case 1: echo " <strong>Tartalékok: </strong>" . $p7fordetail['playername'] . ", " . $p8fordetail['playername'];
                            break;
                            case 2: echo " <strong>Substitutes: </strong>" . $p7fordetail['playername'] . ", " . $p8fordetail['playername'];
                            break;
                            case 3: echo " <strong>Ersatzspieler: </strong>" . $p7fordetail['playername'] . ", " . $p8fordetail['playername'];
                            break;
                        }?>
                    </span>
                </td>
            </tr>
        <?php $previousrank=$r['rank'];}?>
    </table>
</div>

<?php }else{require_once 'includes/minileagueselect.php';} ?>


</div>
<script src="scroll.js"></script>
<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>