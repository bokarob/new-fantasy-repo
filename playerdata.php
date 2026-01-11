<?php 
require_once 'db/conn.php';
$player_id = (int)$_GET['id'];
$playercheck=$player->getPlayerbyID($player_id);
$title = $playercheck['playername'];
require_once 'includes/header.php';

$gameweek = $crud->getGameweek($playercheck['league_id']);
$week = $gameweek['gameweek'];

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if ( !empty($player_id) && $player_id > 10000) {
    // Fecth data
    $playerbasedata=$player->getPlayerteam($player_id);
    $price=$player->getPrice($player_id,$week);
    $mainstats=$player->getMainstats($player_id);
    $homestats=$player->getHomestats($player_id);
    $awaystats=$player->getAwaystats($player_id);
    $row1stats=$player->get1rowstats($player_id);
    $row2stats=$player->get2rowstats($player_id);
    $row3stats=$player->get3rowstats($player_id);
    $matchlist=$player->getAllMatchesforPlayer($player_id);
    $maintotal=$player->getMainTotalPt($player_id);
    $hometotal=$player->getHomeTotalPt($player_id);
    $awaytotal=$player->getAwayTotalPt($player_id);
    $row1total=$player->get1rowTotalPt($player_id);
    $row2total=$player->get2rowTotalPt($player_id);
    $row3total=$player->get3rowTotalPt($player_id);
    $lastprice=$player->getPrice($player_id,$week-1);
    $selections=$player->getRosterSelections($player_id,$week-1);
    $allrosters=$player->countAllRosterInleague($playercheck['league_id'],$week-1);
}else{
    $roster = false;
    echo '<script type="text/javascript">location.href="statistics.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=statistics.php" /></noscript> ';
}


?>

<style>
    #playerdata{
        max-width: 700px;
        margin:auto;
        border: 1px solid darkgray;
        padding:0.5rem;
    }
    #headerdiv{
        display: flex;
        gap:10px;
        align-items: center;
    }
    #logobox{
        flex:1;
    }
    #logobox img{
        max-width:100%;
        max-height: 100%;
    }
    #namebox{
        flex:4;
        padding-left:1rem;
    }
    #pricebox{
        flex:1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    #pricebox h2{
        display: flex;
    }

    #pricebox p{
        font-size: 10px;
        line-height: normal;
        margin-bottom: 0.2rem;
        padding: 0;
    }
    #pricebox h5{
        font-size: 16px;
    }

    #mainstats{
        display:flex;
        margin-top:1rem;
        border-top: 1px solid grey;
        text-align: center;
    }

    #mainstats i{
        font-size: 11pt;
    }
    #mainstats > table{
        width:100%;
        font-size:18px;
    }
    #mainstats th,#mainstats td{
        width:25%;
        padding-top:0.8rem;
    }

    #detailedstats{
        display:flex;
        margin-top:1.2rem;
        padding-top:0.5rem;
        border-top: 1px solid grey;
    }
    #detailedstats > *{
        flex-basis: 100%;
    }
    #detailedstats table{
        width:100%;
    }
    #detailedstats td,#detailedstats th{
        padding-top:0.6rem;
    }
    #detailedstats span{
        padding-left:1rem;
    }

    #detailedstats i{
        font-size: 9pt;
    }

    #homeaway{
        border-right: none;
        width: 100%;
    }
    #homeaway td,#homeaway th{
        width:calc(100%/3);
        text-align: center;
    }

    #matches{
        margin-top:1rem;
        padding-top:0.5rem;
        border-top: 1px solid grey;
    }
    #matches table{
        width:100%;
        text-align:center;
        margin-top:1rem;
    }
    #matches td, #matches th{
        padding-top:0.8rem;
        border: 1px solid grey;
    }
    #matches span{
        padding-left:1rem;
    }

    @media (max-width:700px){
        #detailedstats{
            flex-direction:column;
        }
        #detailedstats > *{
            flex-basis: 100%;
        }
        #homeaway{
            border-bottom: 1px solid grey;
            border-right: 0;
            padding-bottom:1rem;
            padding-top:1rem;
            width: 100%;
        }
        
    }

    
</style>


