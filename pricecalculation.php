<?php 
$title = "Admin";
require_once 'db/conn.php';
require_once 'includes/header.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//megnézni melyik hét van melyik bajnokságban
$hugameweek = $crud->getGameweek(10);
$huweek = $hugameweek['gameweek'];
$hudeadline=$crud->checkDeadline(10,$huweek);
$huopen=$hugameweek['open'];

$degameweek = $crud->getGameweek(20);
$deweek = $degameweek['gameweek'];
$dedeadline=$crud->checkDeadline(20,$deweek);
$deopen=$degameweek['open'];

$deWgameweek = $crud->getGameweek(40);
$deWweek = $deWgameweek['gameweek'];
$deWdeadline=$crud->checkDeadline(40,$deWweek);
$deWopen=$deWgameweek['open'];


//jogosultság check
if(isset($_SESSION['profile_id']) AND $_SESSION['authorization'] ==3){echo "Minden OK";}else{ echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';};

//hét és liga beállítása
if(isset($_POST['setup'])){
    $_SESSION['checkweek']=$_POST['week'];
    $_SESSION['league']=$_POST['league'];
}

if(isset($_SESSION['checkweek']) AND isset($_SESSION['league'])){  
    $playerlist=$player->getPlayerByWeek($_SESSION['league'], $_SESSION['checkweek']);
}else{
    $playerlist=$player->getPlayerByWeek(10, $huweek);
}

//egyes játékosok árának újraszámítása
if (isset($_POST['calc'])){
    $playerid=$_POST['calc'];

    $enterpoints=$admin->updatePlayerPrice($playerid,$_SESSION['checkweek']+1,$_POST["calcprice_".$playerid]);
}

//minden játékos árának újraszámítása - NINCS BEFEJEZVE
if (isset($_POST['calcAll'])){
    while($r = $playerlist->fetch(PDO::FETCH_ASSOC)){
        //getting results
        $weeklyresult=$player->getPlayerresultForWeek($r['player_id'],$_SESSION['checkweek']);
        // setting base according to league
        switch($_SESSION['league']){
            case 10:
                $basepin=530;
                $toppin=660;
                break;
            case 20:
                $basepin=550;
                $toppin=680;
                break;
            case 40:
                $basepin=525;
                $toppin=655;
                break;
        }
        //price if no result or substituted
        if(!$weeklyresult OR $weeklyresult['substituted']==1){
            if($r['price']>=10){
                $newprice=$r['price'] - 0.1;
            }else{
            $newprice=$r['price'];
            }
            $pinprice="-";
        }else{
            //calculating price for the week's result
            if($weeklyresult['pins']<$basepin){
                $pinprice=5;
            }elseif($weeklyresult['pins']>=$basepin AND $weeklyresult['pins']<=$toppin){
                $pinprice=5 + (($weeklyresult['pins'] - $basepin) / 10);
            }elseif($weeklyresult['pins']>$toppin){
                $pinprice=18;
            }

            //calculating new price
            if((($r['price']*6+$pinprice)/7)-$r['price']>0.5){
                $newprice=$r['price'] + 0.5;
            }elseif((($r['price']*6+$pinprice)/7)-$r['price']<-0.5){
                $newprice=$r['price'] - 0.5;
            }else{
                $newprice=round(($r['price']*6+$pinprice)/7,1);
            }
        }

        $enterpoints=$admin->updatePlayerPrice($r['player_id'],$_SESSION['checkweek']+1,$newprice);
    }
}

?>

<style>
    table, th, td {
        border: 1px solid black;
        
    }
</style>

<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="pointcalc">
    <p>Forduló:</p>
    <input type="text" class="form-control" id="week" name="week" style="border-color:darkblue; width:unset;" <?php if(isset($_SESSION['checkweek'])){echo 'value="'.$_SESSION['checkweek'].'"';}else echo 'value="'.$huweek.'"';?>>
    <input type="text" class="form-control" id="league" name="league" style="border-color:darkblue; width:unset;" <?php if(isset($_SESSION['league'])){echo 'value="'.$_SESSION['league'].'"';}else echo 'value=10';?>>
    <button type="submit" class="btn btn-primary" name="setup" value="setup">mehet</button>
</form>
<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="calcAll">
    <button type="submit" class="btn btn-success" style="float:right" name="calcAll" value="calcAll">Minden ár számítása</button>
</form>
<hr><hr>
<table>
    <thead>
        <tr>
            <th>Player_id</th>
            <th>Name</th>
            <th>team</th>
            <th>Current PRICE</th>
            <th>result</th>
            <th>subs</th>
            <th>match avg</th>
            <th>MP</th>
            <th>weekly price</th>
            <th>NEW price</th>
            <th>change</th>
            <th>beírt ár</th>
            <th></th>
            <th>változás</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if(isset($playerlist)){
            foreach($playerlist as $p){ 
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="pricecalc_<?php echo $p['player_id'];?>">
        <tr>
            <td><?php echo $p['player_id']; ?></td>
            <td><?php echo $p['playername']; ?></td>
            <td><?php $team=$player->getPlayerteam($p['player_id']); echo $team['short'] ?></td>
            <td><?php echo $p['price']; ?></td>
            <td><?php $weeklyresult=$player->getPlayerresultForWeek($p['player_id'],$_SESSION['checkweek']); echo $weeklyresult['pins'] ?? "-"; ?></td>
            <td><?php if($weeklyresult){echo $weeklyresult['substituted'];}?></td>
            <td style="font-size: 14px; font-style: italic; color: gray;"><?php if($weeklyresult){$matchavg=$player->getMatchAvg($weeklyresult['match_id']); echo number_format($matchavg['avg'] ?? 0,1);} ?></td>
            <td style="font-size: 14px; font-style: italic; color: gray;"><?php $weeklyMP=$player->getWeeklyPlayerpoints($p['player_id'],$_SESSION['checkweek']); echo $weeklyMP['MP']; ?></td>
            <td>
                <?php 
                    switch($_SESSION['league']){
                        case 10:
                            $basepin=530;
                            $toppin=660;
                            break;
                        case 20:
                            $basepin=550;
                            $toppin=680;
                            break;
                        case 40:
                            $basepin=525;
                            $toppin=655;
                            break;
                    }

                    if(!$weeklyresult OR $weeklyresult['substituted']==1){
                        if($p['price']>=10){
                            $newprice=$p['price'] - 0.1;
                        }else{
                        $newprice=$p['price'];
                        }
                        $pinprice="-";
                    }else{
                        //calculating price for the week's result
                        if($weeklyresult['pins']<$basepin){
                            $pinprice=5;
                        }elseif($weeklyresult['pins']>=$basepin AND $weeklyresult['pins']<=$toppin){
                            $pinprice=5 + (($weeklyresult['pins'] - $basepin) / 10);
                        }elseif($weeklyresult['pins']>$toppin){
                            $pinprice=18;
                        }

                        //calculating new price
                        if((($p['price']*6+$pinprice)/7)-$p['price']>0.5){
                            $newprice=$p['price'] + 0.5;
                        }elseif((($p['price']*6+$pinprice)/7)-$p['price']<-0.5){
                            $newprice=$p['price'] - 0.5;
                        }else{
                            $newprice=round(($p['price']*6+$pinprice)/7,1);
                        }
                    }
                    echo $pinprice ?? "-";
                ?>
            </td>
            <td><input type="text" class="form-control" name="calcprice_<?php echo $p['player_id'];?>" value="<?php echo round($newprice , 3);?>"  style="font-weight: bold;"></td>
            <td><?php echo round($newprice-$p['price'] , 1); ?></td>
            <td>
                <?php 
                $nextprice=$player->getPrice($p['player_id'],$_SESSION['checkweek']+1);
                echo $nextprice['price'] ?? "-";
                ?>
            </td>
            <td>
                <button type="submit" class="btn btn-<?php if(abs($nextprice['price']-$newprice)<0.05){echo 'secondary';}else{echo 'primary';} ?>" name="calc" value="<?php echo $p['player_id'];?>" >Újraszámítás</button>
            </td>
            <td>
                <?php echo round($nextprice['price']-$p['price'] , 1); ?>
            </td>
        </tr>    
        </form>
        <?php 
        }
        } 
        ?>
        
        
        
    </tbody>
</table>


<script src="scroll.js"></script>