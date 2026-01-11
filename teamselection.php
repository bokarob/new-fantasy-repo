<?php 
$title = "Csapatválasztás";
require_once 'includes/header.php';
require_once 'db/conn.php';
//EZT TÖRÖLD
// $_SESSION['league']=10;

//csak olyan oldalról jutunk el ide, ahol már választottunk ligát
if(!isset($_SESSION['league'])){
    echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
}

//forduló meghatározása liga alapján  
$gameweek = $crud->getGameweek($_SESSION['league']);
$week = $gameweek['gameweek'];
$deadline=$crud->checkDeadline($_SESSION['league'], $week);
$open=$gameweek['open'];
if(!isset($_SESSION['profile_id']) OR $week>=18) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';


switch($_SESSION['lang']){
    case 1:
        if($deadline['0']==0){echo '<div class="alert alert-primary text-center">Az aktuális fordulóra már nem tudsz csapatot regisztrálni. Az összeállított csapatod a következő fordulótól kezdve lesz érvényes. </div>'; $week = $week+1 ;};
    break;
    case 2:
        if($deadline['0']==0){echo '<div class="alert alert-primary text-center">You cannot register a team for the current gameweek. The team you create will be valid from the next gameweek.</div>'; $week = $week+1 ;};
    break;
    case 3:
        if($deadline['0']==0){echo '<div class="alert alert-primary text-center">Du kannst kein Team für die aktuelle Spielwoche registrieren. Das Team, das du erstellst, gilt ab der nächsten Spielwoche.</div>'; $week = $week+1 ;};
    break;
}

//megnézzük van-e már csapata
$competitorinleague=$crud->getCompetitorInLeague($_SESSION['profile_id'],$_SESSION['league']);
if($competitorinleague['count'] > 0) echo '<script type="text/javascript">location.href="myteam.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=myteam.php" /></noscript> ';

//megnézni, hogy a kiválasztott játékosok mind egy ligába tartoznak-e
for ($x = 1; $x <= 8; $x++){
    $play="player" . $x;
    if(isset($_SESSION[$play])){
        $team1=$player->getPlayerteam($_SESSION[$play]);
        if($team1['league_id']<>$_SESSION['league']){
            unset($_SESSION[$play]);
        }
    }
}

$teamlist=$crud->getTeamsinLeague($_SESSION['league']);

// mivel akkor csinálunk neki competitor_id-t, mikor elmentjük a csapatot, nem tudjuk onnan venni a creditet
$fullcredit=80;

$player1 = $player->getPlayer($_SESSION['league']);
$player2 = $player->getPlayer($_SESSION['league']);
$player3 = $player->getPlayer($_SESSION['league']);
$player4 = $player->getPlayer($_SESSION['league']);
$player5 = $player->getPlayer($_SESSION['league']);
$player6 = $player->getPlayer($_SESSION['league']);
$player7 = $player->getPlayer($_SESSION['league']);
$player8 = $player->getPlayer($_SESSION['league']);

