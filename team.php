<?php 
$title = "Csapat";
require_once 'includes/header.php';
require_once 'db/conn.php';
$team_id = (int)$_GET['teamid'];


if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if ( !empty($team_id) && $team_id > 0) {
    // Fecth news
    $competitor=$crud->getCompetitor($team_id);
    $gameweek = $crud->getGameweek($competitor['league_id']);
    $week = $gameweek['gameweek']; 
    $roster = $crud->getRoster($team_id,$week-1);
    if($roster && !empty($roster)){
        
        $weekpoints=$crud->getWeeklyteamresult($team_id,$week-1);
        $totalpoints=$crud->getTotalteamresult($team_id,$week);
        $teamresults=$crud->getTeamresultcount($team_id,$week);
        $maxteamresults=$crud->getTeamresultMax($team_id);
        $teamrank=$crud->getTeamrank($team_id,$week-1);
        $picture=$crud->getPicture($competitor['picture_id']);
    }else{
        $roster = false;
        echo '<script type="text/javascript">location.href="standings.php";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=standings.php" /></noscript> ';
    }
    
}else{
    $roster = false;
    echo '<script type="text/javascript">location.href="standings.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=standings.php" /></noscript> ';
}



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
        flex-direction: row;
        align-items: center;
    }

    .profilepic img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        
    }
    #teamname {
        font-size:2.5rem;
        /* margin-bottom: 1rem; */
        /* padding-left: 2rem; */
        font-weight: 400;
    }
    #trainer{
        font-size:1.5rem;
        margin-bottom: 1rem;
        /* padding-left: 2rem; */
        font-weight: 400;
    }
    #teamstats{
        margin-left:0;
        padding-left: 0;
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

    /* átlagpontot kivenni egyelőre */
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
            display:none;
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
        table td:nth-of-type(4) {
            display: none;
        }
        table th:nth-of-type(4) {
            display: none;
        }

        table th {
            font-size:11px;
        }
        #csk {
            display:none;
        }
        
    }

    @media (max-width: 430px) {
        table td:nth-of-type(5) {
            display: none;
        }
        table th:nth-of-type(5) {
            display: none;
        }
        table td:nth-of-type(3) {
            display: none;
        }
        table th:nth-of-type(3) {
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
        #teamname {
            font-size:1.5rem;
        }
        #trainer{
            font-size: 1.2rem;
        }
        .profilepic{
            width: 60px;
            height: 60px;
        }
        
    }

</style>


