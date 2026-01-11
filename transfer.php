<?php 
$title = "Igazolások";
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

//ne lehessen olyan játékost adni vagy venni, aki nem a ligában van
if(isset($_SESSION['transf1']) AND $_SESSION['transf1'] !==""){
    $team1=$player->getPlayerteam($_SESSION['transf1']);
    if($team1['league_id']<>$_SESSION['league']){
        unset($_SESSION['transf1']);
    }
}
if(isset($_SESSION['transf2']) AND $_SESSION['transf2'] !==""){
    $team2=$player->getPlayerteam($_SESSION['transf2']);
    if($team2['league_id']<>$_SESSION['league']){
        unset($_SESSION['transf2']);
    }
}
if(isset($_SESSION['player1in']) AND $_SESSION['player1in'] !==""){
    $team3=$player->getPlayerteam($_SESSION['player1in']);
    if($team3['league_id']<>$_SESSION['league']){
        unset($_SESSION['player1in']);
    }
}
if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==""){
    $team4=$player->getPlayerteam($_SESSION['player2in']);
    if($team4['league_id']<>$_SESSION['league']){
        unset($_SESSION['player2in']);
    }
}


//ha választottunk ligát, akkor betöltjük az egész oldalt. Az oldal végén van else -> akkor ligaválasztó gombok
if(isset($_SESSION['league'])){

$gameweek = $crud->getGameweek($_SESSION['league']);
$week = $gameweek['gameweek'];
$deadline=$crud->checkDeadline($_SESSION['league'], $week);
$open=$gameweek['open'];

//markethez változók
$teamlist=$crud->getTeamsinLeague($_SESSION['league']);
$player1 = $player->getPlayer($_SESSION['league']);
if(isset($_POST['teamfilter'])){
    $_SESSION['teamfilter']=$_POST['teamfilter'];
}
if(isset($_POST['pricefilter'])){
    $_SESSION['pricefilter']=$_POST['pricefilter'];
}

//ha még nincs csapata, akkor átirányítjuk a csapatválasztó oldalra
$competitorinleague=$crud->getCompetitorInLeague($_SESSION['profile_id'],$_SESSION['league']);
if($competitorinleague['count'] < 1) {echo '<script type="text/javascript">location.href="teamselection.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=teamselection.php" /></noscript> ';}else{$teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']); $_SESSION['competitor_id']=$teamrequest['competitor_id'];}

$rostercheck=$crud->existRoster($_SESSION['competitor_id'],$week);
if($rostercheck['num'] == 0){
    $rostercheck=$crud->existRoster($_SESSION['competitor_id'],$week+1);
    if($rostercheck['num'] == 0){
        echo '<script type="text/javascript">location.href="myteam.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=myteam.php" /></noscript> ';
    }
}

//ha már van csapata, akkor lekérjük a csapat adatait
$teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
if($deadline['0']==0){$checkroster=$crud->getRoster($teamrequest['competitor_id'],$week); if(empty($checkroster)){$weekcheck=$week+1;}else{$weekcheck=$week;}}else{$weekcheck=$week;};

$teamname=$teamrequest['teamname'];
$remainingcredits=$teamrequest['credits'];


$roster=$crud->getRoster($teamrequest['competitor_id'],$weekcheck);

$value1=$player->getPrice($roster['player1'],$week);
$value2=$player->getPrice($roster['player2'],$week);
$value3=$player->getPrice($roster['player3'],$week);
$value4=$player->getPrice($roster['player4'],$week);
$value5=$player->getPrice($roster['player5'],$week);
$value6=$player->getPrice($roster['player6'],$week);
$value7=$player->getPrice($roster['player7'],$week);
$value8=$player->getPrice($roster['player8'],$week);

$valuetotal = 0;
$valuetotal = $value1['price'] + $value2['price'] + $value3['price'] + $value4['price'] + $value5['price'] + $value6['price'] + $value7['price'] + $value8['price'];

$transfercheck=$crud->getTransfers($teamrequest['competitor_id'],$week);


//tud-e egyáltalán igazolni, vagy lejárt a határidő, elhasználta a lehetőségeit, stb
switch($_SESSION['lang']){
    case 1:
        if($transfercheck['num']<2 AND $transfercheck['total']<2 AND $open==1 AND $deadline['0']==1){$buttondisable=false;}
        elseif($transfercheck['total']>=2){echo '<div class="alert alert-primary text-center">A játékhétre már elhasználtad a maximális számú átigazolást! A következő fordulóban tudsz legközelebb új játékost igazolni. </div>'; $buttondisable=true;} //megnézzük, hogy a játékhétre van-e már max 3 átigazolás. Ha igen, akkor nem engedünk többet (buttondisable-t használjuk)
        elseif($transfercheck['num'] >=2 AND $transfercheck['total']<3){echo '<div class="alert alert-primary text-center">A játékhétre már igazoltál 2 játékost, most már csak kiegészítő személyzetet tudsz igazolni</div>'; $buttondisable=false;} //ha már volt 2 igazolása, de nincs extra mellette, még igazolhat kiegészítő személyzetet
        elseif($open==0){echo '<div class="alert alert-primary text-center">Kis türelmet, az átigazolási piac hamarosan nyit </div>'; $buttondisable=true;}
        elseif($deadline['0']==0 AND empty($checkroster)){echo '<div class="alert alert-primary text-center">Az átigazolási kérelmeid a következő fordulóra lesznek érvényesek. </div>'; $buttondisable=false;}
        elseif($deadline['0']==0 AND !empty($checkroster)){echo '<div class="alert alert-primary text-center">Az aktuális fordulóra már nem tudsz átigazolási kérelmet leadni. </div>'; $buttondisable=true;}
        //itt nem fontos külön azt nézni, hogy valakinek van-e már eredménye, mert ha még nincs, akkor a transfercheck nem lehet 2 vagy több, mert nem készítünk új entry-t a táblában
        break;
    case 2:
        if($transfercheck['num']<2 AND $transfercheck['total']<2 AND $open==1 AND $deadline['0']==1){$buttondisable=false;}
        elseif($transfercheck['total']>=2){echo '<div class="alert alert-primary text-center">You already made the maximum number of transfers for the week. You can make new transfers in the next gameweek.</div>'; $buttondisable=true;} //megnézzük, hogy a játékhétre van-e már max 3 átigazolás. Ha igen, akkor nem engedünk többet (buttondisable-t használjuk)
        elseif($transfercheck['num'] >=2 AND $transfercheck['total']<3){echo '<div class="alert alert-primary text-center">You already made 2 transfers, you can only trade staff now.</div>'; $buttondisable=false;} //ha már volt 2 igazolása, de nincs extra mellette, még igazolhat kiegészítő személyzetet
        elseif($open==0){echo '<div class="alert alert-primary text-center">Trade market will be open soon.</div>'; $buttondisable=true;}
        elseif($deadline['0']==0 AND empty($checkroster)){echo '<div class="alert alert-primary text-center">Your transfers will be valid for the next gameweek.</div>'; $buttondisable=false;}
        elseif($deadline['0']==0 AND !empty($checkroster)){echo '<div class="alert alert-primary text-center">Trade deadline is over, you cannot make transfers for this gameweek anymore.</div>'; $buttondisable=true;}
        break;
    case 3:
        if($transfercheck['num']<2 AND $transfercheck['total']<2 AND $open==1 AND $deadline['0']==1){$buttondisable=false;}
        elseif($transfercheck['total']>=2){echo '<div class="alert alert-primary text-center">Du hast bereits die maximale Anzahl an Transfers für diese Woche vorgenommen. Du kannst in der nächsten Spielwoche neue Transfers durchführen.</div>'; $buttondisable=true;} //megnézzük, hogy a játékhétre van-e már max 3 átigazolás. Ha igen, akkor nem engedünk többet (buttondisable-t használjuk)
        elseif($transfercheck['num'] >=2 AND $transfercheck['total']<3){echo '<div class="alert alert-primary text-center">You already made 2 transfers, you can only trade staff now.</div>'; $buttondisable=false;} //ha már volt 2 igazolása, de nincs extra mellette, még igazolhat kiegészítő személyzetet
        elseif($open==0){echo '<div class="alert alert-primary text-center">Der Transfermarkt wird bald geöffnet.</div>'; $buttondisable=true;}
        elseif($deadline['0']==0 AND empty($checkroster)){echo '<div class="alert alert-primary text-center">Deine Transfers gelten für die nächste Spielwoche.</div>'; $buttondisable=false;}
        elseif($deadline['0']==0 AND !empty($checkroster)){echo '<div class="alert alert-primary text-center">Die Transferfrist ist abgelaufen, du kannst für diese Spielwoche keine Transfers mehr vornehmen.</div>'; $buttondisable=true;}
        break;
}


//ez a rész akkor kellett, amikor lehetett kiegészítő személyzetet is igazolni
// if($transfercheck['num'] >=2 AND $transfercheck['total']<3){
//     $player1 = $player->getPersonell(); 
// }else{
// $player1 = $player->getPlayer($_SESSION['league']); 
// }



//MIKOR RÁNYOMUNK AZ IGAZOLÁS VÉGLEGESÍTÉSE GOMBRA
if(isset($_POST['transfer1'])){
    //új credit kiszámolása ellenőrzéshez
    $newcredits = $remainingcredits;
    if(isset($_SESSION['transf1']) AND $_SESSION['transf1'] !==""){
        $player1outvalue=$player->getPrice($_SESSION['transf1'],$week);
        $newcredits = round($newcredits + $player1outvalue['price'],1);
    };
    if(isset($_SESSION['transf2']) AND $_SESSION['transf2'] !==""){
        $player2outvalue=$player->getPrice($_SESSION['transf2'],$week);
        $newcredits = round($newcredits + $player2outvalue['price'],1);
    };
    if(isset($_SESSION['player1in'])){
        $v1in=$player->getPrice($_SESSION['player1in'],$week); 
        $newcredits = round($newcredits - $v1in['price'],1);
    };
    if(isset($_SESSION['player2in'])){
        $v2in=$player->getPrice($_SESSION['player2in'],$week); 
        $newcredits = round($newcredits - $v2in['price'],1);
    };
    $newcredits = round($newcredits,1);

    //játékosok csapatainak kigyűjtése ellenőrzéshez, hogy max 2 játékos van minden csapatból
    $teamcheck=array();
    for($i=1; $i<=8; $i++){
        $playerId = $roster['player'.$i];
        if(isset($playerId) && $playerId !== "" 
        && (!isset($_SESSION['transf1']) OR (string)$playerId !== (string)$_SESSION['transf1'])
        && (!isset($_SESSION['transf2']) OR (string)$playerId !== (string)$_SESSION['transf2'])){
            $teamcheck[]=$player->getPlayerteam($roster['player'.$i])['name'];
        }
    }
    if(isset($_SESSION['player1in']) AND $_SESSION['player1in'] !==""){
        $teamcheck[]=$player->getPlayerteam($_SESSION['player1in'])['name'];
    }
    if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==""){
        $teamcheck[]=$player->getPlayerteam($_SESSION['player2in'])['name'];
    }

    //játékosok ellenőrzése, hogy senki nincs kétszer
    $playeridcheck=array($roster['player1'],$roster['player2'],$roster['player3'],$roster['player4'],$roster['player5'],$roster['player6'],$roster['player7'],$roster['player8'],$_SESSION['player1in']);
    if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==""){
        $playeridcheck[]=$_SESSION['player2in'];
    }
    $unique= checkunique($playeridcheck);

    //megnézni hány csere történik
    if(isset($_SESSION['transf1']) AND isset($_SESSION['player1in']) AND !isset($_SESSION['transf2']) AND !isset($_SESSION['player2in'])){
        $transfernumber=1;
    }elseif(isset($_SESSION['transf1']) AND isset($_SESSION['player1in']) AND isset($_SESSION['transf2']) AND isset($_SESSION['player2in'])){
        $transfernumber=2;
    }


    switch($_SESSION['lang']){
        case 1:
            //HA: a credit nem negatív, kettőnél több játékos nincs ugyanabból a csapatból, a játékos id-k egyediek és a cserék száma még belefér, akkor mehet
            if($newcredits >= 0 AND $unique == 1 AND (max(array_count_values($teamcheck)) <= 2) AND ((2 - $transfercheck['num'])>= $transfernumber )){

                
                $booktransfer=$crud->transferToRoster($teamrequest['competitor_id'],$weekcheck,$_SESSION['transf1'],$_SESSION['player1in']);
                if($transfernumber ==2){
                    $booktransfer2=$crud->transferToRoster($teamrequest['competitor_id'],$weekcheck,$_SESSION['transf2'],$_SESSION['player2in']);
                }
                $updatecredit=$crud->updateCredits($teamrequest['competitor_id'],$newcredits);

                $teamresults=$crud->getTeamresultcount($teamrequest['competitor_id'],$week);
                if($teamresults['0'] > 0 AND $week<>10){
                    $addnewtransfer=$crud->insertTransfer($teamrequest['competitor_id'],$week,$_SESSION['transf1'],$_SESSION['player1in']);
                    if($transfernumber ==2){
                        $addnewtransfer2=$crud->insertTransfer($teamrequest['competitor_id'],$week,$_SESSION['transf2'],$_SESSION['player2in']);
                    }
                }; //ez a check a teamresult-al azért kell, hogy ha valamelyik csapatnak még ez az első hete (ergo nincs eredménye még), akkor korlátlan számú átigazolást hajthat végre

                if($roster['captain']==$_SESSION['transf1']){$crud->updateCaptain($teamrequest['competitor_id'],$_SESSION['player1in'],$week);} 
                if($roster['captain']==$_SESSION['transf2']){$crud->updateCaptain($teamrequest['competitor_id'],$_SESSION['player2in'],$week);}
                //ha a kapitányt cseréljük, akkor itt frissítjük a kapitányt
                

                if($booktransfer AND $updatecredit){
                    //header("Location: csapatom.php");
                    unset($_SESSION['transf1']);
                    unset($_SESSION['transf2']);
                    unset($_SESSION['player1in']);
                    unset($_SESSION['player2in']);
                    
                    echo '<script type="text/javascript">location.href="transfer.php";</script>
                <noscript><meta http-equiv="refresh" content="0; URL=transfer.php" /></noscript> ';
                    
                    //ez azért kell, mert különben ha visszatérünk az igazolásokhoz, a már eligazolt játékost látjuk a távozó helyén
                }else{echo $e->getMessage();}
            }
            elseif($newcredits < 0){ echo '<div class="alert alert-danger text-center">Túl sok pénzt költöttél! </div>';}
            elseif(!$unique){ echo '<div class="alert alert-danger text-center">Ez a játékos már szerepel a csapatodban! </div>';}
            elseif(max(array_count_values($teamcheck)) > 2){ echo '<div class="alert alert-danger text-center">Egy csapatból maximum 2 játékost választhatsz! </div>';}
            elseif((2 - $transfercheck['num']) < $transfernumber){ echo '<div class="alert alert-danger text-center">Erre a fordulóra már csak 1 szabad igazolásod van! </div>';}
            break;
        case 2:
            if($newcredits >= 0 AND $unique == 1 AND (max(array_count_values($teamcheck)) <= 2) AND ((2 - $transfercheck['num'])>= $transfernumber )){

                
                $booktransfer=$crud->transferToRoster($teamrequest['competitor_id'],$weekcheck,$_SESSION['transf1'],$_SESSION['player1in']);
                if($transfernumber ==2){
                    $booktransfer2=$crud->transferToRoster($teamrequest['competitor_id'],$weekcheck,$_SESSION['transf2'],$_SESSION['player2in']);
                }
                $updatecredit=$crud->updateCredits($teamrequest['competitor_id'],$newcredits);

                $teamresults=$crud->getTeamresultcount($teamrequest['competitor_id'],$week);
                if($teamresults['0'] > 0 AND $week<>10){
                    $addnewtransfer=$crud->insertTransfer($teamrequest['competitor_id'],$week,$_SESSION['transf1'],$_SESSION['player1in']);
                    if($transfernumber ==2){
                        $addnewtransfer2=$crud->insertTransfer($teamrequest['competitor_id'],$week,$_SESSION['transf2'],$_SESSION['player2in']);
                    }
                }; 

                if($roster['captain']==$_SESSION['transf1']){$crud->updateCaptain($teamrequest['competitor_id'],$_SESSION['player1in'],$week);} 
                if($roster['captain']==$_SESSION['transf2']){$crud->updateCaptain($teamrequest['competitor_id'],$_SESSION['player2in'],$week);}
                
                if($booktransfer AND $updatecredit){
                    
                    unset($_SESSION['transf1']);
                    unset($_SESSION['transf2']);
                    unset($_SESSION['player1in']);
                    unset($_SESSION['player2in']);
                    
                    echo '<script type="text/javascript">location.href="transfer.php";</script>
                <noscript><meta http-equiv="refresh" content="0; URL=transfer.php" /></noscript> ';
                    
                }else{echo $e->getMessage();}
            }
            elseif($newcredits < 0){ echo '<div class="alert alert-danger text-center">You spent more than your budget! </div>';}
            elseif(!$unique){ echo '<div class="alert alert-danger text-center">You selected a player who is already in your team! </div>';}
            elseif(max(array_count_values($teamcheck)) > 2){ echo '<div class="alert alert-danger text-center">You can select maximum 2 players from each team! </div>';}
            elseif((2 - $transfercheck['num']) < $transfernumber){ echo '<div class="alert alert-danger text-center">You have only 1 transfer option left for the gameweek! </div>';}
            break;
        case 3:
            if($newcredits >= 0 AND $unique == 1 AND (max(array_count_values($teamcheck)) <= 2) AND ((2 - $transfercheck['num'])>= $transfernumber )){

                
                $booktransfer=$crud->transferToRoster($teamrequest['competitor_id'],$weekcheck,$_SESSION['transf1'],$_SESSION['player1in']);
                if($transfernumber ==2){
                    $booktransfer2=$crud->transferToRoster($teamrequest['competitor_id'],$weekcheck,$_SESSION['transf2'],$_SESSION['player2in']);
                }
                $updatecredit=$crud->updateCredits($teamrequest['competitor_id'],$newcredits);

                $teamresults=$crud->getTeamresultcount($teamrequest['competitor_id'],$week);
                if($teamresults['0'] > 0 AND $week<>10){
                    $addnewtransfer=$crud->insertTransfer($teamrequest['competitor_id'],$week,$_SESSION['transf1'],$_SESSION['player1in']);
                    if($transfernumber ==2){
                        $addnewtransfer2=$crud->insertTransfer($teamrequest['competitor_id'],$week,$_SESSION['transf2'],$_SESSION['player2in']);
                    }
                }; 

                if($roster['captain']==$_SESSION['transf1']){$crud->updateCaptain($teamrequest['competitor_id'],$_SESSION['player1in'],$week);} 
                if($roster['captain']==$_SESSION['transf2']){$crud->updateCaptain($teamrequest['competitor_id'],$_SESSION['player2in'],$week);}
                
                if($booktransfer AND $updatecredit){
                    
                    unset($_SESSION['transf1']);
                    unset($_SESSION['transf2']);
                    unset($_SESSION['player1in']);
                    unset($_SESSION['player2in']);
                    
                    echo '<script type="text/javascript">location.href="transfer.php";</script>
                <noscript><meta http-equiv="refresh" content="0; URL=transfer.php" /></noscript> ';
                    
                }else{echo $e->getMessage();}
            }
            elseif($newcredits < 0){ echo '<div class="alert alert-danger text-center">Du hast mehr ausgegeben als dein Budget! </div>';}
            elseif(!$unique){ echo '<div class="alert alert-danger text-center">Du hast einen Spieler ausgewählt, der bereits in deinem Team ist! </div>';}
            elseif(max(array_count_values($teamcheck)) > 2){ echo '<div class="alert alert-danger text-center">Du kannst maximal 2 Spieler aus jeder Mannschaft auswählen! </div>';}
            elseif((2 - $transfercheck['num']) < $transfernumber){ echo '<div class="alert alert-danger text-center">Für diese Spielwoche hast du nur einen Transfer </div>';}
            break;
    }

}

