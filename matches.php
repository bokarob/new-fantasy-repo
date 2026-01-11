<?php 
$title = "Meccsek";
require_once 'includes/header.php';
require_once 'db/conn.php';
require_once 'db/player.php';

//magyar liga
$hugameweek = $crud->getGameweek(10);
$huweek = $hugameweek['gameweek'];
if($huweek>18){$huweek=18;};

if(!isset($_SESSION['huweekshow'])){
    $humatches=$crud->getMatches($huweek,10);
    $_SESSION['huweekshow']=$huweek;
}else{
    if(isset($_POST['prvweekhu'])){$_SESSION['huweekshow']=$_SESSION['huweekshow']-1;};
    if(isset($_POST['nextweekhu'])){$_SESSION['huweekshow']=$_SESSION['huweekshow']+1;};
    if(isset($_POST['currweekhu'])){$_SESSION['huweekshow']=$huweek;};
    $humatches=$crud->getMatches($_SESSION['huweekshow'],10);
};
if($_SERVER['REQUEST_METHOD'] == 'POST') echo '<script type="text/javascript">location.href="redirect.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=redirect.php" /></noscript> ';


$showgameweekhu=$crud->checkgameweek($_SESSION['huweekshow'],10);

$hugamedate=explode("-", $showgameweekhu['gamedate']);
switch($_SESSION['lang']){
    case 1: $honapok = Array( "", "január" , "február"  , "március"   ,"április", "május"    , "június"    ,"július" , "augusztus", "szeptember","október", "november" , "december"    );
    break;
    case 2: $honapok = Array( "", "January" , "February"  , "March"   ,"April", "May"    , "June"    ,"July" , "August", "September","October", "November" , "December"    );
    break;
    case 3: $honapok = Array( "", "Januar" , "Februar"  , "März"   ,"April", "Mai"    , "Juni"    ,"Juli" , "August", "September","Oktober", "November" , "Dezember"    );
    break;
}

//német liga

$degameweek = $crud->getGameweek(20);
$deweek = $degameweek['gameweek'];
if($deweek>18){$deweek=18;};

if(!isset($_SESSION['deweekshow'])){
    $dematches=$crud->getMatches($deweek,20);
    $_SESSION['deweekshow']=$deweek;
}else{
    if(isset($_POST['prvweekde'])){$_SESSION['deweekshow']=$_SESSION['deweekshow']-1;};
    if(isset($_POST['nextweekde'])){$_SESSION['deweekshow']=$_SESSION['deweekshow']+1;};
    if(isset($_POST['currweekde'])){$_SESSION['deweekshow']=$deweek;};
    $dematches=$crud->getMatches($_SESSION['deweekshow'],20);
};
if($_SERVER['REQUEST_METHOD'] == 'POST') echo '<script type="text/javascript">location.href="redirect.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=redirect.php" /></noscript> ';


$showgameweekde=$crud->checkgameweek($_SESSION['deweekshow'],20);

$degamedate=explode("-", $showgameweekde['gamedate']);
switch($_SESSION['lang']){
    case 1: $honapok = Array( "", "január" , "február"  , "március"   ,"április", "május"    , "június"    ,"július" , "augusztus", "szeptember","október", "november" , "december"    );
    break;
    case 2: $honapok = Array( "", "January" , "February"  , "March"   ,"April", "May"    , "June"    ,"July" , "August", "September","October", "November" , "December"    );
    break;
    case 3: $honapok = Array( "", "Januar" , "Februar"  , "März"   ,"April", "Mai"    , "Juni"    ,"Juli" , "August", "September","Oktober", "November" , "Dezember"    );
    break;
}

//német női liga

$dewgameweek = $crud->getGameweek(40);
$dewweek = $dewgameweek['gameweek'];
if($dewweek>18){$dewweek=18;};

if(!isset($_SESSION['dewweekshow'])){
    $dewmatches=$crud->getMatches($dewweek,40);
    $_SESSION['dewweekshow']=$dewweek;
}else{
    if(isset($_POST['prvweekdew'])){$_SESSION['dewweekshow']=$_SESSION['dewweekshow']-1;};
    if(isset($_POST['nextweekdew'])){$_SESSION['dewweekshow']=$_SESSION['dewweekshow']+1;};
    if(isset($_POST['currweekdew'])){$_SESSION['dewweekshow']=$dewweek;};
    $dewmatches=$crud->getMatches($_SESSION['dewweekshow'],40);
};
if($_SERVER['REQUEST_METHOD'] == 'POST') echo '<script type="text/javascript">location.href="redirect.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=redirect.php" /></noscript> ';