<div id="playerdata">
    <div id="headerdiv">
        <div id="logobox">
            <img src="img/teamlogo/<?php echo $playerbasedata['logo'];?>" alt="" >
        </div>
        <div id="namebox">
            <div id="playernamebox">
                <h2><?php echo $playerbasedata['playername'];?></h2>
            </div>
            <div id="teamnamebox">
                <h6 style="font-style:italic"><?php echo $playerbasedata['name'];?></h6>
            </div>
        </div>
        <div id="pricebox">
            <div>
                <h2 style="font-style:italic"><?php echo $price['price']."M"; if($lastprice AND $lastprice['price']<$price['price']){echo '<i class="bi bi-arrow-up text-success"></i>';}elseif($lastprice AND $lastprice['price']>$price['price']){echo '<i class="bi bi-arrow-down text-danger"></i>';}?></h2>
            </div>
            <div>
                <p>Team selection</p>
                <h5 style="font-style:italic"><?php if($selections and $allrosters){$selected=$selections['occurrence_count']/$allrosters['allroster']*100; echo number_format($selected,0).' %';}else{echo "-";}?></h5>
            </div>
            
        </div>
    </div>

    <div id="mainstats">
        <table>
            <tr>
                <?php switch($_SESSION['lang']){
                    case 1:
                    ?>
                    <th>Meccsek</th>
                    <th>Átlag</th>
                    <th>Összes pont</th>
                    <th>Átlag pont</th>
                    <?php ;
                    break;

                    case 2:
                    ?>
                    <th>Games</th>
                    <th>Avg pins</th>
                    <th>Total points</th>
                    <th>Avg points</th>
                    <?php ;
                    break;

                    case 3:
                        ?>
                        <th>Sp.</th>
                        <th>Schnitt</th>
                        <th>Gesamtpkt.</th>
                        <th>∅ Pkt</th>
                        <?php ;
                    break;
                }?>
                
            </tr>
            <tr>
                <td><?php if($maintotal['subs']==0){echo number_format($maintotal['matches'],0);}else{echo number_format($maintotal['matches'],0) . '<i>(' . number_format($maintotal['subs'],0) . ')</i>';}?></td>
                <td><?php echo number_format($mainstats['pinavg'],1);?></td>
                <td><?php echo number_format($maintotal['pointsum'],1);?></td>
                <td><?php echo number_format($mainstats['pointavg'],1);?></td>
            </tr>
        </table>
    </div>

    <div id="detailedstats">
        <div id="homeaway">
            <span style="font-style:italic">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Hazai / Idegen statisztikák";
                    break;
                    case 2: echo "Home / Away statistics";
                    break;
                    case 3: echo "Heim- / Auswärtsstatistiken";
                    break;
                }?>
            </span>
            <table>
                <tr>
                    <th></th>
                    <th>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Hazai";
                            break;
                            case 2: echo "Home";
                            break;
                            case 3: echo "Heim";
                            break;
                        }?>
                    </th>
                    <th>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Idegen";
                            break;
                            case 2: echo "Away";
                            break;
                            case 3: echo "Auswärts";
                            break;
                        }?>
                    </th>
                </tr>
                <tr>
                    <th>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Meccsek";
                            break;
                            case 2: echo "Matches";
                            break;
                            case 3: echo "Spiele";
                            break;
                        }?>
                    </th>
                    <td><?php if($hometotal['matches']==0){echo " - ";}else{if($hometotal['subs']==0){echo number_format($hometotal['matches'],0);}else{echo number_format($hometotal['matches'],0) . '<i>(' . number_format($hometotal['subs'],0) . ')</i>';}}?></td>
                    <td><?php if($awaytotal['matches']==0){echo " - ";}else{if($awaytotal['subs']==0){echo number_format($awaytotal['matches'],0);}else{echo number_format($awaytotal['matches'],0) . '<i>(' . number_format($awaytotal['subs'],0) . ')</i>';}}?></td>
                </tr>
                <tr>
                    <th>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Átlag";
                            break;
                            case 2: echo "Avg pins";
                            break;
                            case 3: echo "∅ Kegel";
                            break;
                        }?>
                    </th>
                    <td><?php if($homestats['matches']==0){echo " - ";}else{echo number_format($homestats['pinavg'],1);}?></td>
                    <td><?php if($awaystats['matches']==0){echo " - ";}else{echo number_format($awaystats['pinavg'],1);}?></td>
                </tr>
                <tr>
                    <th>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Összes pont";
                            break;
                            case 2: echo "Total points";
                            break;
                            case 3: echo "Ges.pkt";
                            break;
                        }?>
                    </th>
                    <td><?php if($hometotal['matches']==0){echo " - ";}else{echo number_format($hometotal['pointsum'],1);}?></td>
                    <td><?php if($awaytotal['matches']==0){echo " - ";}else{echo number_format($awaytotal['pointsum'],1);}?></td>
                </tr>
                <tr>
                    <th>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Átlag pont";
                            break;
                            case 2: echo "Avg points";
                            break;
                            case 3: echo "∅ Pkt";
                            break;
                        }?>
                    </th>
                    <td><?php if($homestats['matches']==0){echo " - ";}else{echo number_format($homestats['pointavg'],1);}?></td>
                    <td><?php if($awaystats['matches']==0){echo " - ";}else{echo number_format($awaystats['pointavg'],1);}?></td>
                </tr>
            </table>
        </div>

    </div>

    <div id="matches">
        <span style="font-style:italic">
            <?php switch($_SESSION['lang']){
                case 1: echo "Mérkőzésenkénti lebontás";
                break;
                case 2: echo "Results by games";
                break;
                case 3: echo "Ergebnisse nach Spiel";
                break;
            }?>
        </span>
        <table class="table table-hover">
            <tr>
                <?php switch($_SESSION['lang']){
                    case 1:
                    ?>
                    <th><abbr title="Forduló">For.</th>
                    <th><abbr title="Hazai/Vendég">H/V</th>
                    <th>Ellenfél</th>
                    <th>Ütött Fa</th>
                    <th><abbr title="Szettpont">SzP</th>
                    <th><abbr title="Csapatpont">CsP</th>
                    <th><abbr title="Fantasy pontok">Pont</th>
                    <th style="text-align:left">Ellenfél játékos</th>
                    <th><abbr title="Ellenfél eredmény">Ef.e.</th>
                    <?php ;
                    break;

                    case 2:
                    ?>
                    <th><abbr title="Gameweek">GW.</th>
                    <th><abbr title="Home/Away">H/A</th>
                    <th>Versus</th>
                    <th>Pins</th>
                    <th><abbr title="Set points">SP</th>
                    <th><abbr title="Team point">TP</th>
                    <th><abbr title="Fantasy points earned">Points</th>
                    <th style="text-align:left">Opponent player</th>
                    <th><abbr title="Opponent player's result">Opp.r.</th>
                    <?php ;
                    break;

                    case 3:
                        ?>
                        <th><abbr title="Spielwoche">SW.</th>
                        <th><abbr title="Heim / Auswärts">H/A</th>
                        <th>Gegner</th>
                        <th>Kegel</th>
                        <th>SP</th>
                        <th>MP</th>
                        <th><abbr title="Fantasy-Punkte">Punkte</th>
                        <th style="text-align:left">Gegenspieler</th>
                        <th><abbr title="Ergebnis des Gegners">Erg.G.</th>
                        <?php ;
                    break;
                }?>
                
            </tr>
            <?php while($r = $matchlist->fetch(PDO::FETCH_ASSOC)){ ?>
                <tr>
                    <td><?php if($r['substituted']==0){echo $r['gameweek'];}else{echo $r['gameweek'] . '*';}?></td>
                    <td><?php if($r['homegame']==1){echo "H";}elseif($_SESSION['lang']==1){echo "V";}elseif($_SESSION['lang']==2){echo "A";}elseif($_SESSION['lang']==3){echo "A";} //itt van nyelvi feltétel?></td>
                    <td><?php $team=$player->getPlayerteam($r['opponent_id']); echo $team['short']?></td>
                    <td><?php echo $r['pins']?></td>
                    <td><?php echo $r['setpoints']?></td>
                    <td><?php echo $r['matchpoints']?></td>
                    <td style="border-right:1px solid grey; font-weight:bold"><?php echo $r['points']?></td>
                    <td style="font-style:italic;text-align:left"><?php $opp=$player->getPlayerbyID($r['opponent_id']); echo $opp['playername']; $oppcheck=$player->getPlayerresultForWeek($r['opponent_id'],$r['gameweek']); if($oppcheck['substituted']==1) echo "*";?></td>
                    <td style="font-style:italic"><?php echo $r['opponent_result']?></td>
                </tr>
            <?php }?>
        </table>
    </div>
</div>






<script src="scroll.js"></script>
<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>