if(isset($_POST['veglegesites'])){
    $value1=$player->getPrice($_SESSION['player1'],$week);
    $value2=$player->getPrice($_SESSION['player2'],$week);
    $value3=$player->getPrice($_SESSION['player3'],$week);
    $value4=$player->getPrice($_SESSION['player4'],$week);
    $value5=$player->getPrice($_SESSION['player5'],$week);
    $value6=$player->getPrice($_SESSION['player6'],$week);
    $value7=$player->getPrice($_SESSION['player7'],$week);
    $value8=$player->getPrice($_SESSION['player8'],$week);

    $team1=$player->getPlayerteam($_SESSION['player1']);
    $team2=$player->getPlayerteam($_SESSION['player2']);
    $team3=$player->getPlayerteam($_SESSION['player3']);
    $team4=$player->getPlayerteam($_SESSION['player4']);
    $team5=$player->getPlayerteam($_SESSION['player5']);
    $team6=$player->getPlayerteam($_SESSION['player6']);
    $team7=$player->getPlayerteam($_SESSION['player7']);
    $team8=$player->getPlayerteam($_SESSION['player8']);

    $valuetotal = 0;
    $valuetotal = $value1['price'] + $value2['price'] + $value3['price'] + $value4['price'] + $value5['price'] + $value6['price'] + $value7['price'] + $value8['price'];

    $playeridcheck=array($_SESSION['player1'],$_SESSION['player2'],$_SESSION['player3'],$_SESSION['player4'],$_SESSION['player5'],$_SESSION['player6'],$_SESSION['player7'],$_SESSION['player8']);
    $unique= checkunique($playeridcheck);

    $teamcheck=array($team1['name'],$team2['name'],$team3['name'],$team4['name'],$team5['name'],$team6['name'],$team7['name'],$team8['name']);
    
    if($valuetotal <= $fullcredit AND $unique == 1 AND max(array_count_values($teamcheck)) <= 2){
        $remainingcredit=$fullcredit-$valuetotal;
        $newcompetitor=$crud->insertCompetitor($_SESSION['profile_id'],$_POST['teamname'],$remainingcredit,$_SESSION['league']);

        if($newcompetitor){
            $teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
        

            $competitor_id = $teamrequest['competitor_id'];
            // $newname = $_POST['teamname'];
            $p1=$_SESSION['player1'];
            $p2=$_SESSION['player2'];
            $p3=$_SESSION['player3'];
            $p4=$_SESSION['player4'];
            $p5=$_SESSION['player5'];
            $p6=$_SESSION['player6'];
            $p7=$_SESSION['player7'];
            $p8=$_SESSION['player8'];
            

            // if($deadline['0']==0){$week = $week+1 ;};

            $newroster=$crud->insertRosterwithCap($competitor_id,$week,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8,$p1);
            // $newcredit=$crud->updateCredits($competitor_id,$remainingcredit);
            // $newname=$crud->updateTeamname($competitor_id,$newname);

            if($newroster){
                unset($_SESSION['player1']);
                unset($_SESSION['player2']);
                unset($_SESSION['player3']);
                unset($_SESSION['player4']);
                unset($_SESSION['player5']);
                unset($_SESSION['player6']);
                unset($_SESSION['player7']);
                unset($_SESSION['player8']);
                $_SESSION['competitor_id']=$competitor_id;
                
                //megnézni hogy második profilja-e
                // $compcount=$crud->getCompetitorCount($_SESSION['profile_id']);
                // if($compcount['count']==2){
                //     $newpic=$crud->newExtraPicture($_SESSION['profile_id'],59,$week);
                //     if($newpic){ 
                //         $newnotification=$crud->newPictureNotification('A1',$_SESSION['profile_id'],$week,59);
                //     }
                // }

                //megnézni hogy az első25ben regisztrált-e
                // $allcompcount=$crud->countAllCompetitor();
                // if($allcompcount['count']<=25){
                //     $newpic=$crud->newExtraPicture($_SESSION['profile_id'],57,$week);
                //     if($newpic){ 
                //     $newnotification=$crud->newPictureNotification('A1',$_SESSION['profile_id'],$week,57);
                //     }
                // }

                unset($_SESSION['teamfilter']);
                unset($_SESSION['pricefilter']);

                echo '<script type="text/javascript">location.href="favorite-team-selection.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=favorite-team-selection.php" /></noscript> ';
            }else{}
        }    
    }
    elseif($valuetotal > $fullcredit){ 
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-danger text-center">Túl sok pénzt költöttél! </div>';
            break;
            case 2: echo '<div class="alert alert-danger text-center">You spent more than your budget! </div>';
            break;
            case 3: echo '<div class="alert alert-danger text-center">Du hast mehr ausgegeben als dein Budget! </div>';
            break;
        }
    }
    elseif(!isset($_SESSION['player1']) OR !isset($_SESSION['player2']) OR !isset($_SESSION['player3']) OR !isset($_SESSION['player4']) OR !isset($_SESSION['player5']) OR !isset($_SESSION['player6']) OR !isset($_SESSION['player7']) OR !isset($_SESSION['player8'])){
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-danger text-center">Válaszd ki nyolc játékost mielőtt véglegesíted a csapatodat! </div>';
            break;
            case 2: echo '<div class="alert alert-danger text-center">Select all 8 players before you finalize your team! </div>';
            break;
            case 3: echo '<div class="alert alert-danger text-center">Wähle alle 8 Spieler aus, bevor du dein Team abschließt! </div>';
            break;
        }
    }
    elseif(!$unique){ 
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-danger text-center">Valakit kétszer is választottál!</div>';
            break;
            case 2: echo '<div class="alert alert-danger text-center">You selected someone twice!</div>';
            break;
            case 3: echo '<div class="alert alert-danger text-center">Du hast jemanden doppelt ausgewählt!</div>';
            break;
        }
    }
    elseif(max(array_count_values($teamcheck)) > 2){ 
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-danger text-center">Egy csapatból maximum 2 játékost választhatsz!</div>';
            break;
            case 2: echo '<div class="alert alert-danger text-center">You can select maximum 2 players from each team!</div>';
            break;
            case 3: echo '<div class="alert alert-danger text-center">Du kannst maximal 2 Spieler aus jeder Mannschaft auswählen!</div>';
            break;
        }
    }


}
if(isset($_POST['add'])){
    $transform = explode('_',$_POST['add']);
    $playertoadd=end($transform);
    if(!isset($_SESSION['player1'])){
        $_SESSION['player1']=$playertoadd;
    }elseif(!isset($_SESSION['player2'])){
        $_SESSION['player2']=$playertoadd;
    }elseif(!isset($_SESSION['player3'])){
        $_SESSION['player3']=$playertoadd;
    }elseif(!isset($_SESSION['player4'])){
        $_SESSION['player4']=$playertoadd;
    }elseif(!isset($_SESSION['player5'])){
        $_SESSION['player5']=$playertoadd;
    }elseif(!isset($_SESSION['player6'])){
        $_SESSION['player6']=$playertoadd;
    }elseif(!isset($_SESSION['player7'])){
        $_SESSION['player7']=$playertoadd;
    }elseif(!isset($_SESSION['player8'])){
        $_SESSION['player8']=$playertoadd;
    }
}