<?php if ( $roster && !empty($roster) ){ 
    $competitor=$crud->getCompetitor($team_id);
    ?>
    
    <div class="container-fluid" id="csapatompage">
    <div class="row justify-content-md-center">
        <div id="profiledata">
            <div class="profilepic">
                <img src="img/profilepic/<?= $picture['link'] ?>" id="profilepic"alt="">
            </div>
            <div >
                <h2  id="teamname"><?php echo $competitor['teamname'];?></h2>
                <h4  id="trainer"><?php echo $competitor['alias'];?></h4>
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
                        <div id="playername"><?php $player1name=$player->getPlayerbyID($roster['player1']); echo $player1name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team1=$player->getPlayerteam($roster['player1']); echo $team1['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value1=$player->getPrice($roster['player1'],$week); if(isset($value1['price'])){echo $value1['price'];}else{echo " - ";};?></td>
			<td id="fullw"> <?php $p1games=$player->getPlayedgames($roster['player1']); if($p1games['playedgames']==0){echo 0;}else{echo $p1games['playedgames'];}?> </td>
			<td> <?php $p1total=$player->getTotalPlayerpoints($roster['player1']); if($p1games['playedgames']==0){echo 0;}else{echo $p1total['totalpoints'];}?></td>
			<td> <?php $p1weekly=$player->getWeeklyPlayerpoints($roster['player1'],$week-1); if($p1games['playedgames']==0){echo 0;}elseif($p1weekly['weekpoints']==0){echo 0;}else{echo $p1weekly['weekpoints'];}?></td>
			<td> <?php if($p1games['playedgames']==0){echo 0;}else{echo number_format($p1total['totalpoints'] / $p1games['playedgames'],1);} ?> </td>
            <td id="fullw"> <?php if($team1['team_id']==99 OR $week>18){echo "-";}else{$p1home=$crud->checkHometeam($team1['team_id'],$week,$competitor['league_id']); $p1opp=$crud->getNextopponent($team1['team_id'],$week,$competitor['league_id']); if(!$p1opp){echo " - ";}else{if($p1home['num']==1){echo $p1opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p1opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p1opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p1opp['short']." (A)";}}}?></td>
			
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
                        <div id="playername"><?php $player2name=$player->getPlayerbyID($roster['player2']); echo $player2name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team2=$player->getPlayerteam($roster['player2']); echo $team2['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value2=$player->getPrice($roster['player2'],$week); if(isset($value2['price'])){echo $value2['price'];}else{echo " - ";};?></td>
			<td> <?php $p2games=$player->getPlayedgames($roster['player2']); if($p2games['playedgames']==0){echo 0;}else{echo $p2games['playedgames'];}?> </td>
			<td> <?php $p2total=$player->getTotalPlayerpoints($roster['player2']); if($p2games['playedgames']==0){echo 0;}else{echo $p2total['totalpoints'];}?></td>
			<td> <?php $p2weekly=$player->getWeeklyPlayerpoints($roster['player2'],$week-1); if($p2games['playedgames']==0){echo 0;}elseif($p2weekly['weekpoints']==0){echo 0;}else{echo $p2weekly['weekpoints'];}?></td>
			<td> <?php if($p2games['playedgames']==0){echo 0;}else{echo number_format($p2total['totalpoints'] / $p2games['playedgames'],1);}; ?> </td>
            <td><?php if($team2['team_id']==99 OR $week>18){echo "-";}else{$p2home=$crud->checkHometeam($team2['team_id'],$week,$competitor['league_id']); $p2opp=$crud->getNextopponent($team2['team_id'],$week,$competitor['league_id']); if(!$p2opp){echo " - ";}else{if($p2home['num']==1){echo $p2opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p2opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p2opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p2opp['short']." (A)";}}}?></td>
			
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
                        <div id="playername"><?php $player3name=$player->getPlayerbyID($roster['player3']); echo $player3name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team3=$player->getPlayerteam($roster['player3']); echo $team3['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value3=$player->getPrice($roster['player3'],$week); if(isset($value3['price'])){echo $value3['price'];}else{echo " - ";};?></td>
			<td> <?php $p3games=$player->getPlayedgames($roster['player3']); if($p3games['playedgames']==0){echo 0;}else{echo $p3games['playedgames'];}?> </td>
			<td> <?php $p3total=$player->getTotalPlayerpoints($roster['player3']); if($p3games['playedgames']==0){echo 0;}else{echo $p3total['totalpoints'];}?></td>
			<td> <?php $p3weekly=$player->getWeeklyPlayerpoints($roster['player3'],$week-1); if($p3games['playedgames']==0){echo 0;}elseif($p3weekly['weekpoints']==0){echo 0;}else{echo $p3weekly['weekpoints'];}?></td>
			<td> <?php if($p3games['playedgames']==0){echo 0;}else{echo number_format($p3total['totalpoints'] / $p3games['playedgames'],1);}; ?> </td>
            <td><?php if($team3['team_id']==99 OR $week>18){echo "-";}else{$p3home=$crud->checkHometeam($team3['team_id'],$week,$competitor['league_id']); $p3opp=$crud->getNextopponent($team3['team_id'],$week,$competitor['league_id']); if(!$p3opp){echo " - ";}else{if($p3home['num']==1){echo $p3opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p3opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p3opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p3opp['short']." (A)";}}}?></td>
			
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
                        <div id="playername"><?php $player4name=$player->getPlayerbyID($roster['player4']); echo $player4name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team4=$player->getPlayerteam($roster['player4']); echo $team4['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value4=$player->getPrice($roster['player4'],$week); if(isset($value4['price'])){echo $value4['price'];}else{echo " - ";};?></td>
			<td> <?php $p4games=$player->getPlayedgames($roster['player4']); if($p4games['playedgames']==0){echo 0;}else{echo $p4games['playedgames'];}?> </td>
			<td> <?php $p4total=$player->getTotalPlayerpoints($roster['player4']); if($p4games['playedgames']==0){echo 0;}else{echo $p4total['totalpoints'];}?></td>
			<td> <?php $p4weekly=$player->getWeeklyPlayerpoints($roster['player4'],$week-1); if($p4games['playedgames']==0){echo 0;}elseif($p4weekly['weekpoints']==0){echo 0;}else{echo $p4weekly['weekpoints'];}?></td>
			<td> <?php if($p4games['playedgames']==0){echo 0;}else{echo number_format($p4total['totalpoints'] / $p4games['playedgames'],1);}; ?> </td>
            <td><?php if($team4['team_id']==99 OR $week>18){echo "-";}else{$p4home=$crud->checkHometeam($team4['team_id'],$week,$competitor['league_id']); $p4opp=$crud->getNextopponent($team4['team_id'],$week,$competitor['league_id']); if(!$p4opp){echo " - ";}else{if($p4home['num']==1){echo $p4opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p4opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p4opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p4opp['short']." (A)";}}}?></td>
			
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
                        <div id="playername"><?php $player5name=$player->getPlayerbyID($roster['player5']); echo $player5name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team5=$player->getPlayerteam($roster['player5']); echo $team5['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value5=$player->getPrice($roster['player5'],$week); if(isset($value5['price'])){echo $value5['price'];}else{echo " - ";};?></td>
			<td> <?php $p5games=$player->getPlayedgames($roster['player5']); if($p5games['playedgames']==0){echo 0;}else{echo $p5games['playedgames'];}?> </td>
			<td> <?php $p5total=$player->getTotalPlayerpoints($roster['player5']); if($p5games['playedgames']==0){echo 0;}else{echo $p5total['totalpoints'];}?></td>
			<td> <?php $p5weekly=$player->getWeeklyPlayerpoints($roster['player5'],$week-1); if($p5games['playedgames']==0){echo 0;}elseif($p5weekly['weekpoints']==0){echo 0;}else{echo $p5weekly['weekpoints'];}?></td>
			<td> <?php if($p5games['playedgames']==0){echo 0;}else{echo number_format($p5total['totalpoints'] / $p5games['playedgames'],1);}; ?> </td>
            <td><?php if($team5['team_id']==99 OR $week>18){echo "-";}else{$p5home=$crud->checkHometeam($team5['team_id'],$week,$competitor['league_id']); $p5opp=$crud->getNextopponent($team5['team_id'],$week,$competitor['league_id']); if(!$p5opp){echo " - ";}else{if($p5home['num']==1){echo $p5opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p5opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p5opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p5opp['short']." (A)";}}}?></td>
			
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
                        <div id="playername"><?php $player6name=$player->getPlayerbyID($roster['player6']); echo $player6name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team6=$player->getPlayerteam($roster['player6']); echo $team6['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value6=$player->getPrice($roster['player6'],$week); if(isset($value6['price'])){echo $value6['price'];}else{echo " - ";};?></td>
			<td> <?php $p6games=$player->getPlayedgames($roster['player6']); if($p6games['playedgames']==0){echo 0;}else{echo $p6games['playedgames'];}?> </td>
			<td> <?php $p6total=$player->getTotalPlayerpoints($roster['player6']); if($p6games['playedgames']==0){echo 0;}else{echo $p6total['totalpoints'];}?></td>
			<td> <?php $p6weekly=$player->getWeeklyPlayerpoints($roster['player6'],$week-1); if($p6games['playedgames']==0){echo 0;}elseif($p6weekly['weekpoints']==0){echo 0;}else{echo $p6weekly['weekpoints'];}?></td>
			<td> <?php if($p6games['playedgames']==0){echo 0;}else{echo number_format($p6total['totalpoints'] / $p6games['playedgames'],1);}; ?> </td>
            <td><?php if($team6['team_id']==99 OR $week>18){echo "-";}else{$p6home=$crud->checkHometeam($team6['team_id'],$week,$competitor['league_id']); $p6opp=$crud->getNextopponent($team6['team_id'],$week,$competitor['league_id']); if(!$p6opp){echo " - ";}else{if($p6home['num']==1){echo $p6opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p6opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p6opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p6opp['short']." (A)";}}}?></td>
			
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
                        <div id="playername"><?php $player7name=$player->getPlayerbyID($roster['player7']); echo $player7name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team7=$player->getPlayerteam($roster['player7']); echo $team7['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value7=$player->getPrice($roster['player7'],$week); if(isset($value7['price'])){echo $value7['price'];}else{echo " - ";};?></td>
			<td> <?php $p7games=$player->getPlayedgames($roster['player7']); if($p7games['playedgames']==0){echo 0;}else{echo $p7games['playedgames'];}?> </td>
			<td> <?php $p7total=$player->getTotalPlayerpoints($roster['player7']); if($p7games['playedgames']==0){echo 0;}else{echo $p7total['totalpoints'];}?></td>
			<td> <?php $p7weekly=$player->getWeeklyPlayerpoints($roster['player7'],$week-1); if($p7games['playedgames']==0){echo 0;}elseif($p7weekly['weekpoints']==0){echo 0;}else{echo $p7weekly['weekpoints'];}?></td>
			<td> <?php if($p7games['playedgames']==0){echo 0;}else{echo number_format($p7total['totalpoints'] / $p7games['playedgames'],1);}; ?> </td>
            <td><?php if($team7['team_id']==99 OR $week>18){echo "-";}else{$p7home=$crud->checkHometeam($team7['team_id'],$week,$competitor['league_id']); $p7opp=$crud->getNextopponent($team7['team_id'],$week,$competitor['league_id']); if(!$p7opp){echo " - ";}else{if($p7home['num']==1){echo $p7opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p7opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p7opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p7opp['short']." (A)";}}}?></td>
			
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
                        <div id="playername"><?php $player8name=$player->getPlayerbyID($roster['player8']); echo $player8name['playername'];?> </div>
                        <div class="lh-1 fw-light fst-italic" id="team"><?php $team8=$player->getPlayerteam($roster['player8']); echo $team8['name'];?> </div>
                    </div>
                </div>
			</td>
			<td> <?php $value8=$player->getPrice($roster['player8'],$week); if(isset($value8['price'])){echo $value8['price'];}else{echo " - ";};?></td>
			<td> <?php $p8games=$player->getPlayedgames($roster['player8']); if($p8games['playedgames']==0){echo 0;}else{echo $p8games['playedgames'];}?> </td>
			<td> <?php $p8total=$player->getTotalPlayerpoints($roster['player8']); if($p8games['playedgames']==0){echo 0;}else{echo $p8total['totalpoints'];}?></td>
			<td> <?php $p8weekly=$player->getWeeklyPlayerpoints($roster['player8'],$week-1); if($p8games['playedgames']==0){echo 0;}elseif($p8weekly['weekpoints']==0){echo 0;}else{echo $p8weekly['weekpoints'];}?></td>
			<td> <?php if($p8games['playedgames']==0){echo 0;}else{echo number_format($p8total['totalpoints'] / $p8games['playedgames'],1);}; ?> </td>
            <td><?php if($team8['team_id']==99 OR $week>18){echo "-";}else{$p8home=$crud->checkHometeam($team8['team_id'],$week,$competitor['league_id']); $p8opp=$crud->getNextopponent($team8['team_id'],$week,$competitor['league_id']); if(!$p8opp){echo " - ";}else{if ($p8home['num']==1){echo $p8opp['short']." (H)";}elseif($_SESSION['lang']==1){echo $p8opp['short']." (V)";}elseif($_SESSION['lang']==2){echo $p8opp['short']." (A)";}elseif($_SESSION['lang']==3){echo $p8opp['short']." (A)";}}}?></td>
			
		</tr>
	</tbody>
</table>
</div>
<div id="backbutton">
    <a class="btn btn-secondary" href="standings.php">
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
<?php }?>


<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>