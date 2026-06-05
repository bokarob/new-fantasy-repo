<?php 
$title = "Szavazás";
require_once 'includes/header.php';
require_once 'db/conn.php';

//ha nem vagy bejelentkezve bye-bye
if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

//megnézzük értesítésből navigált-e ide. Ha nem, akkoris maradhat, ha van az adott ligára vonatkozó csapata
if(isset($_GET['notype'])){
    switch ($_GET['notype']) {
        case 'C1':
          $_SESSION['league']=10;
          $comp=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
          $_SESSION['competitor_id']=$comp['competitor_id'];
          break;    
        case 'C2':
          $_SESSION['league']=20;
          $comp=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
          $_SESSION['competitor_id']=$comp['competitor_id'];
          break;
        case 'C4':
          $_SESSION['league']=40;
          $comp=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
          $_SESSION['competitor_id']=$comp['competitor_id'];
          break;
      }
}elseif(isset($_SESSION['league'])){
    $compcount=$crud->getCompetitorInLeague($_SESSION['profile_id'],$_SESSION['league']);
    if($compcount['count']>0){
        $comp=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
        $_SESSION['competitor_id']=$comp['competitor_id'];    
    }else{
        echo '<script type="text/javascript">location.href="index.php";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
    }
}else{
    echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
}

$surveynum=$crud->getOpenSurveyNumber($_SESSION['league']);

$surveys=$crud->getSurveys($_SESSION['league']);



if(isset($_POST['polls'])){
    $_SESSION['survey_id']=$_POST['polls'];
}
if(isset($_SESSION['survey_id'])){
    $survey=$crud->getSurveybyID($_SESSION['survey_id']);
}

if(isset($_POST['vote'])){
    $checkanswer=$crud->checkVote($_POST['vote'],$_SESSION['competitor_id']);
    if(empty($checkanswer)){
        $registervote=$crud->insertVote($_POST['vote'],$_SESSION['competitor_id'],$_POST['optionselected']);
    }else{
        $registervote=$crud->updateVote($_POST['vote'],$_SESSION['competitor_id'],$_POST['optionselected']);
    }
    //olvasottra tesszük az értesítést
    switch ($_SESSION['league']) {
        case 10:
            $notification=$crud->getPointsNotificationForUser($_SESSION['profile_id'],"C1");
            $markread=$crud->markNotificationAsRead($notification['notification_id']);
            break;
        
        case 20:
            $notification=$crud->getPointsNotificationForUser($_SESSION['profile_id'],"C2");
            $markread=$crud->markNotificationAsRead($notification['notification_id']);
            break;

        case 40:
            $notification=$crud->getPointsNotificationForUser($_SESSION['profile_id'],"C4");
            $markread=$crud->markNotificationAsRead($notification['notification_id']);
            break;
    }
    echo '<script type="text/javascript">location.href="redirect.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=redirect.php" /></noscript> ';
}

?>

<style>
.wrapper{
  padding: 20px;
  background: #fff;
  max-width: 500px;
  width: 100%;
  margin:auto;
  margin-top: 3rem;
  box-shadow: 1px 1px 5px 1px rgba(0,0,0,0.1);

}
/* @media (max-width:675px){
    .wrapper{
        max-width: 300px;
}
} */

.wrapper header{
  font-size: 22px;
  font-weight: 600;
}
.wrapper .poll-area{
  margin: 20px 0 15px 0;
}

.wrapper p{
    font-style: italic;
    font-size: 12px;
}
.poll-area label{
  display: block;
  margin-bottom: 10px;
  padding: 8px 15px;
  /* border: 2px solid #e6e6e6; */
  transition: all 0.2s ease;
}

label .row{
  display: flex;
  pointer-events: none;
  justify-content: space-between;
}
label .row .column{
  display: flex;
  align-items: center;
}
label .row .circle{
  height: 19px;
  width: 19px;
  display: block;
  border: 2px solid #ccc;
  border-radius: 50%;
  margin-right: 10px;
  position: relative;
}
label .row .circle::after{
  content: "";
  height: 11px;
  width: 11px;
  border-radius: inherit;
  position: absolute;
  left: 2px;
  top: 2px;
  display: none;
}
.poll-area label:hover .row .circle::after{
  display: block;
  background: #e6e6e6;
}
label.selected .row .circle::after{
  display: block;
}
label .row span{
  font-size: 16px;
  font-weight: 500;
}
#polloption{
    font-size: 12px;
}
#vote{
    margin-top: 2rem;
}
#pollselector{
    max-width: 100%;
    
}
.polloptions{
    display:inline-block;
}
</style>