if(isset($_POST['sell'])){
    $transform = explode('_',$_POST['sell']);
    $playertosell=end($transform);
    switch($playertosell){
        case 1: unset($_SESSION['player1']); break;
        case 2: unset($_SESSION['player2']); break;
        case 3: unset($_SESSION['player3']); break;
        case 4: unset($_SESSION['player4']); break;
        case 5: unset($_SESSION['player5']); break;
        case 6: unset($_SESSION['player6']); break;
        case 7: unset($_SESSION['player7']); break;
        case 8: unset($_SESSION['player8']); break;
    }
}

if(isset($_POST['teamfilter'])){
    $_SESSION['teamfilter']=$_POST['teamfilter'];
}
if(isset($_POST['pricefilter'])){
    $_SESSION['pricefilter']=$_POST['pricefilter'];
}



?>

<style>
    #teamselect{
        padding:10px
    }
    #teamnamebox{
        margin-top: 2rem;
        margin-bottom: 2rem;
    }
    #teamnamebox > h5{
        margin:0;
    }
    #teamname{
        max-width: 600px;
        font-weight: bold;
        font-size:large;
    }
    #selection{
        display:flex;
    }
    #selection > div{
        flex-basis:50%;
    }
    #myteam{
        width: 600px;
        margin-bottom: 2rem;
    }
    #market{
        max-width: 600px;
        border: 1px solid grey;
        border-radius: 8px;
        padding: 10px;
        margin-left: 50px;
        box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 6px, rgba(0, 0, 0, 0.23) 0px 3px 6px;
        background-color: #EFF7FF;
    }
    .player{
        display:flex;
        gap:10px;
        margin-top:1rem;
        align-items: center;
    }

    .player .logo{
        max-width: 24px;
        
    }
    .player .tname{
        flex-basis:235px;
    }
    .player .pname{
        flex-basis:152px;
    }
    .player .price{
        flex-basis:41px;
    }

    

    .marketplayer{
        display:flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom:5px;
    }
    #finalize{
        display:flex;
        justify-content: space-between;
        margin-top:1.5rem;
        width: 600px;
    }
    #budgetleft{
        display:flex;
        justify-content: flex-end;
        align-items: center;
        gap:15px;
        margin-right:25px;
    }

    #budgetleft > h4{
        margin:0;
        font-size: 26px;
    }

    #veglegesites{
        background-color: #36A9AE;
        background-image: linear-gradient(#37ADB2, #329CA0);
        border: 1px solid #2A8387;
        border-radius: 4px;
        box-shadow: rgba(0, 0, 0, 0.12) 0 1px 1px;
        color: #FFFFFF;
        cursor: pointer;        
        font-size: 17px;
        line-height: 100%;
        padding: 11px 15px 12px;
        text-align: center;
    }
    #veglegesites:disabled {
        cursor: not-allowed;
        opacity: .6;
    }

    .player > button{
        background-color: #FFE7E7;
        background-position: 0 0;
        border: 1px solid #FEE0E0;
        border-radius: 8px;
        box-sizing: border-box;
        color: #D33A2C;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 12px;
        text-align: left;
        text-decoration: none;
        text-underline-offset: 1px;
        white-space: nowrap;
    }

    .addbutton{
        background-color: #05516F;
        border: 1px solid #05516F;
        border-radius: 4px;
        box-shadow: rgba(0, 0, 0, .1) 0 2px 4px 0;
        box-sizing: border-box;
        color: #fff;
        font-size: 12px;
        font-weight: 400;
        padding: 5px 15px;
        text-align: center;
    }

    @media (min-width: 1300px){
        #myteam{
            padding-left: 2rem;
        }
        
    }


    @media (max-width: 1000px) {
        #selection{
            flex-direction: column;
            justify-content:flex-start;
            align-items: center;
        }
        #myteam{
            min-width:fit-content;
            width: 100%;
            max-width:600px;
        }
        #finalize{
            width:100%
        }
        #market{
            max-width:100%;
            border: 0;
            margin-left: 0;
        }
        #veglegesites{
            font-size: 14px;
            padding: 10px 5px;
        }
        #budgetleft > h4{
            font-size: 20px;
        }
        #budgetleft{
            gap:10px;
            margin-right:10px;
        }
        .player .tname{
            display:none;
        }
        .player .pname{
            flex-basis:230px;
        }
    }
    @media (max-width: 380px) {
        #budgetleft > span{
            font-size: 10px;
        }
        #budgetleft > h4{
            font-size: 18px;
        }
        #budgetleft{
            gap:10px;
            margin-right:5px;
        }
        .marketteam{
            font-size:10px;
            font-style: italic;
        }
        #market{
            padding-left: 5px;
        }
    }
    