$showgameweekdew=$crud->checkgameweek($_SESSION['dewweekshow'],40);

$dewgamedate=explode("-", $showgameweekdew['gamedate']);
switch($_SESSION['lang']){
    case 1: $honapok = Array( "", "január" , "február"  , "március"   ,"április", "május"    , "június"    ,"július" , "augusztus", "szeptember","október", "november" , "december"    );
    break;
    case 2: $honapok = Array( "", "January" , "February"  , "March"   ,"April", "May"    , "June"    ,"July" , "August", "September","October", "November" , "December"    );
    break;
    case 3: $honapok = Array( "", "Januar" , "Februar"  , "März"   ,"April", "Mai"    , "Juni"    ,"Juli" , "August", "September","Oktober", "November" , "Dezember"    );
    break;
}

?>



<style>
    h2{
        text-align: center; 
        margin-top: 4vh; 
        margin-bottom: 5vh;
    }
    h4{
        text-align: center;
        font-weight: bold;
        margin-bottom: 1rem;
    }
    h5{
        text-align: center; 
        margin-bottom: 3vh;
    }
    .matchtable{
        text-align: center;
        margin-left:auto; 
        margin-right:auto;
        border-color: #dee2e6;
        margin-bottom: 3rem;
    }
    .matchtable tr{
        border-style: solid;
        border-width: 1px;
        border-left: none;
        border-right: none;
        border-top: none;
    }
    .matchtable th, .matchtable td {
        font-size: 0.85rem;
        padding: 0.3rem 0.4rem;
    }
    .accordion-body {
        padding: 0.5rem 0.5rem;
    }
    .accordion-button {
        font-size: 1rem;
        padding: 0.4rem 0.8rem;
    }
    .home{
        width: 40%;
    }
    .away{
        width:40%;
    }
    .buttonrow{
        display: flex;
        flex-direction: row;
        justify-content: center;
        gap: 1rem;
    }
    .weekbutton{
        margin:5px;
        max-width: calc(33% - 5px);
        box-sizing: border-box;
    }

    .weekbutton button{
        height: 100%;
    }

    .fixturesall{
        display:flex;
        
        justify-content: center;
        gap: 1.5rem;
    }
    
    .leaguefixtures{
        flex:1;
        max-width:500px;
    }

    @media (max-width:450px){
        .home{
            width: 35%;
        }
        .away{
            width:35%;
        }
        .fixturesall{
            flex-direction: column;
        }
    }
</style>

<h2>
    <?php switch($_SESSION['lang']){
        case 1: echo "Mérkőzések:";
        break;
        case 2: echo "Fixtures:";
        break;
        case 3: echo "Spielplan:";
        break;
    }?> 
</h2>