//az összes ELKÜLD gombnak ugyanaz a neve, csak más értéket vesz fel, ha megnyomjuk - innen tudjuk hányadik számú játékost küldjük el
if (isset($_POST['transf'])){
        if((isset($_SESSION['transf1']) AND $_POST['transf']==$_SESSION['transf1']) OR (isset($_SESSION['transf2']) AND $_POST['transf']==$_SESSION['transf2'])){ 
            //ha már van távozó játékosunk, akkor nem engedjük újra kiválasztani
            // echo '<div class="alert alert-danger text-center">A kiválasztott játékos már távozó! </div>';
        }else{
            if(!isset($_SESSION['transf1']) OR $_SESSION['transf1']==""){ 
                $player1out=$_POST['transf'];
                $_SESSION['transf1']=$player1out;
            }elseif(!isset($_SESSION['transf2']) OR $_SESSION['transf2']==""){
                $player2out=$_POST['transf'];
                $_SESSION['transf2']=$player2out;
            }
        
        }
        
    }

if(isset($_POST['cancel1'])){
    if(isset($_SESSION['transf2']) AND $_SESSION['transf2'] !==''){
        $_SESSION['transf1']=$_SESSION['transf2'];
        $player2out="";
        unset($_SESSION['transf2']);
    }else{
        $player1out=""; 
        unset($_SESSION['transf1']); 
    }}
    