</style>

<h2 style="text-align:center">
    <?php switch($_SESSION['lang']){
        case 1: echo "Csapatválasztás";
        break;
        case 2: echo "Team selection";
        break;
        case 3: echo "Teamauswahl";
        break;
    }?>
</h2>

<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="teamselect" id="teamselect">
    <div id="teamnamebox">
        <!-- <h5>Csapatnév:</h5> -->
        <input required type="text" class="form-control" id="teamname" name="teamname" value="<?php if(isset($_POST['teamname'])){ echo $_POST['teamname'];}else{};?>" <?php if(!isset($_POST['teamname'])){ switch($_SESSION['lang']){
        case 1: echo 'placeholder="Csapatnév"';
        break;
        case 2: echo 'placeholder="Team name"';
        break;
        case 3: echo 'placeholder="Teamname"';
        break;
    }};?>>
    </div>

    <div id="selection">
    <div id="myteam">
        <h4>
            <?php switch($_SESSION['lang']){
                case 1: echo "Csapatösszeállítás";
                break;
                case 2: echo "Team line-up";
                break;
                case 3: echo "Teamaufstellung";
                break;
            }?>
        </h4>
        <div class="player" id="p1">
            <span>1.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player1'])){$team1=$player->getPlayerteam($_SESSION['player1']);?>
                    <img src="img/teamlogo/<?php echo $team1['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player1'])){$player1name=$player->getPlayerbyID($_SESSION['player1']); echo $player1name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player1']))echo $team1['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player1'])){$value1=$player->getPrice($_SESSION['player1'],$week); echo $value1['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player1'])){ ?>
            <button type="submit" name="sell" value="sell_1">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Mégse";
                    break;
                    case 2: echo "Cancel";
                    break;
                    case 3: echo "Abbrechen";
                    break;
                }?>
            </button>
            <?php } ?>
        </div>
        <div class="player" id="p2">
            <span>2.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player2'])){$team2=$player->getPlayerteam($_SESSION['player2']);?>
                    <img src="img/teamlogo/<?php echo $team2['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player2'])){$player2name=$player->getPlayerbyID($_SESSION['player2']); echo $player2name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player2']))echo $team2['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player2'])){$value2=$player->getPrice($_SESSION['player2'],$week); echo $value2['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player2'])){ ?>
            <button type="submit" name="sell" value="sell_2">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Mégse";
                    break;
                    case 2: echo "Cancel";
                    break;
                    case 3: echo "Abbrechen";
                    break;
                }?>
            </button>
            <?php } ?>
        </div>
        <div class="player" id="p3">
            <span>3.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player3'])){$team3=$player->getPlayerteam($_SESSION['player3']);?>
                    <img src="img/teamlogo/<?php echo $team3['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player3'])){$player3name=$player->getPlayerbyID($_SESSION['player3']); echo $player3name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player3']))echo $team3['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player3'])){$value3=$player->getPrice($_SESSION['player3'],$week); echo $value3['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player3'])){ ?>
            <button type="submit" name="sell" value="sell_3">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Mégse";
                    break;
                    case 2: echo "Cancel";
                    break;
                    case 3: echo "Abbrechen";
                    break;
                }?>
            </button>
            <?php } ?>
        </div>
        <div class="player" id="p4">
            <span>4.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player4'])){$team4=$player->getPlayerteam($_SESSION['player4']);?>
                    <img src="img/teamlogo/<?php echo $team4['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player4'])){$player4name=$player->getPlayerbyID($_SESSION['player4']); echo $player4name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player4']))echo $team4['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player4'])){$value4=$player->getPrice($_SESSION['player4'],$week); echo $value4['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player4'])){ ?>
            <button type="submit" name="sell" value="sell_4">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Mégse";
                    break;
                    case 2: echo "Cancel";
                    break;
                    case 3: echo "Abbrechen";
                    break;
                }?>
            </button>
            <?php } ?>
        </div>
        <div class="player" id="p5">
            <span>5.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player5'])){$team5=$player->getPlayerteam($_SESSION['player5']);?>
                    <img src="img/teamlogo/<?php echo $team5['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player5'])){$player5name=$player->getPlayerbyID($_SESSION['player5']); echo $player5name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player5']))echo $team5['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player5'])){$value5=$player->getPrice($_SESSION['player5'],$week); echo $value5['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player5'])){ ?>
            <button type="submit" name="sell" value="sell_5">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Mégse";
                    break;
                    case 2: echo "Cancel";
                    break;
                    case 3: echo "Abbrechen";
                    break;
                }?>
            </button>
            <?php } ?>
        </div>
        <div class="player" id="p6">
            <span>6.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player6'])){$team6=$player->getPlayerteam($_SESSION['player6']);?>
                    <img src="img/teamlogo/<?php echo $team6['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player6'])){$player6name=$player->getPlayerbyID($_SESSION['player6']); echo $player6name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player6']))echo $team6['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player6'])){$value6=$player->getPrice($_SESSION['player6'],$week); echo $value6['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player6'])){ ?>
            <button type="submit" name="sell" value="sell_6">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Mégse";
                    break;
                    case 2: echo "Cancel";
                    break;
                    case 3: echo "Abbrechen";
                    break;
                }?>
            </button>
            <?php } ?>
        </div>
        <div class="player" id="p7">
            <span>7.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player7'])){$team7=$player->getPlayerteam($_SESSION['player7']);?>
                    <img src="img/teamlogo/<?php echo $team7['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player7'])){$player7name=$player->getPlayerbyID($_SESSION['player7']); echo $player7name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player7']))echo $team7['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player7'])){$value7=$player->getPrice($_SESSION['player7'],$week); echo $value7['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player7'])){ ?>
            <button type="submit" name="sell" value="sell_7">
                <?php switch($_SESSION['lang']){
                    case 1: echo "Mégse";
                    break;
                    case 2: echo "Cancel";
                    break;
                    case 3: echo "Abbrechen";
                    break;
                }?>
            </button>
            <?php } ?>
        </div>
        <div class="player" id="p8">
            <span>8.</span>
            <div class="logo">
                <?php if(isset($_SESSION['player8'])){$team8=$player->getPlayerteam($_SESSION['player8']);?>
                    <img src="img/teamlogo/<?php echo $team8['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                <?php } ?>
            </div>
            <div class="pname">
                <?php if(isset($_SESSION['player8'])){$player8name=$player->getPlayerbyID($_SESSION['player8']); echo $player8name['playername'];} ?>
            </div>
            <div class="tname">
                <?php if(isset($_SESSION['player8']))echo $team8['name'];?>
            </div>
            <div class="price">
                <?php if(isset($_SESSION['player8'])){$value8=$player->getPrice($_SESSION['player8'],$week); echo $value8['price'] . 'M';}?>
            </div>
            <?php if(isset($_SESSION['player8'])){ ?>
                <button type="submit" name="sell" value="sell_8">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Mégse";
                        break;
                        case 2: echo "Cancel";
                        break;
                        case 3: echo "Abbrechen";
                        break;
                    }?>
                </button>
            <?php } ?>
        </div>
        <div id="finalize">
            <button type="submit" name="veglegesites" id="veglegesites" <?php if(!isset($_SESSION['player1']) OR !isset($_SESSION['player2']) OR !isset($_SESSION['player3']) OR !isset($_SESSION['player4']) OR !isset($_SESSION['player5']) OR !isset($_SESSION['player6']) OR !isset($_SESSION['player7']) OR !isset($_SESSION['player8'])){echo "disabled";}?>>
                <?php switch($_SESSION['lang']){
                    case 1: echo "Csapat véglegesítése";
                    break;
                    case 2: echo "Finalize team";
                    break;
                    case 3: echo "Team bestätigen";
                    break;
                }?>
            </button>
            <div id="budgetleft">            
                <?php 
                    $valuetotal = 0;
                    if(isset($_SESSION['player1'])){ $valuetotal = $valuetotal + $value1['price'];};
                    if(isset($_SESSION['player2'])){ $valuetotal = $valuetotal + $value2['price'];};
                    if(isset($_SESSION['player3'])){ $valuetotal = $valuetotal + $value3['price'];};
                    if(isset($_SESSION['player4'])){ $valuetotal = $valuetotal + $value4['price'];};
                    if(isset($_SESSION['player5'])){ $valuetotal = $valuetotal + $value5['price'];};
                    if(isset($_SESSION['player6'])){ $valuetotal = $valuetotal + $value6['price'];};
                    if(isset($_SESSION['player7'])){ $valuetotal = $valuetotal + $value7['price'];};
                    if(isset($_SESSION['player8'])){ $valuetotal = $valuetotal + $value8['price'];};

                ?>
                <span>
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Megmaradt összeg: ";
                        break;
                        case 2: echo "Remaining budget: ";
                        break;
                        case 3: echo "Verbleibendes Budget: ";
                        break;
                    }?>
                </span>
                <h4 <?php if($valuetotal <=$fullcredit){echo 'class="value good"';}else{echo 'class="value bad"';}; ?>><?php echo number_format($fullcredit-$valuetotal,1); ?> M</h4>
            </div>
        </div>

    </div>

    <div id="market">
        <h4>
            <?php switch($_SESSION['lang']){
                case 1: echo "Elérhető játékosok";
                break;
                case 2: echo "Available players";
                break;
                case 3: echo "Verfügbare Spieler";
                break;
            }?>
        </h4>
        <!-- Add these two selects at the top of #market, before the player list -->
        <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem;">
            <!-- Team filter -->
            <div>
                <label for="teamfilter">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Csapat szűrés"; break;
                        case 2: echo "Team filter"; break;
                        case 3: echo "Team-Filter"; break;
                    }?>
                </label>
                <select id="teamfilter" name="teamfilter" style="min-width:120px;" onchange="this.form.submit()">
                    <option value="0" <?php if(!isset($_SESSION['teamfilter']) || $_SESSION['teamfilter']==0) echo 'selected'; ?>>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Összes csapat"; break;
                            case 2: echo "All teams"; break;
                            case 3: echo "Alle Teams"; break;
                        }?>
                    </option>
                    <?php while($team = $teamlist->fetch(PDO::FETCH_ASSOC)){ ?>
                        <option value="<?php echo $team['team_id']; ?>" <?php if(isset($_SESSION['teamfilter']) && $_SESSION['teamfilter']==$team['team_id']) echo 'selected'; ?>>
                            <?php echo $team['name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <!-- Price filter -->
            <div>
                <label for="pricefilter">
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Max ár"; break;
                        case 2: echo "Max price"; break;
                        case 3: echo "Max Preis"; break;
                    }?>
                </label>
                <select id="pricefilter" name="pricefilter" style="min-width:80px;" onchange="this.form.submit()">
                    <option value="0" <?php if(!isset($_SESSION['pricefilter']) || $_SESSION['pricefilter']==0) echo 'selected'; ?>>
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Összes ár"; break;
                            case 2: echo "All prices"; break;
                            case 3: echo "Alle Preise"; break;
                        }?>
                    </option>
                    <?php for($i=6; $i<=18; $i++){ ?>
                        <option value="<?php echo $i; ?>" <?php if(isset($_SESSION['pricefilter']) && $_SESSION['pricefilter']==$i) echo 'selected'; ?>>
                            <?php echo $i; ?>M
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div id="playerlist">
            <?php while($r = $player1->fetch(PDO::FETCH_ASSOC)){ ?>
                <?php if((!isset($_SESSION['player1']) OR $r['player_id'] <> $_SESSION['player1']) AND (!isset($_SESSION['player2']) OR $r['player_id'] <> $_SESSION['player2']) AND (!isset($_SESSION['player3']) OR $r['player_id'] <> $_SESSION['player3']) AND (!isset($_SESSION['player4']) OR $r['player_id'] <> $_SESSION['player4']) AND (!isset($_SESSION['player5']) OR $r['player_id'] <> $_SESSION['player5']) AND (!isset($_SESSION['player6']) OR $r['player_id'] <> $_SESSION['player6']) AND (!isset($_SESSION['player7']) OR $r['player_id'] <> $_SESSION['player7']) AND (!isset($_SESSION['player8']) OR $r['player_id'] <> $_SESSION['player8'])){ ?>
                    <?php 
                        $r_team=$player->getPlayerteam($r['player_id']);
                        if((isset($_SESSION['teamfilter']) && $_SESSION['teamfilter'] != 0 && $r_team['team_id'] != $_SESSION['teamfilter']) OR (isset($_SESSION['pricefilter']) && $_SESSION['pricefilter'] != 0 && $r['price'] > $_SESSION['pricefilter'])){

                        }else{;
                    ?>
                    <div class="marketplayer">
                        
                        <img src="img/teamlogo/<?php echo $r_team['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                        <span><?php echo $r['playername']?></span>
                        <!-- <span> - </span>
                        <span class="marketteam"><?php $teamtoselect=$player->getPlayerteam($r['player_id']);echo $teamtoselect['short']?></span> -->
                        <span> - </span>
                        <span <?php if(($fullcredit-$valuetotal)<$r['price']){echo 'style=color:grey';}?>><?php echo $r['price']?>M</span>
                        <button type="submit" name="add" class="addbutton" value="add_<?php echo $r['player_id'];?>">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Kiválaszt";
                                break;
                                case 2: echo "Select";
                                break;
                                case 3: echo "Auswählen";
                                break;
                            }?>
                        </button>
                    </div>
                <?php } ?>
                <?php } ?>
                <?php } ?>
        </div>
    </div>
    </div>
</form>




<?php
function checkunique(array $input_array) {
    return count($input_array) === count(array_flip($input_array));
}
?>

<script src="scroll.js"></script>

<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>