<div class="fixturesall">
    <div class="leaguefixtures">
        <h4>Szuperliga</h4>
        <h5>
            <?php switch($_SESSION['lang']){
                case 1: echo $_SESSION['huweekshow'] . ". forduló - " . $hugamedate[0].". ".$honapok[number_format($hugamedate[1],)]." ".$hugamedate[2]."." ;
                break;
                case 2: 
                    $newdate=date('jS F Y', strtotime($showgameweekhu['gamedate']));
                    echo "Gameweek " . $_SESSION['huweekshow'] . " - " . $newdate ;
                break;
                case 3: echo $_SESSION['huweekshow'] . ". Spielwoche - " . $hugamedate[2].". ".$honapok[number_format($hugamedate[1],)]." ".$hugamedate[0]."." ;
                break;
            }?> 
        </h5>
        <div class="accordion" id="huMatchesAccordion">
        <?php
        $matchIndex = 0;
        while($r = $humatches->fetch(PDO::FETCH_ASSOC)){
            $hometeam = $crud->teamname($r['hometeam']);
            $awayteam = $crud->teamname($r['awayteam']);
            $matchId = $r['match_id'];
            $results = $player->getResultsbyMatchID($matchId);
            
            // Group results by row and side
            $rows = [];
            foreach ($results as $pr) {
                $side = $pr['homegame'] ? 'home' : 'away';
                $row = $pr['row'];
                $rows[$row][$side][] = $pr;
            }

            // Calculate team points (allow decimals)
            $homeMPsum = 0.0;
            $awayMPsum = 0.0;
            $homePinsum = 0;
            $awayPinsum = 0;
            foreach ($rows as $rowdata) {
                if (isset($rowdata['home'])) {
                    foreach ($rowdata['home'] as $hp) {
                        $homeMPsum += floatval($hp['matchpoints']);
                        $homePinsum += intval($hp['pins']);
                    }
                }
                if (isset($rowdata['away'])) {
                    foreach ($rowdata['away'] as $ap) {
                        $awayMPsum += floatval($ap['matchpoints']);
                        $awayPinsum += intval($ap['pins']);
                    }
                }
            }
            // Pin bonus
            if ($homePinsum > $awayPinsum) {
                $homeMPsum += 2;
            }elseif($homePinsum==0){
                $homeMPsum = 0;
                $awayMPsum = 0;
            }elseif ($homePinsum < $awayPinsum) {
                $awayMPsum += 2;
            } else {
                $homeMPsum += 1;
                $awayMPsum += 1;
            }
            
            
            $resultDisplay = $homeMPsum . " - " . $awayMPsum;
            $showTotalPins = $homePinsum+$awayPinsum > 0;
        ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="huheading<?= $matchIndex ?>">
                    <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#hucollapse<?= $matchIndex ?>"
                        aria-expanded="false"
                        aria-controls="hucollapse<?= $matchIndex ?>">
                        <div style="display: flex; width: 100%; align-items: center;">
                            <div style="flex: 1; display: flex; align-items: center; justify-content: flex-start;">
                                <img src="img/teamlogo/<?= $hometeam['logo'] ?>" alt="" style="height:24px; margin-right:1vw;">
                                <span><?= $hometeam['name'] ?></span>
                            </div>
                            <div style="flex: 0 0 80px; text-align: center;">
                                <?= $resultDisplay ?>
                            </div>
                            <div style="flex: 1; display: flex; align-items: center; justify-content: flex-end;">
                                <span><?= $awayteam['name'] ?></span>
                                <img src="img/teamlogo/<?= $awayteam['logo'] ?>" alt="" style="height:24px; margin-left:1vw;">
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="hucollapse<?= $matchIndex ?>" class="accordion-collapse collapse" aria-labelledby="huheading<?= $matchIndex ?>">
                    <div class="accordion-body">
                        <table class="matchtable" style="text-align:center;">
                            <thead>
                                <tr>
                                    <th> </th>
                                    <th> </th>
                                    <th>SP</th>
                                    <th>MP</th>
                                    <th style="width:20px;"></th>
                                    <th>MP</th>
                                    <th>SP</th>
                                    <th> </th>
                                    <th> </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $rownum => $rowdata):
                                $homePlayers = isset($rowdata['home']) ? $rowdata['home'] : [];
                                $awayPlayers = isset($rowdata['away']) ? $rowdata['away'] : [];
                                $homeCell = '';
                                $homePins = '';
                                $homeSet = '';
                                $homeMP = '';
                                foreach ($homePlayers as $hp) {
                                    $homeCell .= ($homeCell !== '' ? '<br>' : '') . $hp['playername'] . ($hp['substituted'] ? ' <span style="color:red;">*</span>' : '');
                                    $homePins .= ($homePins !== '' ? '<br>' : '') . $hp['pins'];
                                    $homeSet .= ($homeSet !== '' ? '<br>' : '') . $hp['setpoints'];
                                    // Only show MP if not zero
                                    $homeMP .= ($homeMP !== '' ? '<br>' : '') . ($hp['matchpoints'] != 0 ? $hp['matchpoints'] : '');
                                }
                                $awayCell = '';
                                $awayPins = '';
                                $awaySet = '';
                                $awayMP = '';
                                foreach ($awayPlayers as $ap) {
                                    $awayCell .= ($awayCell !== '' ? '<br>' : '') . $ap['playername'] . ($ap['substituted'] ? ' <span style="color:red;">*</span>' : '');
                                    $awayPins .= ($awayPins !== '' ? '<br>' : '') . $ap['pins'];
                                    $awaySet .= ($awaySet !== '' ? '<br>' : '') . $ap['setpoints'];
                                    // Only show MP if not zero
                                    $awayMP .= ($awayMP !== '' ? '<br>' : '') . ($ap['matchpoints'] != 0 ? $ap['matchpoints'] : '');
                                }
                            ?>
                                <tr style="vertical-align:middle;">
                                    <td><?= $homeCell ?></td>
                                    <td><?= $homePins ?></td>
                                    <td><?= $homeSet ?></td>
                                    <td><?= $homeMP ?></td>
                                    <td style="background:#f8f9fa;"></td>
                                    <td><?= $awayMP ?></td>
                                    <td><?= $awaySet ?></td>
                                    <td><?= $awayPins ?></td>
                                    <td><?= $awayCell ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($homePinsum+$awayPinsum > 0): ?>
                                <tr style="font-weight:bold;">
                                    <td></td>
                                    <td><?= $homePinsum ?></td>
                                    <td></td>
                                    <td></td>
                                    <td style="background:#f8f9fa;"></td>
                                    <td></td>
                                    <td></td>
                                    <td><?= $awayPinsum ?></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php $matchIndex++; } ?>
        </div>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="weekchange">
            <div class="buttonrow">
                <div class="weekbutton">
                    <button type="submit" class="btn btn-info" name="prvweekhu" value="prvweekhu" <?php if($_SESSION['huweekshow']==1){echo "disabled";}?>>
                        <?php  echo '<i class="bi bi-chevron-double-left"></i>';  ?>
                    </button>
                </div>
                <div class="weekbutton">
                    <button type="submit" class="btn btn-outline-secondary" name="currweekhu" value="currweekhu">
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Vissza az aktuális hétre";
                            break;
                            case 2: echo "Back to current week";
                            break;
                            case 3: echo "Zurück zur aktuellen Woche";
                            break;
                        }?>
                    </button>
                </div>
                <div class="weekbutton">
                    <button type="submit" class="btn btn-info" name="nextweekhu" value="nextweekhu" <?php if($_SESSION['huweekshow']==18){echo "disabled";}?>>
                        <?php  echo '<i class="bi bi-chevron-double-right"></i>';   ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="leaguefixtures">
        <h4>Bundesliga Men</h4>
        <h5>
            <?php switch($_SESSION['lang']){
                case 1: echo $_SESSION['deweekshow'] . ". forduló - " . $degamedate[0].". ".$honapok[number_format($degamedate[1],)]." ".$degamedate[2]."." ;
                break;
                case 2: 
                    $newdate=date('jS F Y', strtotime($showgameweekde['gamedate']));
                    echo "Gameweek " . $_SESSION['deweekshow'] . " - " . $newdate ;
                break;
                case 3: echo $_SESSION['deweekshow'] . ". Spielwoche - " . $degamedate[2].". ".$honapok[number_format($degamedate[1],)]." ".$degamedate[0]."." ;
                break;
            }?> 
        </h5>
        <div class="accordion" id="deMatchesAccordion">
        <?php
        $matchIndex = 0;
        while($r = $dematches->fetch(PDO::FETCH_ASSOC)){
            $hometeam = $crud->teamname($r['hometeam']);
            $awayteam = $crud->teamname($r['awayteam']);
            $matchId = $r['match_id'];
            $results = $player->getResultsbyMatchID($matchId);

            $rows = [];
            foreach ($results as $pr) {
                $side = $pr['homegame'] ? 'home' : 'away';
                $row = $pr['row'];
                $rows[$row][$side][] = $pr;
            }

            $homeMPsum = 0;
            $awayMPsum = 0;
            $homePinsum = 0;
            $awayPinsum = 0;
            foreach ($rows as $rowdata) {
                if (isset($rowdata['home'])) {
                    foreach ($rowdata['home'] as $hp) {
                        $homeMPsum += floatval($hp['matchpoints']);
                        $homePinsum += intval($hp['pins']);
                    }
                }
                if (isset($rowdata['away'])) {
                    foreach ($rowdata['away'] as $ap) {
                        $awayMPsum += floatval($ap['matchpoints']);
                        $awayPinsum += intval($ap['pins']);
                    }
                }
            }
            if ($homePinsum > $awayPinsum) {
                $homeMPsum += 2;
            }elseif($homePinsum==0){
                $homeMPsum = 0;
                $awayMPsum = 0;
            }elseif ($homePinsum < $awayPinsum) {
                $awayMPsum += 2;
            } else {
                $homeMPsum += 1;
                $awayMPsum += 1;
            }
            $resultDisplay = $homeMPsum . " - " . $awayMPsum;
        ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="deheading<?= $matchIndex ?>">
                    <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#decollapse<?= $matchIndex ?>"
                        aria-expanded="false"
                        aria-controls="decollapse<?= $matchIndex ?>">
                        <div style="display: flex; width: 100%; align-items: center;">
                            <div style="flex: 1; display: flex; align-items: center; justify-content: flex-start;">
                                <img src="img/teamlogo/<?= $hometeam['logo'] ?>" alt="" style="height:24px; margin-right:1vw;">
                                <span><?= $hometeam['name'] ?></span>
                            </div>
                            <div style="flex: 0 0 80px; text-align: center;">
                                <?= $resultDisplay ?>
                            </div>
                            <div style="flex: 1; display: flex; align-items: center; justify-content: flex-end;">
                                <span><?= $awayteam['name'] ?></span>
                                <img src="img/teamlogo/<?= $awayteam['logo'] ?>" alt="" style="height:24px; margin-left:1vw;">
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="decollapse<?= $matchIndex ?>" class="accordion-collapse collapse" aria-labelledby="deheading<?= $matchIndex ?>">
                    <div class="accordion-body">
                        <table class="matchtable" style="text-align:center;">
                            <thead>
                                <tr>
                                    <th> </th>
                                    <th> </th>
                                    <th>SP</th>
                                    <th>MP</th>
                                    <th style="width:20px;"></th>
                                    <th>MP</th>
                                    <th>SP</th>
                                    <th> </th>
                                    <th> </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $rownum => $rowdata):
                                $homePlayers = isset($rowdata['home']) ? $rowdata['home'] : [];
                                $awayPlayers = isset($rowdata['away']) ? $rowdata['away'] : [];
                                $homeCell = '';
                                $homePins = '';
                                $homeSet = '';
                                $homeMP = '';
                                foreach ($homePlayers as $hp) {
                                    $homeCell .= ($homeCell !== '' ? '<br>' : '') . $hp['playername'] . ($hp['substituted'] ? ' <span style="color:red;">*</span>' : '');
                                    $homePins .= ($homePins !== '' ? '<br>' : '') . $hp['pins'];
                                    $homeSet .= ($homeSet !== '' ? '<br>' : '') . $hp['setpoints'];
                                    // Only show MP if not zero
                                    $homeMP .= ($homeMP !== '' ? '<br>' : '') . ($hp['matchpoints'] != 0 ? $hp['matchpoints'] : '');
                                }
                                $awayCell = '';
                                $awayPins = '';
                                $awaySet = '';
                                $awayMP = '';
                                foreach ($awayPlayers as $ap) {
                                    $awayCell .= ($awayCell !== '' ? '<br>' : '') . $ap['playername'] . ($ap['substituted'] ? ' <span style="color:red;">*</span>' : '');
                                    $awayPins .= ($awayPins !== '' ? '<br>' : '') . $ap['pins'];
                                    $awaySet .= ($awaySet !== '' ? '<br>' : '') . $ap['setpoints'];
                                    // Only show MP if not zero
                                    $awayMP .= ($awayMP !== '' ? '<br>' : '') . ($ap['matchpoints'] != 0 ? $ap['matchpoints'] : '');
                                }
                            ?>
                                <tr style="vertical-align:middle;">
                                    <td><?= $homeCell ?></td>
                                    <td><?= $homePins ?></td>
                                    <td><?= $homeSet ?></td>
                                    <td><?= $homeMP ?></td>
                                    <td style="background:#f8f9fa;"></td>
                                    <td><?= $awayMP ?></td>
                                    <td><?= $awaySet ?></td>
                                    <td><?= $awayPins ?></td>
                                    <td><?= $awayCell ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($homePinsum+$awayPinsum > 0): ?>
                                <tr style="font-weight:bold;">
                                    <td></td>
                                    <td><?= $homePinsum ?></td>
                                    <td></td>
                                    <td></td>
                                    <td style="background:#f8f9fa;"></td>
                                    <td></td>
                                    <td></td>
                                    <td><?= $awayPinsum ?></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php $matchIndex++; } ?>
        </div>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="weekchange">
            <div class="buttonrow">
                <div class="weekbutton">
                    <button type="submit" class="btn btn-info" name="prvweekde" value="prvweekde" <?php if($_SESSION['deweekshow']==1){echo "disabled";}?>>
                        <?php  echo '<i class="bi bi-chevron-double-left"></i>';  ?>
                    </button>
                </div>
                <div class="weekbutton">
                    <button type="submit" class="btn btn-outline-secondary" name="currweekde" value="currweekde">
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Vissza az aktuális hétre";
                            break;
                            case 2: echo "Back to current week";
                            break;
                            case 3: echo "Zurück zur aktuellen Woche";
                            break;
                        }?>
                    </button>
                </div>
                <div class="weekbutton">
                    <button type="submit" class="btn btn-info" name="nextweekde" value="nextweekde" <?php if($_SESSION['deweekshow']==18){echo "disabled";}?>>
                        <?php  echo '<i class="bi bi-chevron-double-right"></i>';   ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="leaguefixtures">
        <h4>Bundesliga Women</h4>
        <h5>
            <?php switch($_SESSION['lang']){
                case 1: echo $_SESSION['dewweekshow'] . ". forduló - " . $dewgamedate[0].". ".$honapok[number_format($dewgamedate[1],)]." ".$dewgamedate[2]."." ;
                break;
                case 2: 
                    $newdate=date('jS F Y', strtotime($showgameweekdew['gamedate']));
                    echo "Gameweek " . $_SESSION['dewweekshow'] . " - " . $newdate ;
                break;
                case 3: echo $_SESSION['dewweekshow'] . ". Spielwoche - " . $dewgamedate[2].". ".$honapok[number_format($dewgamedate[1],)]." ".$dewgamedate[0]."." ;
                break;
            }?> 
        </h5>
        <div class="accordion" id="dewMatchesAccordion">
        <?php
        $matchIndex = 0;
        while($r = $dewmatches->fetch(PDO::FETCH_ASSOC)){
            $hometeam = $crud->teamname($r['hometeam']);
            $awayteam = $crud->teamname($r['awayteam']);
            $matchId = $r['match_id'];
            $results = $player->getResultsbyMatchID($matchId);

            $rows = [];
            foreach ($results as $pr) {
                $side = $pr['homegame'] ? 'home' : 'away';
                $row = $pr['row'];
                $rows[$row][$side][] = $pr;
            }

            $homeMPsum = 0;
            $awayMPsum = 0;
            $homePinsum = 0;
            $awayPinsum = 0;
            foreach ($rows as $rowdata) {
                if (isset($rowdata['home'])) {
                    foreach ($rowdata['home'] as $hp) {
                        $homeMPsum += floatval($hp['matchpoints']);
                        $homePinsum += intval($hp['pins']);
                    }
                }
                if (isset($rowdata['away'])) {
                    foreach ($rowdata['away'] as $ap) {
                        $awayMPsum += floatval($ap['matchpoints']);
                        $awayPinsum += intval($ap['pins']);
                    }
                }
            }
            if ($homePinsum > $awayPinsum) {
                $homeMPsum += 2;
            }elseif($homePinsum==0){
                $homeMPsum = 0;
                $awayMPsum = 0;
            }elseif ($homePinsum < $awayPinsum) {
                $awayMPsum += 2;
            } else {
                $homeMPsum += 1;
                $awayMPsum += 1;
            }
            $resultDisplay = $homeMPsum . " - " . $awayMPsum;
        ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="dewheading<?= $matchIndex ?>">
                    <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#dewcollapse<?= $matchIndex ?>"
                        aria-expanded="false"
                        aria-controls="dewcollapse<?= $matchIndex ?>">
                        <div style="display: flex; width: 100%; align-items: center;">
                            <div style="flex: 1; display: flex; align-items: center; justify-content: flex-start;">
                                <img src="img/teamlogo/<?= $hometeam['logo'] ?>" alt="" style="height:24px; margin-right:1vw;">
                                <span><?= $hometeam['name'] ?></span>
                            </div>
                            <div style="flex: 0 0 80px; text-align: center;">
                                <?= $resultDisplay ?>
                            </div>
                            <div style="flex: 1; display: flex; align-items: center; justify-content: flex-end;">
                                <span><?= $awayteam['name'] ?></span>
                                <img src="img/teamlogo/<?= $awayteam['logo'] ?>" alt="" style="height:24px; margin-left:1vw;">
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="dewcollapse<?= $matchIndex ?>" class="accordion-collapse collapse" aria-labelledby="dewheading<?= $matchIndex ?>">
                    <div class="accordion-body">
                        <table class="matchtable" style="text-align:center;">
                            <thead>
                                <tr>
                                    <th> </th>
                                    <th> </th>
                                    <th>SP</th>
                                    <th>MP</th>
                                    <th style="width:20px;"></th>
                                    <th>MP</th>
                                    <th>SP</th>
                                    <th> </th>
                                    <th> </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $rownum => $rowdata):
                                $homePlayers = isset($rowdata['home']) ? $rowdata['home'] : [];
                                $awayPlayers = isset($rowdata['away']) ? $rowdata['away'] : [];
                                $homeCell = '';
                                $homePins = '';
                                $homeSet = '';
                                $homeMP = '';
                                foreach ($homePlayers as $hp) {
                                    $homeCell .= ($homeCell !== '' ? '<br>' : '') . $hp['playername'] . ($hp['substituted'] ? ' <span style="color:red;">*</span>' : '');
                                    $homePins .= ($homePins !== '' ? '<br>' : '') . $hp['pins'];
                                    $homeSet .= ($homeSet !== '' ? '<br>' : '') . $hp['setpoints'];
                                    // Only show MP if not zero
                                    $homeMP .= ($homeMP !== '' ? '<br>' : '') . ($hp['matchpoints'] != 0 ? $hp['matchpoints'] : '');
                                }
                                $awayCell = '';
                                $awayPins = '';
                                $awaySet = '';
                                $awayMP = '';
                                foreach ($awayPlayers as $ap) {
                                    $awayCell .= ($awayCell !== '' ? '<br>' : '') . $ap['playername'] . ($ap['substituted'] ? ' <span style="color:red;">*</span>' : '');
                                    $awayPins .= ($awayPins !== '' ? '<br>' : '') . $ap['pins'];
                                    $awaySet .= ($awaySet !== '' ? '<br>' : '') . $ap['setpoints'];
                                    // Only show MP if not zero
                                    $awayMP .= ($awayMP !== '' ? '<br>' : '') . ($ap['matchpoints'] != 0 ? $ap['matchpoints'] : '');
                                }
                            ?>
                                <tr style="vertical-align:middle;">
                                    <td><?= $homeCell ?></td>
                                    <td><?= $homePins ?></td>
                                    <td><?= $homeSet ?></td>
                                    <td><?= $homeMP ?></td>
                                    <td style="background:#f8f9fa;"></td>
                                    <td><?= $awayMP ?></td>
                                    <td><?= $awaySet ?></td>
                                    <td><?= $awayPins ?></td>
                                    <td><?= $awayCell ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($homePinsum+$awayPinsum > 0): ?>
                                <tr style="font-weight:bold;">
                                    <td></td>
                                    <td><?= $homePinsum ?></td>
                                    <td></td>
                                    <td></td>
                                    <td style="background:#f8f9fa;"></td>
                                    <td></td>
                                    <td></td>
                                    <td><?= $awayPinsum ?></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php $matchIndex++; } ?>
        </div>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="weekchange">
            <div class="buttonrow">
                <div class="weekbutton">
                    <button type="submit" class="btn btn-info" name="prvweekdew" value="prvweekdew" <?php if($_SESSION['dewweekshow']==1){echo "disabled";}?>>
                        <?php  echo '<i class="bi bi-chevron-double-left"></i>';  ?>
                    </button>
                </div>
                <div class="weekbutton">
                    <button type="submit" class="btn btn-outline-secondary" name="currweekdew" value="currweekdew">
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Vissza az aktuális hétre";
                            break;
                            case 2: echo "Back to current week";
                            break;
                            case 3: echo "Zurück zur aktuellen Woche";
                            break;
                        }?>
                    </button>
                </div>
                <div class="weekbutton">
                    <button type="submit" class="btn btn-info" name="nextweekdew" value="nextweekdew" <?php if($_SESSION['dewweekshow']==18){echo "disabled";}?>>
                        <?php  echo '<i class="bi bi-chevron-double-right"></i>';   ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
    
<script src="scroll.js"></script>
<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>