if(isset($_POST['cancel2'])){
    $player2out=""; 
    unset($_SESSION['transf2']);

}; 
//ha megnyomjuk a MÉGSE gombot, nullázzuk a SESSION-t

if(isset($_POST['add'])){
    $transform = explode('_',$_POST['add']);
    $playertoadd=end($transform);
    if(!isset($_SESSION['player1in'])){
        $_SESSION['player1in']=$playertoadd;
    }elseif(!isset($_SESSION['player2in'])){
        $_SESSION['player2in']=$playertoadd;
    }
}
if(isset($_POST['cancel1in'])){
    if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==''){
        $_SESSION['player1in']=$_SESSION['player2in'];
        $player2in="";
        unset($_SESSION['player2in']);
    }else{
        $player1in=""; 
        unset($_SESSION['player1in']); 
    }}
    
if(isset($_POST['cancel2in'])){
    $player2in=""; 
    unset($_SESSION['player2in']);

}; 

//megnézzük, hogy ugyanannyi érkező játékos van-e, mint távozó
if(isset($_SESSION['transf1']) AND $_SESSION['transf1'] !=="" AND isset($_SESSION['player1in']) AND $_SESSION['player1in'] !=="" AND (!isset($_SESSION['transf2']) OR $_SESSION['transf2']=="") AND (!isset($_SESSION['player2in']) OR $_SESSION['player2in']=="")){
    $transferbalance=true;
}elseif(isset($_SESSION['transf1']) AND isset($_SESSION['player1in']) AND isset($_SESSION['transf2']) AND isset($_SESSION['player2in'])){
    $transferbalance=true;
}else{
    $transferbalance=false;
}

