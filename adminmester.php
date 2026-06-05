
<?php 
$title = "Admin";
require_once 'db/conn.php';
require_once 'includes/header.php';
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
if(isset($_SESSION['profile_id']) AND $_SESSION['authorization'] ==3){echo $_SESSION['authorization'];}else{ echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';};


//hetek és rosterek kezelése pontszámításhoz. Itt a heteket nem vesszük külön német/magyar hétre. Ha van olyan, ami eltér, a számított pontok alapján látszódni fog.
if(isset($_POST['checkweek'])){
    $_SESSION['checkweek']=$_POST['week'];
}
if(isset($_SESSION['checkweek'])){
    $pointcheck=$admin->getRosters($_SESSION['checkweek']);
}else{$pointcheck=$admin->getRosters($huweek);}

//forduló zárás/nyitás
    if(isset($_POST['husubmit'])){
        if($_POST['huweekchange']=="open"){
            $hugameweekchange=$admin->YEStrade($huweek,10);
        }elseif($_POST['huweekchange']=="close"){
            $hugameweekchange=$admin->NOtrade($huweek,10);
        }
    }
    if(isset($_POST['desubmit'])){
        if($_POST['deweekchange']=="open"){
            $degameweekchange=$admin->YEStrade($deweek,20);
        }elseif($_POST['deweekchange']=="close"){
            $degameweekchange=$admin->NOtrade($deweek,20);
        }
    }
    if(isset($_POST['deWsubmit'])){
        if($_POST['deWweekchange']=="open"){
            $deWgameweekchange=$admin->YEStrade($deWweek,40);
        }elseif($_POST['deWweekchange']=="close"){
            $deWgameweekchange=$admin->NOtrade($deWweek,40);
        }
    }

//keretek átmásolása
    if(isset($_POST['hurostercopy'])){
        $hurostercopy=$admin->copyRosterstoNextWeek($huweek,10);
    }
    if(isset($_POST['derostercopy'])){
        $derostercopy=$admin->copyRosterstoNextWeek($deweek,20);
    }
    if(isset($_POST['deWrostercopy'])){
        $deWrostercopy=$admin->copyRosterstoNextWeek($deWweek,40);
    }

//játékosárak átmásolása
    if(isset($_POST['hupricescopy'])){
        $hupricescopy=$admin->copyPricestoNextWeek($huweek,10);
    }
    if(isset($_POST['depricescopy'])){
        $depricescopy=$admin->copyPricestoNextWeek($deweek,20);
    }
    if(isset($_POST['deWpricescopy'])){
        $deWpricescopy=$admin->copyPricestoNextWeek($deWweek,40);
    }

//trollteke kérdés   
if(isset($_POST['newquestion'])){
    $questionweek=$_POST['questionweek'];
    $question=$_POST['question'];
    $questiontype=$_POST['questiontype'];
    echo $questionweek.$question.$questiontype;
    $newquestion=$admin->enterNewQuestion($questionweek,$question,$questiontype);
}

//manuális értesítés   
if(isset($_POST['manualnoti'])){
    $notification_type=$_POST['notification_type'];
    $notiprofile_id=$_POST['profile_id'];
    $notigameweek=$_POST['gameweek'];
    $picture_id=$_POST['picture_id'];
    $newnoti=$admin->newNotification($notification_type,$notiprofile_id,$notigameweek,$picture_id);
}

//jelszó átírás
if(isset($_POST['changepass'])){
    $passchangeprofil=$_POST['profile_ID'];
    $passchangeemail=$_POST['email'];
    $passchangepass=$_POST['password'];
    $forcechangepass=$webuser->updatePassword($passchangeprofil,$passchangeemail,$passchangepass);
}

//egyes játékosok pontjainak újraszámítása
if (isset($_POST['calc'])){
    $transform = explode('_',$_POST['calc']);
    $compid=end($transform);

    if(isset($_SESSION['checkweek'])){
        $enterpoints=$admin->enterCompetitorPoints($compid,$_SESSION['checkweek'],$_POST["calcpoint_".$compid]);
    }else{$enterpoints=$admin->enterCompetitorPoints($compid,$week,$_POST["calcpoint_".$compid]);}
}

//minden HU játékos pontjainak újraszámítása
if (isset($_POST['calcHuAll'])){
    while($r = $pointcheck->fetch(PDO::FETCH_ASSOC)){
        if($r['league_id']==10){
            $calcpoint=$player->calculatePoints($r);
            if(isset($_SESSION['checkweek'])){
                $enterpoints=$admin->enterCompetitorPoints($r['competitor_id'],$_SESSION['checkweek'],$calcpoint);
            }else{$enterpoints=$admin->enterCompetitorPoints($r['competitor_id'],$week,$calcpoint);}
        }
    }
}

//minden DE játékos pontjainak újraszámítása
if (isset($_POST['calcDeAll'])){
    while($r = $pointcheck->fetch(PDO::FETCH_ASSOC)){
        if($r['league_id']==20){
            $calcpoint=$player->calculatePoints($r);
            if(isset($_SESSION['checkweek'])){
                $enterpoints=$admin->enterCompetitorPoints($r['competitor_id'],$_SESSION['checkweek'],$calcpoint);
            }else{$enterpoints=$admin->enterCompetitorPoints($r['competitor_id'],$week,$calcpoint);}
        }
    }
}

//minden Női DE játékos pontjainak újraszámítása
if (isset($_POST['calcDeWAll'])){
    while($r = $pointcheck->fetch(PDO::FETCH_ASSOC)){
        if($r['league_id']==40){
            $calcpoint=$player->calculatePoints($r);
            if(isset($_SESSION['checkweek'])){
                $enterpoints=$admin->enterCompetitorPoints($r['competitor_id'],$_SESSION['checkweek'],$calcpoint);
            }else{$enterpoints=$admin->enterCompetitorPoints($r['competitor_id'],$week,$calcpoint);}
        }
    }
}

//HU Ranking számítás az adott hétre
if (isset($_POST['HURankingCalc'])){
    $compHUforRanking=$admin->getHUCompetitorsForRanking($_SESSION['checkweek']);
    $rank=0;
    while($r = $compHUforRanking->fetch(PDO::FETCH_ASSOC)){
        $rank++;
        $checkHUrank=$crud->getTeamrank($r['competitor_id'],$_SESSION['checkweek']);
        if(!$checkHUrank){
            $enterRank=$admin->enterCompetitorRank($r['competitor_id'],$_SESSION['checkweek'],$rank);
        }else{
            $enterRank=$admin->updateCompetitorRank($r['competitor_id'],$_SESSION['checkweek'],$rank);
        }
    }
}

//DE Ranking számítás az adott hétre
if (isset($_POST['DERankingCalc'])){
    $compDEforRanking=$admin->getDECompetitorsForRanking($_SESSION['checkweek']);
    $rank=0;
    while($r = $compDEforRanking->fetch(PDO::FETCH_ASSOC)){
        $rank++;
        $checkDErank=$crud->getTeamrank($r['competitor_id'],$_SESSION['checkweek']);
        if(!$checkDErank){
            $enterRank=$admin->enterCompetitorRank($r['competitor_id'],$_SESSION['checkweek'],$rank);
        }else{
            $enterRank=$admin->updateCompetitorRank($r['competitor_id'],$_SESSION['checkweek'],$rank);
        }
    }
}

//DE Női Ranking számítás az adott hétre
if (isset($_POST['DEWRankingCalc'])){
    $compDEWforRanking=$admin->getDEWCompetitorsForRanking($_SESSION['checkweek']);
    $rank=0;
    while($r = $compDEWforRanking->fetch(PDO::FETCH_ASSOC)){
        $rank++;
        $checkDEWrank=$crud->getTeamrank($r['competitor_id'],$_SESSION['checkweek']);
        if(!$checkDEWrank){
            $enterRank=$admin->enterCompetitorRank($r['competitor_id'],$_SESSION['checkweek'],$rank);
        }else{
            $enterRank=$admin->updateCompetitorRank($r['competitor_id'],$_SESSION['checkweek'],$rank);
        }
    }
}

//HU értesítések küldése
if(isset($_POST['HUNotification'])){
    while($r = $pointcheck->fetch(PDO::FETCH_ASSOC)){
        if($r['league_id']==10){
            $newnoti=$admin->newNotification("B1",$r['profile_id'],$_SESSION['checkweek'],0);
        }
    }
}

//DE értesítések küldése
if(isset($_POST['DENotification'])){
    while($r = $pointcheck->fetch(PDO::FETCH_ASSOC)){
        if($r['league_id']==20){
            $newnoti=$admin->newNotification("B2",$r['profile_id'],$_SESSION['checkweek'],0);
        }
    }
}

//DE Női értesítések küldése
if(isset($_POST['DEWNotification'])){
    while($r = $pointcheck->fetch(PDO::FETCH_ASSOC)){
        if($r['league_id']==40){
            $newnoti=$admin->newNotification("B4",$r['profile_id'],$_SESSION['checkweek'],0);
        }
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST') echo '<script type="text/javascript">location.href="redirect.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=redirect.php" /></noscript> ';

$huopen=$hugameweek['open'];
$deopen=$degameweek['open'];
$deWopen=$deWgameweek['open'];
?>

  
  <div class="container" id="fullpage">
    <div class="container" id="gameweek">
        <h5>Jelenlegi forduló</h5>
        <div class="row">
            <div class="col">
                <p>HU:</p>
            </div>
            <div class="col">
                <p><?php echo $huweek?></p>
            </div>
            <div class="col">
                <p><?php if($huopen==0){echo "Zárva";}elseif($huopen==1){echo "Nyitva";}else{echo "miageci";};?></p>
            </div>
            <div class="col">
                <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="huopenclose">
                    <input type="radio" class="btn-check" name="huweekchange" id="huopen" value="open" autocomplete="off" <?php if($huopen==1)echo "checked";?>>
                    <label class="btn btn-outline-success" for="huopen">Open</label>

                    <input type="radio" class="btn-check" name="huweekchange" id="huclose" value="close" autocomplete="off" <?php if($huopen==0)echo "checked";?>>
                    <label class="btn btn-outline-danger" for="huclose">Close</label>
                    <button type="submit" class="btn btn-primary" name="husubmit" value="submitweek">Submit</button>
                </form>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <p>DE:</p>
            </div>
            <div class="col">
                <p><?php echo $deweek?></p>
            </div>
            <div class="col">
                <p><?php if($deopen==0){echo "Zárva";}elseif($deopen==1){echo "Nyitva";}else{echo "miageci";};?></p>
            </div>
            <div class="col">
                <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="deopenclose">
                    <input type="radio" class="btn-check" name="deweekchange" id="deopen" value="open" autocomplete="off" <?php if($deopen==1)echo "checked";?>>
                    <label class="btn btn-outline-success" for="deopen">Open</label>

                    <input type="radio" class="btn-check" name="deweekchange" id="declose" value="close" autocomplete="off" <?php if($deopen==0)echo "checked";?>>
                    <label class="btn btn-outline-danger" for="declose">Close</label>
                    <button type="submit" class="btn btn-primary" name="desubmit" value="submitweek">Submit</button>
                </form>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <p>DE W:</p>
            </div>
            <div class="col">
                <p><?php echo $deWweek?></p>
            </div>
            <div class="col">
                <p><?php if($deWopen==0){echo "Zárva";}elseif($deWopen==1){echo "Nyitva";}else{echo "miageci";};?></p>
            </div>
            <div class="col">
                <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="deWopenclose">
                    <input type="radio" class="btn-check" name="deWweekchange" id="deWopen" value="open" autocomplete="off" <?php if($deWopen==1)echo "checked";?>>
                    <label class="btn btn-outline-success" for="deWopen">Open</label>

                    <input type="radio" class="btn-check" name="deWweekchange" id="deWclose" value="close" autocomplete="off" <?php if($deWopen==0)echo "checked";?>>
                    <label class="btn btn-outline-danger" for="deWclose">Close</label>
                    <button type="submit" class="btn btn-primary" name="deWsubmit" value="submitweek">Submit</button>
                </form>
            </div>
        </div>
    </div>
    <hr>
    <div class="container" id="profiles">
        <table class="table">
            <caption style="caption-side:top">Minden profil csoportonként</caption>  
            <?php $profilelist=$admin->allProfilesByGroups()?>
            <tr>
                <th>típus</th>
                <th>db</th>
            </tr>
            <?php while($r = $profilelist->fetch(PDO::FETCH_ASSOC)){ ?> 
                <tr>
                    <td><?php echo $r['name'];?></td>
                    <td><?php echo $r['count'];?></td>

                </tr>      
                <?php }?>
        </table>
    </div>
    <hr>
    <div class="container">
        <table class="table">
            <caption  style="caption-side:top">Troll profilok kilistázva</caption>
            <?php $trollist=$admin->trollProfiles()?>
            <tr>
                <th>ID</th>
                <th>email</th>
                <th>név</th>
                <th>alias</th>
            </tr>
            <?php while($r = $trollist->fetch(PDO::FETCH_ASSOC)){ ?> 
                <tr>
                    <td><?php echo $r['profile_id'];?></td>
                    <td><?php echo $r['email'];?></td>
                    <td><?php echo $r['profilename'];?></td>
                    <td><?php echo $r['alias'];?></td>

                </tr>      
                <?php }?>
        </table>
    </div>
    <hr>
    <div class="container">
        <h5>Hírlevelek</h5>
        <a class="btn btn-primary" href="newsletter_template_list.php">Newsletter kreátor</a>
    </div>
    <hr>
    <div class="container">
        <h5>Price calculation</h5>
        <a class="btn btn-primary" href="pricecalculation.php">Price calculation</a>
    </div>
    <hr>
    <div class="container">
        <h5>Extra képek és szövegek</h5>
        <table class="table">
            <?php $extrapics=$crud->getExtraPicturesForCheck()?>
            <tr>
                <th>picture_id</th>
                <th>link</th>
                <th>secret</th>
                <th>HU</th>
                <th>EN</th>
                <th>DE</th>
                <th>users</th>
                <th>check</th>
            </tr>
            <?php while($r = $extrapics->fetch(PDO::FETCH_ASSOC)){ 
                $hutext=$crud->getPictureText($r['picture_id'],1);
                $entext=$crud->getPictureText($r['picture_id'],2);
                $detext=$crud->getPictureText($r['picture_id'],3);
                $assignment=$crud->countExtraPictureAssignment($r['picture_id']);
                ?> 
                <tr>
                    <td><?php echo $r['picture_id'];?></td>
                    <td><?php echo $r['link'];?></td>
                    <td><?php if($r['secret']==1){echo "Yes";}else{echo "No";};?></td>
                    <td><?php if($hutext AND strlen($hutext['description'])>0){echo 1; $hu=1;}else{echo 0; $hu=0;};?></td>
                    <td><?php if($entext AND strlen($entext['description'])>0){echo 1; $en=1;}else{echo 0; $en=0;};?></td>
                    <td><?php if($detext AND strlen($detext['description'])>0){echo 1; $de=1;}else{echo 0; $de=0;};?></td>
                    <td><?php if($assignment){echo $assignment['count'];}else{echo 0;};?></td>
                    <td><?php if(($hu+$en+$de)==3){echo '<span style="background-color:green;">&#9786</span>';}elseif(($hu+$en+$de)<3 AND $assignment['count']==0 AND $r['secret']==1){echo '<span style="background-color:yellow;">&#9888</span>';}else{echo '<span style="background-color:red;">&#9760</span>';};?></td>

                </tr>      
                <?php }?>
        </table>
    </div>
    <hr>
    <div class="container">
        <div style="display: flex; justify-content: space-between;">
             <h5>Manuális Értesítések</h5>
             <a class="btn btn-warning" href="notificationcreator.php">Értesítés kreátor</a>
        </div>
       
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="manualnoti">
            <div>
                <label for="notification_type" class="form-label">Notification_type</label>
                <input type="text" class="form-control" id="notification_type" name="notification_type">
            </div>
            <div>
                <label for="profile_id" class="form-label">profile_id</label>
                <input type="text" class="form-control" id="profile_id" name="profile_id">
            </div>
            <div>
                <label for="gameweek" class="form-label">gameweek</label>
                <input type="text" class="form-control" id="gameweek" name="gameweek">
            </div>
            <div>
                <label for="picture_id" class="form-label">picture_id</label>
                <input type="text" class="form-control" id="picture_id" name="picture_id">
            </div>
            <input type="submit" class="btn btn-primary" name="manualnoti" value="manualnoti">
        </form>
    </div>
    <hr>
    <div class="container" style="display:flex">
        <h5>Roster átvitele</h5>
        <?php 
            $hulastweekroster=$admin->countRoster($huweek-1,10);
            $huthisweekroster=$admin->countRoster($huweek,10);
            $hunextweekroster=$admin->countRoster($huweek+1,10);

            $delastweekroster=$admin->countRoster($deweek-1,20);
            $dethisweekroster=$admin->countRoster($deweek,20);
            $denextweekroster=$admin->countRoster($deweek+1,20);

            $deWlastweekroster=$admin->countRoster($deWweek-1,40);
            $deWthisweekroster=$admin->countRoster($deWweek,40);
            $deWnextweekroster=$admin->countRoster($deWweek+1,40);
        ?>
        <div class="container">
            <h6>HU</h6>
            <ul>
                <li>Múlt heti keretek: <?php echo $hulastweekroster['count']?></li>
                <li>Eheti keretek: <?php echo $huthisweekroster['count']?></li>
                <li>Következő heti keretek: <?php echo $hunextweekroster['count']?></li>
            </ul>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="hurostercopy">
                <input type="submit" class="btn-check" name="hurostercopy" value="hurostercopy" id="hurostercopy" onchange="this.form.submit()" onclick="return confirm('Biztosan véglegesíteni szeretnéd a másolást?');" > 
                <label class="btn btn-info" for="hurostercopy">Keretek átmásolása</label>
            </form>
        </div>
        <div class="container">
            <h6>DE</h6>
            <ul>
                <li>Múlt heti keretek: <?php echo $delastweekroster['count']?></li>
                <li>Eheti keretek: <?php echo $dethisweekroster['count']?></li>
                <li>Következő heti keretek: <?php echo $denextweekroster['count']?></li>
            </ul>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="derostercopy">
                <input type="submit" class="btn-check" name="derostercopy" value="derostercopy" id="derostercopy" onchange="this.form.submit()" onclick="return confirm('Biztosan véglegesíteni szeretnéd a másolást?');" > 
                <label class="btn btn-info" for="derostercopy">Keretek átmásolása</label>
            </form>
        </div>
        <div class="container">
            <h6>DE W</h6>
            <ul>
                <li>Múlt heti keretek: <?php echo $deWlastweekroster['count']?></li>
                <li>Eheti keretek: <?php echo $deWthisweekroster['count']?></li>
                <li>Következő heti keretek: <?php echo $deWnextweekroster['count']?></li>
            </ul>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="deWrostercopy">
                <input type="submit" class="btn-check" name="deWrostercopy" value="deWrostercopy" id="deWrostercopy" onchange="this.form.submit()" onclick="return confirm('Biztosan véglegesíteni szeretnéd a másolást?');" > 
                <label class="btn btn-info" for="deWrostercopy">Keretek átmásolása</label>
            </form>
        </div>
    </div>
    <br>
    <div class="container" style="display:flex">
        <h5>Játékosárak átmásolása</h5>
        <?php 
            //itt kellene megnézni, hogy mennyi ár volt előző, jelenlegi és következő hétre a három ligában
            $hulastweekpricecount=$admin->countPrices($huweek-1,10);
            $huthisweekpricecount=$admin->countPrices($huweek,10);
            $hunextweekpricecount=$admin->countPrices($huweek+1,10);

            $delastweekpricecount=$admin->countPrices($deweek-1,20);
            $dethisweekpricecount=$admin->countPrices($deweek,20);
            $denextweekpricecount=$admin->countPrices($deweek+1,20);

            $deWlastweekpricecount=$admin->countPrices($deWweek-1,40);
            $deWthisweekpricecount=$admin->countPrices($deWweek,40);
            $deWnextweekpricecount=$admin->countPrices($deWweek+1,40);
        ?>
        <div class="container">
            <h6>HU</h6>
            <ul>
                <li>Múlt heti árak: <?php echo $hulastweekpricecount['count']?></li>
                <li>Eheti árak: <?php echo $huthisweekpricecount['count']?></li>
                <li>Következő heti árak: <?php echo $hunextweekpricecount['count']?></li>
            </ul>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="hupricescopy">
                <input type="submit" class="btn-check" name="hupricescopy" value="hupricescopy" id="hupricescopy" onchange="this.form.submit()" > 
                <label class="btn btn-info" for="hupricescopy">Játékosárak átmásolása</label>
            </form>
        </div>
        <div class="container">
            <h6>DE</h6>
            <ul>
                <li>Múlt heti árak: <?php echo $delastweekpricecount['count']?></li>
                <li>Eheti árak: <?php echo $dethisweekpricecount['count']?></li>
                <li>Következő heti árak: <?php echo $denextweekpricecount['count']?></li>
            </ul>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="depricescopy">
                <input type="submit" class="btn-check" name="depricescopy" value="depricescopy" id="depricescopy" onchange="this.form.submit()" > 
                <label class="btn btn-info" for="depricescopy">Játékosárak átmásolása</label>
            </form>
        </div>
        <div class="container">
            <h6>DE W</h6>
            <ul>
                <li>Múlt heti árak: <?php echo $deWlastweekpricecount['count']?></li>
                <li>Eheti árak: <?php echo $deWthisweekpricecount['count']?></li>
                <li>Következő heti árak: <?php echo $deWnextweekpricecount['count']?></li>
            </ul>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="deWpricescopy">
                <input type="submit" class="btn-check" name="deWpricescopy" value="deWpricescopy" id="deWpricescopy" onchange="this.form.submit()" > 
                <label class="btn btn-info" for="deWpricescopy">Játékosárak átmásolása</label>
            </form>
        </div>
    </div>
    <hr>
    <span>Trollteke kérdések - hidden</span>
    <div class="container" style="display: none;">
        <h5>Új trollteke kérdés hozzáadása</h5>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="trollbet">
            <div>
                <label for="questionweek" class="form-label">gameweek</label>
                <input type="text" class="form-control" id="questionweek" name="questionweek">
            </div>
            <div>
                <label for="question" class="form-label">kérdés</label>
                <input type="text" class="form-control" id="question" name="question">
            </div>
            <div>
                <label for="questiontype" class="form-label">típus</label>
                <input type="text" class="form-control" id="questiontype" name="questiontype">
            </div>
            <input type="submit" class="btn btn-primary" name="newquestion" value="newquestion">
            
        </form>
    </div>
    <hr>
    <span>Jelszó megváltoztatás - hidden</span>
    <div class="container" style="display: none;">
        <h5>Jelszó megváltoztatása</h5>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="changepassword">
            <div>
                <label for="profile_ID" class="form-label">profile id</label>
                <input type="text" class="form-control" id="profile_ID" name="profile_ID">
            </div>
            <div>
                <label for="email" class="form-label">email</label>
                <input type="text" class="form-control" id="email" name="email">
            </div>
            <div>
                <label for="password" class="form-label">password</label>
                <input type="text" class="form-control" id="password" name="password">
            </div>
            <input type="submit" class="btn btn-danger" name="changepass" value="changepass">
            
        </form>
    </div>
    <hr>

    <div class="container">
        <h5>Pontszámítás</h5>
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="pointcalc">
            <p>Forduló:</p>
            <input type="text" class="form-control" id="week" name="week" style="border-color:darkblue; width:unset;" <?php if(isset($_SESSION['checkweek'])){echo 'value="'.$_SESSION['checkweek'].'"';}else echo 'value="'.$huweek.'"';?>>
            <button type="submit" class="btn btn-primary" name="checkweek" value="checkweek">mehet</button>
        </form>
        <h6>Pontszámítások:</h6>
        <div class="masscalcdiv" style="display:flex;gap:15px;">
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="calcHuAll">
                <button type="submit" class="btn btn-success" name="calcHuAll" value="calcHuAll">HU Pontok</button>
            </form>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="HURankingCalc">
                <button type="submit" class="btn btn-success" name="HURankingCalc" value="HURankingCalc">HU Helyezések</button>
            </form>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="HUNotification">
                <button type="submit" class="btn btn-success" name="HUNotification" value="HUNotification">HU Értesítés</button>
            </form>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="calcDeAll">
                <button type="submit" class="btn btn-warning" name="calcDeAll" value="calcDeAll">DE Pontok</button>
            </form>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="DERankingCalc">
                <button type="submit" class="btn btn-warning" name="DERankingCalc" value="DERankingCalc">DE Helyezések</button>
            </form>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="DENotification">
                <button type="submit" class="btn btn-warning" name="DENotification" value="DENotification">DE Értesítés</button>
            </form>

            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="calcDeWAll">
                <button type="submit" class="btn btn-info" name="calcDeWAll" value="calcDeWAll">DE W Pontok</button>
            </form>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="DEWRankingCalc">
                <button type="submit" class="btn btn-info" name="DEWRankingCalc" value="DEWRankingCalc">DE W Helyezések</button>
            </form>
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="DEWNotification">
                <button type="submit" class="btn btn-info" name="DEWNotification" value="DEWNotification">DE W Értesítés</button>
            </form>
        </div>
        

        <table class="table caption-top">
            <tr>
                <th>id</th>
                <th>league</th>
                <th>beírt pont</th>
                <th>p1</th>
                <th>p2</th>
                <th>p3</th>
                <th>p4</th>
                <th>p5</th>
                <th>p6</th>
                <th>p7</th>
                <th>p8</th>
                <th>CSK</th>
                <th>számított pont</th>
                <th></th>
            </tr>
            <?php while($r = $pointcheck->fetch(PDO::FETCH_ASSOC)){ ?>
                <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="weeklypoint_<?php echo $r['competitor_id'];?>">
                <?php 
                    if(isset($_SESSION['checkweek'])){$weekforrow=$_SESSION['checkweek'];}else{$weekforrow=$week;}
                    $p1p=$player->getWeeklyPlayerpoints($r['player1'],$weekforrow);
                    $p2p=$player->getWeeklyPlayerpoints($r['player2'],$weekforrow);
                    $p3p=$player->getWeeklyPlayerpoints($r['player3'],$weekforrow);
                    $p4p=$player->getWeeklyPlayerpoints($r['player4'],$weekforrow);
                    $p5p=$player->getWeeklyPlayerpoints($r['player5'],$weekforrow);
                    $p6p=$player->getWeeklyPlayerpoints($r['player6'],$weekforrow);
                    $p7p=$player->getWeeklyPlayerpoints($r['player7'],$weekforrow);
                    $p8p=$player->getWeeklyPlayerpoints($r['player8'],$weekforrow);
                    $cskp=$player->getWeeklyPlayerpoints($r['captain'],$weekforrow);
                    if(!$p1p){$p1=0;}else{$p1=$p1p['weekpoints'];}
                    if(!$p2p){$p2=0;}else{$p2=$p2p['weekpoints'];}
                    if(!$p3p){$p3=0;}else{$p3=$p3p['weekpoints'];}
                    if(!$p4p){$p4=0;}else{$p4=$p4p['weekpoints'];}
                    if(!$p5p){$p5=0;}else{$p5=$p5p['weekpoints'];}
                    if(!$p6p){$p6=0;}else{$p6=$p6p['weekpoints'];}
                    if(!$p7p){$p7=0;}else{$p7=$p7p['weekpoints'];}
                    if(!$p8p){$p8=0;}else{$p8=$p8p['weekpoints'];}
                    if(!$cskp){$csk=0;}else{$csk=$cskp['weekpoints'];}

                    $calculatedpoints=0;
                    $missedp=0;
                    if($p1==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p1;};
                    if($p2==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p2;};
                    if($p3==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p3;};
                    if($p4==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p4;};
                    if($p5==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p5;};
                    if($p6==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p6;};
                    if($missedp==1 AND $p7>0){$calculatedpoints=$calculatedpoints+$p7;}elseif($missedp==1 AND $p7==0){$calculatedpoints=$calculatedpoints+$p8;}elseif($missedp>1){$calculatedpoints=$calculatedpoints+$p7+$p8;};
                    $calculatedpoints=$calculatedpoints+$csk;
                    ?>
                <tr>
                    <td><?php echo $r['competitor_id'];?></td>
                    <td><?php switch($r['league_id']){
                        case 10: echo "HU";
                        break;
                        case 20: echo "DE";
                        break;
                        case 40: echo "DE W";
                        break;
                    };?></td>
                    <td><?php $wp=$crud->getWeeklyteamresult($r['competitor_id'],$weekforrow); if(!$wp){echo 0;}else{echo $wp['weeklypoints'];}?></td>
                    <td><?php echo $p1;?></td>
                    <td><?php echo $p2;?></td>
                    <td><?php echo $p3;?></td>
                    <td><?php echo $p4;?></td>
                    <td><?php echo $p5;?></td>
                    <td><?php echo $p6;?></td>
                    <td><?php echo $p7;?></td>
                    <td><?php echo $p8;?></td>
                    <td><?php echo $csk;?></td>
                    <td><input type="text" class="form-control" name="calcpoint_<?php echo $r['competitor_id'];?>" value="<?php echo $calculatedpoints;?>"></td>
                    <td>
                        <button type="submit" class="btn btn-primary" name="calc" value="calc_<?php echo $r['competitor_id'];?>" <?php if(!$wp){}elseif(abs($wp['weeklypoints']-$calculatedpoints)<0.01)echo 'disabled'; ?>>Újraszámítás</button>
                    </td>
                </tr>
                </form>
            <?php }?>        
        </table>
    </div>

    <script src="scroll.js"></script>
  </div>