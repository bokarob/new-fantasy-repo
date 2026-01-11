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


//jogosultság check
if(isset($_SESSION['profile_id']) AND $_SESSION['authorization'] ==3){echo "Minden OK";}else{ echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';};

//hét beállítása
if(isset($_POST['checkweek'])){
    $_SESSION['checkweek']=$_POST['week'];
}

//értesítések küldése
//a formok értékei mindig így vannak, hogy A1_profile_gameweek_picture
if(isset($_POST['sendnoti'])){
    $notipart = explode('_',$_POST['sendnoti']);
    $newpic=$crud->newExtraPicture($notipart[1],$notipart[3],$notipart[2]);
    if($newpic){ 
        $newnotification=$crud->newPictureNotification('A1',$notipart[1],$notipart[2],$notipart[3]);
    }
}



?>

<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="pointcalc">
    <p>Forduló:</p>
    <input type="text" class="form-control" id="week" name="week" style="border-color:darkblue; width:unset;" <?php if(isset($_SESSION['checkweek'])){echo 'value="'.$_SESSION['checkweek'].'"';}else echo 'value="'.$huweek.'"';?>>
    <button type="submit" class="btn btn-primary" name="checkweek" value="checkweek">mehet</button>
</form>
<hr><hr>

<h2>Extra képek check</h2>

<hr>
<div>
    <h5>39 - legyél a TOP3-ban az egyik héten</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor39=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic39list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor39->fetch(PDO::FETCH_ASSOC)){
        if($r['rank']<=3){
            $check=$crud->findExtraPicture(39,$r['profile_id']);
            if($check['count']==0){
                $pic39list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==39){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic39list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=39>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>league_id</th> 
                <th>rank</th> 
            </tr>
        
        <?php
        foreach ($pic39list as $pic39player) {
            $result=$crud->getWeeklyRankwithDetails($pic39player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['league_id'];?></td>
                <td><?php echo $result['rank'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_39">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>45 - Egyik játékosod szerezzen 100+ pontot az egyik héten</h5>
    
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor45=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic45list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor45->fetch(PDO::FETCH_ASSOC)){
        $is100=false;
        for ($x = 1; $x <= 8; $x++){
            $play="player" . $x;
            $playerpoint=$player->getWeeklyPlayerpoints($r[$play],$_SESSION['checkweek']);
            if($playerpoint['weekpoints']>=100){
                $is100=true;
            }
        }
        if($is100){
            $check=$crud->findExtraPicture(45,$r['profile_id']);
            if($check['count']==0){
                $pic45list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==45){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic45list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=45>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>p1 /</th>
                <th>p2 /</th> 
                <th>p3 /</th> 
                <th>p4 /</th> 
                <th>p5 /</th> 
                <th>p6 /</th> 
                <th>p7 /</th> 
                <th>p8 /</th> 
            </tr>
        
        <?php
        foreach ($pic45list as $pic45player) {
            $result=$crud->getRosterwithDetails($pic45player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <?php
                for ($x = 1; $x <= 8; $x++){
                    $play="player" . $x;
                    $playerpoint=$player->getWeeklyPlayerpoints($result[$play],$_SESSION['checkweek']);
                    ?>
                    <td><?php echo $playerpoint['weekpoints'];?> /</td>
                    <?php
                }
                ?>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_45">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>47 - Egyik héten legfeljebb 4 játékosod szerzett fantasy pontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor47=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic47list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor47->fetch(PDO::FETCH_ASSOC)){
        $gotpoints=0;
        for ($x = 1; $x <= 8; $x++){
            $play="player" . $x;
            $playerpoint=$player->getWeeklyPlayerpoints($r[$play],$_SESSION['checkweek']);
            if($playerpoint['weekpoints']>0){
                $gotpoints++;
            }
        }
        if($gotpoints<=4){
            $check=$crud->findExtraPicture(47,$r['profile_id']);
            if($check['count']==0){
                $pic47list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==47){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic47list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=47>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>p1 /</th>
                <th>p2 /</th> 
                <th>p3 /</th> 
                <th>p4 /</th> 
                <th>p5 /</th> 
                <th>p6 /</th> 
                <th>p7 /</th> 
                <th>p8 /</th> 
            </tr>
        
        <?php
        foreach ($pic47list as $pic47player) {
            $result=$crud->getRosterwithDetails($pic47player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <?php
                for ($x = 1; $x <= 8; $x++){
                    $play="player" . $x;
                    $playerpoint=$player->getWeeklyPlayerpoints($result[$play],$_SESSION['checkweek']);
                    ?>
                    <td><?php echo $playerpoint['weekpoints'];?> /</td>
                    <?php
                }
                ?>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_47">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>48 - Csinálj 5 héten keresztül minden héten 2 igazolást</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor48=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic48list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor48->fetch(PDO::FETCH_ASSOC)){
        $transfercheck=0;
        for ($i=0; $i < 5; $i++) { 
            $transfernum=$crud->getTransfers($r['competitor_id'],$_SESSION['checkweek']-$i);
            $transfercheck=$transfercheck+$transfernum['num'];
        }
    
        if($transfercheck==10){
            $check=$crud->findExtraPicture(48,$r['profile_id']);
            if($check['count']==0){
                $pic48list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==48){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
        
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic48list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=48>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>current /</th> 
                <th>-1 /</th> 
                <th>-2 /</th> 
                <th>-3 /</th> 
                <th>-4</th> 
            </tr>
        
        <?php
        foreach ($pic48list as $pic48player) {
            $result=$crud->getWeeklyResultwithDetails($pic48player,$_SESSION['checkweek']);
            $transfers=array();
            for ($i=0; $i < 5; $i++) { 
                $transfernum=$crud->getTransfers($result['competitor_id'],$_SESSION['checkweek']-$i);
                $transfers[]=$transfernum['num'];
            }
            
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $transfers[0];?></td>
                <td><?php echo $transfers[1];?></td>
                <td><?php echo $transfers[2];?></td>
                <td><?php echo $transfers[3];?></td>
                <td><?php echo $transfers[4];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_48">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>49 - Csinálj összesen 10 igazolást</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor49=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic49list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor49->fetch(PDO::FETCH_ASSOC)){

        $transfernum=$crud->getAllTransfers($r['competitor_id']);
    
        if($transfernum AND $transfernum['num']>=10){
            $check=$crud->findExtraPicture(49,$r['profile_id']);
            if($check['count']==0){
                $pic49list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==49){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
        
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic49list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=49>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>transfers</th>  
            </tr>
        
        <?php
        foreach ($pic49list as $pic49player) {
            $result=$crud->getWeeklyResultwithDetails($pic49player,$_SESSION['checkweek']);
            
            $transfernum=$crud->getAllTransfers($result['competitor_id']);
                
            
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $transfernum['num'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_49">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>50 - Legyen mindkét ligában csapatod - mindkettő a TOP20-ban</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allprofile50=$crud->getAllProfiles();
    $pic50list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allprofile50->fetch(PDO::FETCH_ASSOC)){
        $compcount=$crud->getCompetitorCount($r['profile_id']);
        if($compcount['count']==2){
            $compcheck=$crud->getAllCompetitorForProfile($r['profile_id']);
            $top20=0;
            while($t = $compcheck->fetch(PDO::FETCH_ASSOC)){
                $result=$crud->getWeeklyRankwithDetails($t['competitor_id'],$_SESSION['checkweek']);
                if($result AND $result['rank']<=20) $top20++;
            }
            if($top20==2){
                $check=$crud->findExtraPicture(50,$r['profile_id']);
                if($check['count']==0){
                    $pic50list[]=$r['profile_id'];

                    //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                    if(isset($_POST['notiforall']) AND $_POST['notiforall']==50){
                        $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                        if($newpic){ 
                            $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                        }
                    }

                }
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic50list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=50>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>HU competitor</th>
                <th>HU teamname</th> 
                <th>HU rank</th> 
                <th>DE competitor</th>
                <th>DE teamname</th> 
                <th>DE rank</th> 
            </tr>
        
        <?php
        foreach ($pic50list as $pic50player) {
            $hucomp=$crud->getCompetitorID($pic50player,10);
            $huresult=$crud->getWeeklyRankwithDetails($hucomp['competitor_id'],$_SESSION['checkweek']);
            $decomp=$crud->getCompetitorID($pic50player,20);
            $deresult=$crud->getWeeklyRankwithDetails($decomp['competitor_id'],$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $pic50player;?></td>
                <td><?php echo $huresult['competitor_id'];?></td>
                <td><?php echo $huresult['teamname'];?></td>
                <td><strong><?php echo $huresult['rank'];?></strong></td>
                <td><?php echo $deresult['competitor_id'];?></td>
                <td><?php echo $deresult['teamname'];?></td>
                <td><strong><?php echo $deresult['rank'];?></strong></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $pic50player;?>_<?php echo $_SESSION['checkweek'];?>_50">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>52 - Mind a 8 játékosod szerezzen csapatpontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor52=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic52list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor52->fetch(PDO::FETCH_ASSOC)){
        $gotpoints=0;
        for ($x = 1; $x <= 8; $x++){
            $play="player" . $x;
            $playerpoint=$player->getWeeklyPlayerpoints($r[$play],$_SESSION['checkweek']);
            if($playerpoint['MP']>0){
                $gotpoints++;
            }
        }
        if($gotpoints==8){
            $check=$crud->findExtraPicture(52,$r['profile_id']);
            if($check['count']==0){
                $pic52list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==52){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic52list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=52>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>p1 /</th>
                <th>p2 /</th> 
                <th>p3 /</th> 
                <th>p4 /</th> 
                <th>p5 /</th> 
                <th>p6 /</th> 
                <th>p7 /</th> 
                <th>p8 /</th> 
            </tr>
        
        <?php
        foreach ($pic52list as $pic52player) {
            $result=$crud->getRosterwithDetails($pic52player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <?php
                for ($x = 1; $x <= 8; $x++){
                    $play="player" . $x;
                    $playerpoint=$player->getWeeklyPlayerpoints($result[$play],$_SESSION['checkweek']);
                    ?>
                    <td><?php echo $playerpoint['MP'];?> /</td>
                    <?php
                }
                ?>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_52">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>53 - Mind a 8 játékosod szerezzen fantasy pontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor53=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic53list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor53->fetch(PDO::FETCH_ASSOC)){
        $gotpoints=0;
        for ($x = 1; $x <= 8; $x++){
            $play="player" . $x;
            $playerpoint=$player->getWeeklyPlayerpoints($r[$play],$_SESSION['checkweek']);
            if($playerpoint['weekpoints']>0){
                $gotpoints++;
            }
        }
        if($gotpoints==8){
            $check=$crud->findExtraPicture(53,$r['profile_id']);
            if($check['count']==0){
                $pic53list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==53){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic53list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=53>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>p1 /</th>
                <th>p2 /</th> 
                <th>p3 /</th> 
                <th>p4 /</th> 
                <th>p5 /</th> 
                <th>p6 /</th> 
                <th>p7 /</th> 
                <th>p8 /</th> 
            </tr>
        
        <?php
        foreach ($pic53list as $pic53player) {
            $result=$crud->getRosterwithDetails($pic53player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <?php
                for ($x = 1; $x <= 8; $x++){
                    $play="player" . $x;
                    $playerpoint=$player->getWeeklyPlayerpoints($result[$play],$_SESSION['checkweek']);
                    ?>
                    <td><?php echo $playerpoint['weekpoints'];?> /</td>
                    <?php
                }
                ?>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_53">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>55 - Legyen mindkét ligában csapatod - az egyik TOP10ben, a másik TOP100-on kívül</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allprofile55=$crud->getAllProfiles();
    $pic55list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allprofile55->fetch(PDO::FETCH_ASSOC)){
        $compcount=$crud->getCompetitorCount($r['profile_id']);
        if($compcount['count']==2){
            $compcheck=$crud->getAllCompetitorForProfile($r['profile_id']);
            $top10=false;
            $top100=false;
            while($t = $compcheck->fetch(PDO::FETCH_ASSOC)){
                $result=$crud->getWeeklyRankwithDetails($t['competitor_id'],$_SESSION['checkweek']);
                if($result AND $result['rank']<=10) $top10=true;
                if($result AND $result['rank']>100) $top100=true;
            }
            if($top10 AND $top100){
                $check=$crud->findExtraPicture(55,$r['profile_id']);
                if($check['count']==0){
                    $pic55list[]=$r['profile_id'];

                    //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                    if(isset($_POST['notiforall']) AND $_POST['notiforall']==55){
                        $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                        if($newpic){ 
                            $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                        }
                    }

                }
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic55list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=55>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>HU competitor</th>
                <th>HU teamname</th> 
                <th>HU rank</th> 
                <th>DE competitor</th>
                <th>DE teamname</th> 
                <th>DE rank</th> 
            </tr>
        
        <?php
        foreach ($pic55list as $pic55player) {
            $hucomp=$crud->getCompetitorID($pic55player,10);
            $huresult=$crud->getWeeklyRankwithDetails($hucomp['competitor_id'],$_SESSION['checkweek']);
            $decomp=$crud->getCompetitorID($pic55player,20);
            $deresult=$crud->getWeeklyRankwithDetails($decomp['competitor_id'],$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $pic55player;?></td>
                <td><?php echo $huresult['competitor_id'];?></td>
                <td><?php echo $huresult['teamname'];?></td>
                <td><strong><?php echo $huresult['rank'];?></strong></td>
                <td><?php echo $deresult['competitor_id'];?></td>
                <td><?php echo $deresult['teamname'];?></td>
                <td><strong><?php echo $deresult['rank'];?></strong></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $pic55player;?>_<?php echo $_SESSION['checkweek'];?>_55">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>56 - Legyél heti győztes egyik héten </h5>
    <?php    
    $pic56list=array();
    $hucompetitor56=$crud->getHighestTeamresult($_SESSION['checkweek'],10);
        
    $check=$crud->findExtraPicture(56,$hucompetitor56['profile_id']);
    if($check['count']==0){
        $pic56list[]=$hucompetitor56['competitor_id'];
    }
    $decompetitor56=$crud->getHighestTeamresult($_SESSION['checkweek'],20);
        
    $check=$crud->findExtraPicture(56,$decompetitor56['profile_id']);
    if($check['count']==0){
        $pic56list[]=$decompetitor56['competitor_id'];
    }
        
    
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic56list)!==0){
        ?>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>league_id</th> 
                <th>weeklypoints</th> 
            </tr>
        
        <?php
        foreach ($pic56list as $pic56player) {
            $result=$crud->getWeeklyResultwithDetails($pic56player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['league_id'];?></td>
                <td><?php echo $result['weeklypoints'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_56">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>58 - Legyen a csapatodban egyszerre legalább 6 olyan korábbi FTC játékos, akik a vezetőség miatt igazoltak el</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor58=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic58list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor58->fetch(PDO::FETCH_ASSOC)){
        $ftccount=0;
        $ftcarray=[10099,10013,10006,10070,10037,10021,10082,10023];
        for ($x = 1; $x <= 8; $x++){
            $play="player" . $x;
            if(in_array($r[$play],$ftcarray)){
                $ftccount++;
            }
        }
        if($ftccount>=4){
            $check=$crud->findExtraPicture(58,$r['profile_id']);
            if($check['count']==0){
                $pic58list[]=$r['competitor_id'];
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic58list)!==0){
        ?>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>p1 /</th>
                <th>p2 /</th> 
                <th>p3 /</th> 
                <th>p4 /</th> 
                <th>p5 /</th> 
                <th>p6 /</th> 
                <th>p7 /</th> 
                <th>p8 /</th> 
            </tr>
        
        <?php
        foreach ($pic58list as $pic58player) {
            $result=$crud->getRosterwithDetails($pic58player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <?php
                for ($x = 1; $x <= 8; $x++){
                    $play="player" . $x;
                    ?>
                    <td><?php if(in_array($result[$play],$ftcarray)) echo "FTC";?> /</td>
                    <?php
                }
                ?>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_58">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>62 - Szerezzen mind a 6 kezdőjátékosod csapatpontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor62=$crud->listAllCompetitorsWithRosters($_SESSION['checkweek']);
    $pic62list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor62->fetch(PDO::FETCH_ASSOC)){
        $gotpoints=0;
        for ($x = 1; $x <= 6; $x++){
            $play="player" . $x;
            $playerpoint=$player->getWeeklyPlayerpoints($r[$play],$_SESSION['checkweek']);
            if($playerpoint['MP']>0){
                $gotpoints++;
            }
        }
        if($gotpoints==6){
            $check=$crud->findExtraPicture(62,$r['profile_id']);
            if($check['count']==0){
                $pic62list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==62){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic62list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=62>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>p1 /</th>
                <th>p2 /</th> 
                <th>p3 /</th> 
                <th>p4 /</th> 
                <th>p5 /</th> 
                <th>p6 /</th> 
                <th>p7 /</th> 
                <th>p8 /</th> 
            </tr>
        
        <?php
        foreach ($pic62list as $pic62player) {
            $result=$crud->getRosterwithDetails($pic62player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <?php
                for ($x = 1; $x <= 8; $x++){
                    $play="player" . $x;
                    $playerpoint=$player->getWeeklyPlayerpoints($result[$play],$_SESSION['checkweek']);
                    ?>
                    <td><?php echo $playerpoint['MP'];?> /</td>
                    <?php
                }
                ?>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_62">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>64 - 200+ pont fejlődés egyik hétről a másikra    </h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor64=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic64list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor64->fetch(PDO::FETCH_ASSOC)){
        $checkprweek=$crud->getWeeklyResultwithDetails($r['competitor_id'],$_SESSION['checkweek']-1);
        if($checkprweek AND $checkprweek['weeklypoints']+200<=$r['weeklypoints']){
            $rostercheck=$crud->existRoster($r['competitor_id'],$_SESSION['checkweek']);
            if($rostercheck['num']>0){
                $check=$crud->findExtraPicture(64,$r['profile_id']);
                if($check['count']==0){
                    $pic64list[]=$r['competitor_id'];

                    //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                    if(isset($_POST['notiforall']) AND $_POST['notiforall']==64){
                        $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                        if($newpic){ 
                            $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                        }
                    }

            }
            }
            
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic64list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=64>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>last week points</th> 
                <th>current week points</th> 
            </tr>
        
        <?php
        foreach ($pic64list as $pic64player) {
            $result=$crud->getWeeklyResultwithDetails($pic64player,$_SESSION['checkweek']);
            $prresult=$crud->getWeeklyResultwithDetails($pic64player,$_SESSION['checkweek']-1);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $prresult['weeklypoints'];?></td>
                <td><?php echo $result['weeklypoints'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_64">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>65 - vezesd a ligát az egyik héten</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor65=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic65list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor65->fetch(PDO::FETCH_ASSOC)){
        if($r['rank']==1){
            $check=$crud->findExtraPicture(65,$r['profile_id']);
            if($check['count']==0){
                $pic65list[]=$r['competitor_id'];
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic65list)!==0){
        ?>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>league_id</th> 
                <th>rank</th> 
            </tr>
        
        <?php
        foreach ($pic65list as $pic65player) {
            $result=$crud->getWeeklyRankwithDetails($pic65player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['league_id'];?></td>
                <td><?php echo $result['rank'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_65">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>66 - Egyik héten maradjon legalább 15M elköltetlen kereted </h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor66=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic66list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor66->fetch(PDO::FETCH_ASSOC)){
        if($r['credits']>=15){
            $check=$crud->findExtraPicture(66,$r['profile_id']);
            if($check['count']==0){
                $pic66list[]=$r['competitor_id'];
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic66list)!==0){
        ?>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>league_id</th> 
                <th>credit</th> 
            </tr>
        
        <?php
        foreach ($pic66list as $pic66player) {
            $result=$crud->getWeeklyRankwithDetails($pic66player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['league_id'];?></td>
                <td><?php echo $result['credits'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_66">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>67 - szerezz 0 pontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor67=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic67list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor67->fetch(PDO::FETCH_ASSOC)){
        if($r['weeklypoints']==0){
            $rostercheck=$crud->existRoster($r['competitor_id'],$_SESSION['checkweek']);
            if($rostercheck['num']>0){
                $check=$crud->findExtraPicture(67,$r['profile_id']);
                if($check['count']==0){
                $pic67list[]=$r['competitor_id'];
            }
            }
            
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic67list)!==0){
        ?>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>points</th> 
            </tr>
        
        <?php
        foreach ($pic67list as $pic67player) {
            $result=$crud->getWeeklyResultwithDetails($pic67player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['weeklypoints'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_67">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>68 - Vegyél valakit, majd add el ugyanazon a játékhéten </h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor68=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic68list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor68->fetch(PDO::FETCH_ASSOC)){
        $transfernum=$crud->getTransfers($r['competitor_id'],$_SESSION['checkweek']);
        if($transfernum AND $transfernum['num']==2){
            $i=1;
            $transferlist=$crud->getWeeklyTransfers($r['competitor_id'],$_SESSION['checkweek']);
            foreach ($transferlist as $transfer) {
                if($i==1){
                    $t1out=$transfer['playerout'];
                    $t1in=$transfer['playerin'];
                }elseif($i==2){
                    $t2out=$transfer['playerout'];
                    $t2in=$transfer['playerin'];
                }
                $i++;
            }
            if($t1in==$t2out){
                $check=$crud->findExtraPicture(68,$r['profile_id']);
                if($check['count']==0){
                    $pic68list[]=$r['competitor_id'];

                    //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                    if(isset($_POST['notiforall']) AND $_POST['notiforall']==68){
                        $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                        if($newpic){ 
                            $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                        }
                    }

                }
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic68list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=68>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>t1out</th> 
                <th>t1in</th> 
                <th>t2out</th> 
                <th>t2in</th> 
            </tr>
        
        <?php
        foreach ($pic68list as $pic68player) {
            $result=$crud->getWeeklyResultwithDetails($pic68player,$_SESSION['checkweek']);
            $i=1;
            $transferlist=$crud->getWeeklyTransfers($pic68player,$_SESSION['checkweek']);
            foreach ($transferlist as $transfer) {
                if($i==1){
                    $t1out=$transfer['playerout'];
                    $t1in=$transfer['playerin'];
                }elseif($i==2){
                    $t2out=$transfer['playerout'];
                    $t2in=$transfer['playerin'];
                }
                $i++;
            }
            $t1outname=$player->getPlayerbyID($t1out);
            $t1inname=$player->getPlayerbyID($t1in);
            $t2outname=$player->getPlayerbyID($t2out);
            $t2inname=$player->getPlayerbyID($t2in);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $t1outname['playername'];?></td>
                <td><?php echo $t1inname['playername'];?></td>
                <td><?php echo $t2outname['playername'];?></td>
                <td><?php echo $t2inname['playername'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_68">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>70 - Legyen 4 héten keresztül mindig más a kapitány </h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor70=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic70list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor70->fetch(PDO::FETCH_ASSOC)){
        $captaincount=$crud->getCaptainsLast4weeks($r['competitor_id'],$_SESSION['checkweek']);
        if($captaincount['count']==4){
            $check=$crud->findExtraPicture(70,$r['profile_id']);
            if($check['count']==0){
                $pic70list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==70){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
        
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic70list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=70>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>gw <?php echo $_SESSION['checkweek'] ?></th> 
                <th>gw <?php echo $_SESSION['checkweek']-1 ?></th> 
                <th>gw <?php echo $_SESSION['checkweek']-2 ?></th> 
                <th>gw <?php echo $_SESSION['checkweek']-3 ?></th> 
            </tr>
        
        <?php
        foreach ($pic70list as $pic70player) {
            $w0=$crud->getRosterwithDetails($pic70player,$_SESSION['checkweek']);
            $w1=$crud->getRosterwithDetails($pic70player,$_SESSION['checkweek']-1);
            $w2=$crud->getRosterwithDetails($pic70player,$_SESSION['checkweek']-2);
            $w3=$crud->getRosterwithDetails($pic70player,$_SESSION['checkweek']-3);
            
            $w0name=$player->getPlayerbyID($w0['captain']);
            $w1name=$player->getPlayerbyID($w1['captain']);
            $w2name=$player->getPlayerbyID($w2['captain']);
            $w3name=$player->getPlayerbyID($w3['captain']);
            ?>
            <tr>
                <td><?php echo $w0['profile_id'];?></td>
                <td><?php echo $w0['competitor_id'];?></td>
                <td><?php echo $w0['teamname'];?></td>
                <td><?php echo $w0name['playername'];?></td>
                <td><?php echo $w1name['playername'];?></td>
                <td><?php echo $w2name['playername'];?></td>
                <td><?php echo $w3name['playername'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $w0['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_70">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>71 - szerezz 600 pontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor71=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic71list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor71->fetch(PDO::FETCH_ASSOC)){
        if($r['weeklypoints']>=600){
            $check=$crud->findExtraPicture(71,$r['profile_id']);
            if($check['count']==0){
                $pic71list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==71){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic71list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=71>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>points</th> 
            </tr>
        
        <?php
        foreach ($pic71list as $pic71player) {
            $result=$crud->getWeeklyResultwithDetails($pic71player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['weeklypoints'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_71">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>72 - szerezz 650 pontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor72=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic72list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor72->fetch(PDO::FETCH_ASSOC)){
        if($r['weeklypoints']>=650){
            $check=$crud->findExtraPicture(72,$r['profile_id']);
            if($check['count']==0){
                $pic72list[]=$r['competitor_id'];

                //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                if(isset($_POST['notiforall']) AND $_POST['notiforall']==72){
                    $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                    if($newpic){ 
                        $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                    }
                }

            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic72list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=72>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>points</th> 
            </tr>
        
        <?php
        foreach ($pic72list as $pic72player) {
            $result=$crud->getWeeklyResultwithDetails($pic72player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['weeklypoints'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_72">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>73 - szerezz 700 pontot</h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor73=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic73list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor73->fetch(PDO::FETCH_ASSOC)){
        if($r['weeklypoints']>=700){
            $check=$crud->findExtraPicture(73,$r['profile_id']);
            if($check['count']==0){
                $pic73list[]=$r['competitor_id'];
            }
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic73list)!==0){
        ?>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>points</th> 
            </tr>
        
        <?php
        foreach ($pic73list as $pic73player) {
            $result=$crud->getWeeklyResultwithDetails($pic73player,$_SESSION['checkweek']);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $result['weeklypoints'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_73">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

<hr>
<div>
    <h5>74 - Szerezz legalább 200 ponttal kevesebbet egyik héten, mint az előzőn </h5>
    <?php 
    //kigyűjtünk mindenkit aki szerzett pontot a héten
    $allcompetitor74=$crud->listAllCompetitorsForWeek($_SESSION['checkweek']);
    $pic74list=array();
    //kigyűjtjük egy listába azokat, akik kapnak
    while($r = $allcompetitor74->fetch(PDO::FETCH_ASSOC)){
        $checkprweek=$crud->getWeeklyResultwithDetails($r['competitor_id'],$_SESSION['checkweek']-1);
        if($checkprweek AND $checkprweek['weeklypoints']-200>=$r['weeklypoints']){
            $rostercheck=$crud->existRoster($r['competitor_id'],$_SESSION['checkweek']);
            if($rostercheck['num']>0){
                $check=$crud->findExtraPicture(74,$r['profile_id']);
                if($check['count']==0){
                    $pic74list[]=$r['competitor_id'];

                    //ha rákattintottunk az összes küldésére, akkor hozzáadjuk a képet az emberhez és küldünk neki notit
                    if(isset($_POST['notiforall']) AND $_POST['notiforall']==74){
                        $newpic=$crud->newExtraPicture($r['profile_id'],$_POST['notiforall'],$_SESSION['checkweek']);
                        if($newpic){ 
                            $newnotification=$crud->newPictureNotification('A1',$r['profile_id'],$_SESSION['checkweek'],$_POST['notiforall']);
                        }
                    }

                }
            }
            
        }
    }
    //ha van bárki, akkor őket kilistázzuk és kirakunk egy gombot a számoláshoz
    if(count($pic74list)!==0){
        ?>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
            <button type="submit" class="btn btn-success" name="notiforall" value=74>Értesítések küldése mindenkinek</button>
        </form>
        <table>
            <tr>
                <th>profile</th>
                <th>competitor</th>
                <th>teamname</th>
                <th>last week points</th> 
                <th>current week points</th> 
            </tr>
        
        <?php
        foreach ($pic74list as $pic74player) {
            $result=$crud->getWeeklyResultwithDetails($pic74player,$_SESSION['checkweek']);
            $prresult=$crud->getWeeklyResultwithDetails($pic74player,$_SESSION['checkweek']-1);
            ?>
            <tr>
                <td><?php echo $result['profile_id'];?></td>
                <td><?php echo $result['competitor_id'];?></td>
                <td><?php echo $result['teamname'];?></td>
                <td><?php echo $prresult['weeklypoints'];?></td>
                <td><?php echo $result['weeklypoints'];?></td>
                <td>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="extrapic">
                        <button type="submit" class="btn btn-info" name="sendnoti" value="A1_<?php echo $result['profile_id'];?>_<?php echo $_SESSION['checkweek'];?>_74">Értesítések küldése</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </table>
    <?php } ?>
</div>