?>
<style>
    h2, h3{
        color:#170202;
    }    
    
    #teamname {
        font-size:2.5rem;
        margin-bottom: 1rem;
        padding-left: 2rem;
        font-weight: 400;
    }

    hr.divider {
        border: 1px solid red;
        border-radius: 1px;
        margin-top: 1rem;
    }
    #cut2{
        display:none
    }
    #remaining {
        font-size:14px;
        margin-top: 1rem;
    }
    #teamdata{
        margin-left:0;
        padding-left: 0;
        background-image: linear-gradient(rgba(255, 255, 255, 0) 20%, rgba(255, 255, 255, 0.5) 70%, rgb(255, 255, 255) 100%), linear-gradient(to right, #01cae4 100%, #0146fe 82%);
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        margin-bottom:1rem;
    }

    #market{
        max-width: 600px;
        border: 1px solid grey;
        border-radius: 8px;
        padding: 10px;
        margin-top: 50px;
        box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 6px, rgba(0, 0, 0, 0.23) 0px 3px 6px;
        /* background-color: #EFF7FF; */
    }

    .marketplayer{
        display:flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom:5px;
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

    #teamdata h3 {
        font-size: 1.5rem;
        color: #170202;
    }

    .sold img{
        filter: saturate(0.3);
    }
    .sold td{
        color: grey
    }
    .sold .btn{
        background-color: white;
        color: grey;
    }

    @media (max-width: 800px) {
        #cut2{display: block;}
        #flex {display: flex; flex-direction: column; }
        #players { order: 1; }
        #cut {order: 2; }
        #transfer { order: 3; }
        #cut2 { order: 4; }
        #market { order: 5; }
        hr.divider{border: 2px solid red;}
        #value1{padding-top:0px; padding-bottom: 1px;}
        #teamname {font-size:1.8rem;}
        #market{
            max-width:100%;
            border: 0;
            margin-left: 0;
            padding-left: 5px
        }
    }
    @media (min-width: 1300px){
        #myteam{
            padding-left: 2rem;
        }
        
    }