<?php if($surveynum['surveycount']==0){?>

    <h3>
      <?php switch($_SESSION['lang']){
        case 1: echo "Jelenleg nincs élő szavazás az oldalon.";
        break;
        case 2: echo "No live polls currently";
        break;
        case 3: echo "Es gibt derzeit keine Live-Umfragen";
        break;
      }?>
    </h3>


<?php }elseif($surveynum['surveycount']>1){?>
<p>
  <?php switch($_SESSION['lang']){
    case 1: echo "Aktuális élő szavazások:";
    break;
    case 2: echo "Live polls:";
    break;
    case 3: echo "Live-Umfragen:";
    break;
  }?>
</p>
<div id="pollselector">
    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="pollselect">

        <?php while($r = $surveys->fetch(PDO::FETCH_ASSOC)){ ?> 
            <div class="polloptions">
                <input type="radio" class="btn-check" name="polls" id="<?php echo $r['survey_id'] ?>" value="<?php echo $r['survey_id'] ?>" autocomplete="off" onchange="this.form.submit()" <?php if(isset($_SESSION['survey_id']) AND $_SESSION['survey_id']==$r['survey_id'])echo "checked";?>>
                <label class="btn btn-outline-secondary" for="<?php echo $r['survey_id'] ?>" id="polloption">
                  <?php 
                    switch($_SESSION['lang']){
                      case 1: echo $r['topicHU'];
                      break;
                      case 2: echo $r['topicEN'];
                      break;
                      case 3: echo $r['topicDE'];
                      break;
                    } 
                  ?>
                </label>
            </div>    
        <?php }?>
    </form>
</div>


<?php }elseif($surveynum['surveycount']==1){$survey=$surveys->fetch(PDO::FETCH_ASSOC);} ?>


<?php //itt kezdődik az oldal tartalma ?>


<?php if($surveynum['surveycount']>1 AND !isset($_SESSION['survey_id'])){echo '';}
            elseif($surveynum['surveycount']==0){echo '';}
            else{ ?>
            
<div class="wrapper">

  <h2>
    <?php 
      switch($_SESSION['lang']){
        case 1: echo $survey['questionHU'];
        break;
        case 2: echo $survey['questionEN'];
        break;
        case 3: echo $survey['questionDE'];
        break;
      }
    ?>
  </h2>
  
    <?php
        $options=$crud->getOptions($survey['survey_id']);
        $checkanswer=$crud->checkVote($survey['survey_id'],$_SESSION['competitor_id']);
        if(!empty($checkanswer)){
          switch($_SESSION['lang']){
            case 1: echo "<p>Erre a kérdésre már leadtad a szavazatod, de megváltoztathatod a határidő lejártáig.</p>";
            break;
            case 2: echo "<p>You already voted for this topic, but you can change your vote until the deadline.</p>";
            break;
            case 3: echo "<p>Du hast bereits für dieses Thema abgestimmt, aber du kannst deine Stimme bis zur Frist ändern.</p>";
            break;
          }
        }
        $deadline=explode("-", $survey['end_date']);
        switch($_SESSION['lang']){
            case 1: $honapok = Array( "", "január" , "február"  , "március"   ,"április", "május"    , "június"    ,"július" , "augusztus", "szeptember","október", "november" , "december"    );
            break;
            case 2: $honapok = Array( "", "January" , "February"  , "March"   ,"April", "May"    , "June"    ,"July" , "August", "September","October", "November" , "December"    );
            break;
            case 3: $honapok = Array( "", "Januar" , "Februar"  , "März"   ,"April", "Mai"    , "Juni"    ,"Juli" , "August", "September","Oktober", "November" , "Dezember"    );
            break;
        }
    ?>

    <h6>
      <?php switch($_SESSION['lang']){
        case 1:
        ?>
        Határidő: <?php echo $deadline[0].". ".$honapok[number_format($deadline[1],)]." ".$deadline[2]."."?>
        <?php ;
        break;

        case 2:
        ?>
        Deadline: <?php $newdate=date('jS F Y', strtotime($survey['end_date'])); echo $newdate?>
        <?php ;
        break;

        case 3:
        ?>
        Abstimmungsfrist: <?php echo $deadline[2].". ".$honapok[number_format($deadline[1],)]." ".$deadline[0]."."?>
        <?php ;
        break;
      }?>
    </h6>

    <div class="poll-area">
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="answersurvey">
            
                <?php while($r = $options->fetch(PDO::FETCH_ASSOC)){ ?>
                    
                            <input type="radio" class="btn-check" name="optionselected" id="opt-<?php echo $r['option_id'] ?>" value="<?php echo $r['option_id'] ?>" autocomplete="off" <?php if(!empty($checkanswer) AND $checkanswer['option_id']==$r['option_id']){echo "checked";}?>>
                            <label class="btn btn-outline-<?php if(!empty($checkanswer) AND $checkanswer['option_id']==$r['option_id']){echo "info";}else{echo "secondary";}?>" for="opt-<?php echo $r['option_id'] ?>">
                                <div class="row">
                                    <div class="column">
                                        <span class="circle"></span>
                                        <span class="text">
                                          <?php 
                                            switch($_SESSION['lang']){
                                              case 1: echo $r['answerHU'];
                                              break;
                                              case 2: echo $r['answerEN'];
                                              break;
                                              case 3: echo $r['answerDE'];
                                              break;
                                            } 
                                          ?>
                                        </span>
                                    </div>
                                </div>
                            </label>
                        
                <?php }?>
                <button type="submit" class="btn btn-dark" name="vote" value="<?php echo $survey['survey_id']; //a vote button értékével adjuk át a survey_id-t a formban, hogy az adatbázis funciónál egyértelmű legyen?>" id="vote">
                  <?php 
                  if(empty($checkanswer)){
                    switch($_SESSION['lang']){
                      case 1: echo "Szavazok";
                      break;
                      case 2: echo "Vote";
                      break;
                      case 3: echo "Abstimmen";
                      break;}
                  }else{
                    switch($_SESSION['lang']){
                      case 1: echo "Megváltoztatom a szavazatom";
                      break;
                      case 2: echo "Change my vote";
                      break;
                      case 3: echo "Meine Stimme ändern";
                      break;}
                  }?>
                </button>
            
        </form>
    </div>
    <?php } ?>
</div>

<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>