</style>

<?php require_once 'includes/minileagueselect.php'; ?>

<div class="container">
    <div class="row justify-content-md-center">
        <div class="col">
            <h2 id="teamname"><?php echo $teamname;?></h2>
        </div>
    </div>
    <div class="row row-cols-2 text-center" id="teamdata">
        <div class="col">
            <h3>
                <?php switch($_SESSION['lang']){
                    case 1: echo "Csapat érték: ";
                    break;
                    case 2: echo "Team value: ";
                    break;
                    case 3: echo "Teamwert: ";
                    break;
                }?>
            </h3>
            <h2 class="fw-bold">
                <?php echo $valuetotal;?> 
            </h2>
        </div>
        <div class="col">
            <h3>
                <?php switch($_SESSION['lang']){
                    case 1: echo "Megmaradt összeg: ";
                    break;
                    case 2: echo "Remaining budget: ";
                    break;
                    case 3: echo "Verbleibendes Budget: ";
                    break;
                }?>
            </h3>
            <h2 class="fw-bold">
                <?php echo $remainingcredits; ?>
            </h2>
        </div>
    </div>
    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="transfers">
        <div class="row row-cols" id="flex">
        <div class="col border-end" id="players">
            <table class="table table-fixed" style="text-align: center; vertical-align: middle">
            <thead>
                <tr>
                    <th style="text-align: left">
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Kezdőcsapat";
                            break;
                            case 2: echo "Line-up";
                            break;
                            case 3: echo "Teamaufstellung";
                            break;
                        }?>
                    </th>
                    <th>
                        <?php switch($_SESSION['lang']){
                            case 1: 
                            ?>
                                <abbr title="Ár">Ár</abbr>
                            <?php 
                            break;
                            case 2: 
                            ?>
                                <abbr title="Price">Price</abbr>
                            <?php 
                            break;
                            case 3: 
                            ?>
                                <abbr title="Preis">Preis</abbr>
                            <?php 
                            break;
                        }?>
                    </th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player1']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player1'])) echo 'class="sold"' ?> >
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team1=$player->getPlayerteam($roster['player1']); echo $team1['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                            <?php $player1name=$player->getPlayerbyID($roster['player1']); echo $player1name['playername'];?>
                        </div>
                    </td>
                    <td> <?php $value1=$player->getPrice($roster['player1'],$week); echo $value1['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player1'] ?>" id="player1trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player1trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player2']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player2'])) echo 'class="sold"' ?> > <!-- player2 -->
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team2=$player->getPlayerteam($roster['player2']); echo $team2['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                            <?php $player2name=$player->getPlayerbyID($roster['player2']); echo $player2name['playername'];?>
                        </div>
                    </td>
                    <td> <?php $value2=$player->getPrice($roster['player2'],$week); echo $value2['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player2'] ?>" id="player2trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player2trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player3']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player3'])) echo 'class="sold"' ?> > <!-- player3 -->
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team3=$player->getPlayerteam($roster['player3']); echo $team3['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                            <?php $player3name=$player->getPlayerbyID($roster['player3']); echo $player3name['playername'];?>
                        </div>
                    </td>
                    <td> <?php $value3=$player->getPrice($roster['player3'],$week); echo $value3['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player3'] ?>" id="player3trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player3trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player4']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player4'])) echo 'class="sold"' ?> > <!-- player4 -->
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team4=$player->getPlayerteam($roster['player4']); echo $team4['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                            <?php $player4name=$player->getPlayerbyID($roster['player4']); echo $player4name['playername'];?>
                        </div>
                    </td>
                    <td> <?php $value4=$player->getPrice($roster['player4'],$week); echo $value4['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player4'] ?>" id="player4trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player4trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player5']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player5'])) echo 'class="sold"' ?> > <!-- player5 -->
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team5=$player->getPlayerteam($roster['player5']); echo $team5['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                            <?php $player5name=$player->getPlayerbyID($roster['player5']); echo $player5name['playername'];?>
                        </div>
                    </td>
                    <td> <?php $value5=$player->getPrice($roster['player5'],$week); echo $value5['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player5'] ?>" id="player5trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player5trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player6']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player6'])) echo 'class="sold"' ?> > <!-- player6 -->
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team6=$player->getPlayerteam($roster['player6']); echo $team6['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                            <?php $player6name=$player->getPlayerbyID($roster['player6']); echo $player6name['playername'];?>
                        </div>
                    </td>
                    <td> <?php $value6=$player->getPrice($roster['player6'],$week); echo $value6['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player6'] ?>" id="player6trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player6trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player7']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player7'])) echo 'class="sold"' ?> > <!-- player7 -->
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team7=$player->getPlayerteam($roster['player7']); echo $team7['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                            <?php $player7name=$player->getPlayerbyID($roster['player7']); echo $player7name['playername'];?>
                        </div>
                    </td>
                    <td> <?php $value7=$player->getPrice($roster['player7'],$week); echo $value7['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player7'] ?>" id="player7trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player7trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
                <tr <?php if((isset($_SESSION['transf1']) AND $_SESSION['transf1']==$roster['player8']) OR (isset($_SESSION['transf2']) AND $_SESSION['transf2']==$roster['player8'])) echo 'class="sold"' ?> > <!-- player8 -->
                    <td style="text-align: left">
                        <div>
                            <img src="img/teamlogo/<?php $team8=$player->getPlayerteam($roster['player8']); echo $team8['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">

                            <?php $player8name=$player->getPlayerbyID($roster['player8']); echo $player8name['playername'];?>

                        </div>
                    </td>
                    <td> <?php $value8=$player->getPrice($roster['player8'],$week); echo $value8['price'];?></td>
                    <td>
                        <input type="submit" class="btn-check" name="transf" value="<?= $roster['player8'] ?>" id="player8trans" onchange="this.form.submit()" <?php if($buttondisable){ echo "disabled";}?>>
                        <label class="btn btn-warning" for="player8trans">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Elküld";
                                break;
                                case 2: echo "Sell";
                                break;
                                case 3: echo "Tausch";
                                break;
                            }?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>

        <div class="col" id="transfer">
            <div class="row text-center">
                <h4>
                    <?php switch($_SESSION['lang']){
                        case 1: echo "Átigazolási lap";
                        break;
                        case 2: echo "Transfer sheet";
                        break;
                        case 3: echo "Transferblatt";
                        break;
                    }?>
                </h4>
            </div>
            <table class="table table-hover table-fixed" style="vertical-align: middle">
                <thead>
                    <tr>
                        <th style="text-align: left">
                            <?php switch($_SESSION['lang']){
                                case 1: echo 'Távozó játékos <strong style="color:red; font-size:20px;">&#10140;</strong>';
                                break;
                                case 2: echo 'Player out <strong style="color:red; font-size:20px;">&#10140;</strong>';
                                break;
                                case 3: echo 'Spieler raus <strong style="color:red; font-size:20px;">&#10140;</strong>';
                                break;
                            }?>
                        </th>
                        <th>
                            
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(isset($_SESSION['transf1'])){?>
                    <tr>
                        <td style="text-align: left">
                            <div>
                                <img src="img/teamlogo/<?php $player1outteam=$player->getPlayerteam($_SESSION['transf1']); echo $player1outteam['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                                
                                <?php if(isset($_SESSION['transf1']) AND $_SESSION['transf1'] !==""){$player1outname=$player->getPlayerbyID($_SESSION['transf1']); echo $player1outname['playername'];}?> 
                                
                            </div>
                        </td>
                        <td> <?php if(isset($_SESSION['transf1']) AND $_SESSION['transf1'] !==""){$player1outvalue=$player->getPrice($_SESSION['transf1'],$week); echo $player1outvalue['price'];}?></td>
                        <?php if(isset($_SESSION['transf1']) AND $_SESSION['transf1'] !==""){ ?>
                        <td>
                            <input type="submit" class="btn-check" name="cancel1" value="cancel1" id="cancel1" onchange="this.form.submit()">
                            <label class="btn btn-outline-secondary" for="cancel1">
                                <?php switch($_SESSION['lang']){
                                    case 1: echo "Mégsem";
                                    break;
                                    case 2: echo "Cancel";
                                    break;
                                    case 3: echo "Abbrechen";
                                    break;
                                }?>
                            </label>
                        </td>
                        <?php } ?>
                    </tr>    
                    <?php } ?>   
                    
                    <?php if(isset($_SESSION['transf2'])){?>
                    <tr>
                        <td style="text-align: left">
                            <div>
                                <img src="img/teamlogo/<?php $player2outteam=$player->getPlayerteam($_SESSION['transf2']); echo $player2outteam['logo'];?>" alt="" style="height:24px; margin-right:0.5vw;">
                                
                                <?php if(isset($_SESSION['transf2']) AND $_SESSION['transf2'] !==""){$player2outname=$player->getPlayerbyID($_SESSION['transf2']); echo $player2outname['playername'];}?> 
                                
                            </div>
                        </td>
                        <td> <?php if(isset($_SESSION['transf2']) AND $_SESSION['transf2'] !==""){$player2outvalue=$player->getPrice($_SESSION['transf2'],$week); echo $player2outvalue['price'];}?></td>
                        <?php if(isset($_SESSION['transf2']) AND $_SESSION['transf2'] !==""){ ?>
                        <td>
                            <input type="submit" class="btn-check" name="cancel2" value="cancel2" id="cancel2" onchange="this.form.submit()">
                            <label class="btn btn-outline-secondary" for="cancel2">
                                <?php switch($_SESSION['lang']){
                                    case 1: echo "Mégsem";
                                    break;
                                    case 2: echo "Cancel";
                                    break;
                                    case 3: echo "Abbrechen";
                                    break;
                                }?>
                            </label>
                        </td>
                        <?php } ?>   
                    </tr>
                    <?php } ?>

            </table>
            <table class="table table-fixed" style="vertical-align: middle">        
                <thead>
                    <tr>
                        <th></th>
                        <th></th>
                        <th style="text-align: right">
                            <?php switch($_SESSION['lang']){
                                case 1: echo '<strong style="color:green; font-size:20px; display: inline-block; transform: scale(-1, 1);">&#10140;</strong> Érkező játékos';
                                break;
                                case 2: echo '<strong style="color:green; font-size:20px; display: inline-block; transform: scale(-1, 1);">&#10140;</strong> Player in';
                                break;
                                case 3: echo '<strong style="color:green; font-size:20px; display: inline-block; transform: scale(-1, 1);">&#10140;</strong> Spieler rein';
                                break;
                            }?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(isset($_SESSION['player1in'])){?>
                    <tr>
                        <?php if(isset($_SESSION['player1in']) AND $_SESSION['player1in'] !==""){ ?>
                        <td>
                            <input type="submit" class="btn-check" name="cancel1in" value="cancel1in" id="cancel1in" onchange="this.form.submit()">
                            <label class="btn btn-outline-secondary" for="cancel1in">
                                <?php switch($_SESSION['lang']){
                                    case 1: echo "Mégsem";
                                    break;
                                    case 2: echo "Cancel";
                                    break;
                                    case 3: echo "Abbrechen";
                                    break;
                                }?>
                            </label>
                        </td>
                        <?php } ?>
                        <td> 
                            <?php if(isset($_SESSION['player1in']) AND $_SESSION['player1in'] !==""){$player1invalue=$player->getPrice($_SESSION['player1in'],$week); echo $player1invalue['price'];}?>
                        </td>
                        <td style="text-align: right">
                            <div>
                                <?php if(isset($_SESSION['player1in']) AND $_SESSION['player1in'] !==""){$player1inname=$player->getPlayerbyID($_SESSION['player1in']); echo $player1inname['playername'];}?> 
                                
                                <img src="img/teamlogo/<?php $player1inteam=$player->getPlayerteam($_SESSION['player1in']); echo $player1inteam['logo'];?>" alt="" style="height:24px; margin-left:0.5vw;">
                                                                
                            </div>
                        </td>
                        
                        
                    </tr>    
                    <?php } ?>   
                    
                    <?php if(isset($_SESSION['player2in'])){?>
                    <tr>
                        <?php if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==""){ ?>
                        <td>
                            <input type="submit" class="btn-check" name="cancel2in" value="cancel2in" id="cancel2in" onchange="this.form.submit()">
                            <label class="btn btn-outline-secondary" for="cancel2in">
                                <?php switch($_SESSION['lang']){
                                    case 1: echo "Mégsem";
                                    break;
                                    case 2: echo "Cancel";
                                    break;
                                    case 3: echo "Abbrechen";
                                    break;
                                }?>
                            </label>
                        </td>
                        <?php } ?>
                        <td> 
                            <?php if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==""){$player2invalue=$player->getPrice($_SESSION['player2in'],$week); echo $player2invalue['price'];}?>
                        </td>
                        <td style="text-align: right">
                            <div>
                                <?php if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==""){$player2inname=$player->getPlayerbyID($_SESSION['player2in']); echo $player2inname['playername'];}?> 
                                
                                <img src="img/teamlogo/<?php $player2inteam=$player->getPlayerteam($_SESSION['player2in']); echo $player2inteam['logo'];?>" alt="" style="height:24px; margin-left:0.5vw;">
                                
                            </div>
                        </td>
                        
                           
                    </tr>
                    <?php } ?>
                </tbody>

            </table>
            <?php 
                $newcredits = $remainingcredits;
                if(isset($_SESSION['transf1']) AND $_SESSION['transf1'] !==""){$newcredits = $newcredits + $player1outvalue['price'];};
                if(isset($_SESSION['transf2']) AND $_SESSION['transf2'] !==""){$newcredits = $newcredits + $player2outvalue['price'];};
                if(isset($_SESSION['player1in']) AND $_SESSION['player1in'] !==""){$v1in=$player->getPrice($_SESSION['player1in'],$week); $newcredits = $newcredits - $v1in['price'];}; 
                if(isset($_SESSION['player2in']) AND $_SESSION['player2in'] !==""){$v2in=$player->getPrice($_SESSION['player2in'],$week); $newcredits = $newcredits - $v2in['price'];};
            ?>
            <table style="text-align: center">
                <tr>
                    <td>
                        <?php switch($_SESSION['lang']){
                            case 1:
                            ?>
                            <input type="submit" class="btn-check" name="transfer1" value="transfer1" id="transfer1" onchange="this.form.submit()" onclick="return confirm('Biztosan véglegesíteni szeretnéd az átigazolást?');"  <?php if(!$transferbalance OR $buttondisable){ echo "disabled";}?>> <!--ha nincs érkező vagy távozó játékos kiválasztva, akkor disabled. Akkoris, ha már megvolt a két átigazolásunk -->
                            <?php ;
                            break;

                            case 2:
                            ?>
                            <input type="submit" class="btn-check" name="transfer1" value="transfer1" id="transfer1" onchange="this.form.submit()" onclick="return confirm('Are you sure you want to finalize the transfer?');"  <?php if(!$transferbalance OR $buttondisable){ echo "disabled";}?>>
                            <?php ;
                            break;

                            case 3:
                            ?>
                            <input type="submit" class="btn-check" name="transfer1" value="transfer1" id="transfer1" onchange="this.form.submit()" onclick="return confirm('Bist du sicher, dass du den Transfer abschließen möchtest?');"  <?php if(!$transferbalance OR $buttondisable){ echo "disabled";}?>>
                            <?php ;
                            break;
                        }?>
                        <label class="btn btn-info" for="transfer1">
                            <?php switch($_SESSION['lang']){
                                case 1: echo "Átigazolás véglegesítése";
                                break;
                                case 2: echo "Finalize transfer";
                                break;
                                case 3: echo "Transfer abschließen";
                                break;
                            }?>
                        </label>
                    </td>
                    
                </tr>
                <tr>
                <td>
                    <div id="remaining">
                        <?php switch($_SESSION['lang']){
                            case 1: echo "Megmaradó összeg: ";
                            break;
                            case 2: echo "Remaining budget: ";
                            break;
                            case 3: echo "Verbleibendes Budget: ";
                            break;
                        }?>
                    <button type="button" <?php if(round($newcredits,1) >= 0){echo 'class="btn btn-outline-primary"';}else{echo 'class="btn btn-outline-danger"';}; ?> class="btn btn-outline-primary" id="value1" disabled> <?php echo number_format($newcredits,1);?></button></div>
                    </td>
                </tr>
            </table>
        </div>
        <hr class="divider" id="cut">
        <hr class="divider" id="cut2">
        
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
                    <?php if((!isset($_SESSION['player1in']) OR $r['player_id'] <> $_SESSION['player1in']) AND (!isset($_SESSION['player2in']) OR $r['player_id'] <> $_SESSION['player2in']) AND !in_array($r['player_id'],$roster)){ ?>
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
                            <span <?php if(($newcredits)<$r['price']){echo 'style=color:grey';}?>><?php echo $r['price']?>M</span>
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
    <div class="row">

    </div>



</div>

<?php }else{require_once 'includes/minileagueselect.php';} ?>

<?php
//ez a funkció segít megnézni, hogy az adott tömbben minden elem egyszer szerepel-e
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
