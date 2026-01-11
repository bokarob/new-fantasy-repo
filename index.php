<?php 

$title = "Kezdőlap";
require_once 'includes/header.php';
require_once 'db/conn.php';



if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['email'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sqlemail=$webuser->getUserdetailsbyemail($email); //ez a sor azért kell, hogy pontosan azt az emailt hozza, ami az adatbázisban van
    if(!$sqlemail){
        switch($_SESSION['lang']){
            case 1: echo '<div class="alert alert-danger">Helytelen email cím</div>';
            break;
            case 2: echo '<div class="alert alert-danger">Wrong email address</div>';
            break;
            case 3: echo '<div class="alert alert-danger">Falsche E-Mail-Adresse</div>';
            break;
        }
    }else{
        $new_password = md5($password.$sqlemail['email']);
    

        $result = $webuser->getUser($email,$new_password);

        if(!$result){
            switch($_SESSION['lang']){
                case 1: echo '<div class="alert alert-danger">Helytelen jelszó </div>';
                break;
                case 2: echo '<div class="alert alert-danger">Wrong password</div>';
                break;
                case 3: echo '<div class="alert alert-danger">Falsches Passwort</div>';
                break;
            }
        }else{
          if(empty($result['reg_token_hash'])){
            
            $_SESSION['email'] = $email;
            $_SESSION['profile_id'] = $result['profile_id'];
            $_SESSION['alias'] = $result['alias'];
            $_SESSION['profilename'] = $result['profilename'];        
            $_SESSION['authorization'] = $result['authorization']; 
            $_SESSION['lang'] = $result['lang_id'];
            //header('Location: index.php');
            echo '<script type="text/javascript">location.href="index.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

          }elseif($result['reg_token_hash'] == '1'){
            $_SESSION['email'] = $email;
            $_SESSION['profile_id'] = $result['profile_id'];
            $_SESSION['alias'] = $result['alias'];
            $_SESSION['profilename'] = $result['profilename'];        
            $_SESSION['authorization'] = $result['authorization']; 
            $_SESSION['lang'] = $result['lang_id'];
            //header('Location: profilecheck.php');
            echo '<script type="text/javascript">location.href="profilecheck.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=profilecheck.php" /></noscript> ';
            
          }else{
            echo '<script type="text/javascript">location.href="waiting-confirmation.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=waiting-confirmation.php" /></noscript> ';
          }
            
        }
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['league'])){
  switch ($_POST['league']) {
    case '10':
      $_SESSION['league']=10;
      $competitorinleague=$crud->getCompetitorInLeague($_SESSION['profile_id'],$_SESSION['league']);
      if($competitorinleague['count'] > 0){
        $teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
        $_SESSION['competitor_id']=$teamrequest['competitor_id'];
      }else{unset($_SESSION['competitor_id']);}
      echo '<script type="text/javascript">location.href="myteam.php";</script>
      <noscript><meta http-equiv="refresh" content="0; URL=myteam.php" /></noscript> ';
      break;
    
    case '20':
      $_SESSION['league']=20;
      $competitorinleague=$crud->getCompetitorInLeague($_SESSION['profile_id'],$_SESSION['league']);
      if($competitorinleague['count'] > 0){
        $teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
        $_SESSION['competitor_id']=$teamrequest['competitor_id'];
      }else{unset($_SESSION['competitor_id']);}
      echo '<script type="text/javascript">location.href="myteam.php";</script>
      <noscript><meta http-equiv="refresh" content="0; URL=myteam.php" /></noscript> ';
      break;
    
    case '40':
      $_SESSION['league']=40;
      $competitorinleague=$crud->getCompetitorInLeague($_SESSION['profile_id'],$_SESSION['league']);
      if($competitorinleague['count'] > 0){
        $teamrequest=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
        $_SESSION['competitor_id']=$teamrequest['competitor_id'];
      }else{unset($_SESSION['competitor_id']);}
      echo '<script type="text/javascript">location.href="myteam.php";</script>
      <noscript><meta http-equiv="refresh" content="0; URL=myteam.php" /></noscript> ';
      break;
  }
}

if(isset($_GET['holmes'])){
  $check=$crud->findExtraPicture(75,$_SESSION['profile_id']);
  if($check['count']==0){
    $newpic=$crud->newExtraPicture($_SESSION['profile_id'],75,1);
    if($newpic){ 
        $newnotification=$crud->newPictureNotification('A1',$_SESSION['profile_id'],1,75);
    }
  }
  
}

$areweready=0;



?>
<style>

.loginbox {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  position: relative;
  margin-top: 2rem;
  
  
  font-family: "Roboto", helvetica, arial, sans-serif;
  font-size: 1.5em;
  
  &:before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    height: 100%;
    width: 100%;
  
  }
}

.login-form {
  width: 100%;
  padding: 2em;
  position: relative;
  background: rgba(black, .15);
  
  &:before {
    content: '';
    position: absolute;
    top: -2px; left: 0;
    height: 2px; width: 100%;
    
    background: linear-gradient(
      to right,
      #35c3c1,
      #00d6b7
    );    
  }
  
  @media screen and (min-width: 600px) {
    width: 50vw;
    max-width: 15em;
  }
}

  .flex-row {
    display: flex;
    margin-bottom: 1em;
  }
  
  .lf--label {
    width: 2em;
    display: flex;
    align-items: center;
    justify-content: center;
    
    background: #f5f6f8;
    cursor: pointer;
  }
  .lf--input {
    flex: 1;
    padding: 1em;
    border: 0;
    color: #8f8f8f;
    font-size: 1rem;

    &:focus {
      outline: none;
      transition: transform .15s ease;
      transform: scale(1.1);
    }
  }

  .lf--submit {
    display: block;
    padding: 1em;
    width: 100%;
    
    background: linear-gradient(
      to right,
      #35c3c1,
      #00d6b7
    );
    border: 0;
    color: #fff;
    cursor: pointer;
    font-size: .75em;
    font-weight: 600;
    text-shadow: 0 1px 0 rgba(black, .2);
    max-width: 15em;
    margin: auto;
    
    &:focus {
      outline: none;
      transition: transform .15s ease;
      transform: scale(1.1);
    }
  }

  .lf--reg{
    display: block;
    padding: 1em;
    width: 100%;
    
    background: linear-gradient(
      to right,
      #35c3c1,
      #00d6b7
    );
    border: 0;
    color: #fff;
    cursor: pointer;
    font-size: .75em;
    font-weight: 600;
    text-shadow: 0 1px 0 rgba(black, .2);
    max-width: 15em;
    
    &:focus {
      outline: none;
      transition: transform .15s ease;
      transform: scale(1.1);
    }
  }

.lf--forgot {
  margin-top: 1em;
  color: #00d6b7;
  font-size: .65em;
  text-align: center;
  position: relative;
}

.lf--reg {
  display: block;
  width: 100%;
  max-width: 15em;
  color: #00d6b7;
  background: linear-gradient(
      to right,
      #34b2e5,
      #0ce5ec
    );
  color: #fff;
  cursor: pointer;
  font-size: .75em;
  font-weight: 600;
  text-shadow: 0 1px 0 rgba(black, .2);
  padding: 1em;
  text-decoration: none;
  text-align: center;
  position: relative;
  &:focus {
      outline: none;
      transition: transform .15s ease;
      transform: scale(1.1);
    }
}

::placeholder { color: #8f8f8f; }

.leagueselection{
  display: flex;
  flex-direction: column;
  margin-top: 5rem;
  align-items: center;
}

.league{
  margin: 1rem;
}

.card{
  padding: 1rem;
  width:180px;
  height: 180px;
}

.card-img-top{
  height: 95px;
  object-fit: scale-down;
}


.indexcontainer {
    display: flex;
    flex-wrap: wrap;
    padding: 20px;
}

.leagueselection {
    flex: 1;
    /* min-width: 300px;
    padding: 20px; */
}

.news-feed {
    width: 300px;
    margin-left: 20px;
}

.news-feed h2 {
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.news-article {
    margin-bottom: 20px;
    padding: 5px;
    border: 1px solid lightgray;
    display: flex;
    align-items: center;
}

.news-image {
    width: 100%;
    height: auto;
    max-width: 100px;
    float: left;
    margin-right: 10px;
    aspect-ratio: 1.5;
}

.news-article h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
}

.news-article p {
    margin: 0;
    font-size: 14px;
    color: #555;
}

.articles a{
  text-decoration: none;
  color: inherit;
}

.notification-section {
    width: 300px;
    margin-left: 20px;
    margin-bottom: 2rem;
}

.notification-section h2 {
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.noti-item {
    margin-bottom: 10px;
    padding: 5px;
    border: 1px solid lightgray;
    display: flex;
    align-items: center;
    background-color: #fafad2;
}

.noti-image {
    width: 100%;
    height: auto;
    max-width: 40px;
    float: left;
    margin-right: 10px;
}

.noti-item h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
}

.noti-item p {
    margin: 0;
    font-size: 13px;
    font-style: italic;
    color: #555;
}

.notifications a{
  text-decoration: none;
  color: inherit;
}

@media (max-width: 700px) {
  .leagueselection{
    margin-top: 2rem;
  }
  .indexcontainer{
    padding:0;
  }
  .league{
    margin:0.3rem;
  }
  .card{
    width: 80px;
    height: 90px;
    padding:0.5rem;
  }
  .card-img-top{
    height: 50px;
    width: 50px;
  }
  .card-body{
    padding: 5px;
  }
  .card-title{
    font-size: 10px;

  }
  .articles{
    display:flex;
    flex-wrap: wrap;
    gap:10px;
    padding: 10px;
  }
  .articles a{
    flex: 48%;
    display:flex;
    gap:10px;
    max-width: 48%;
  }
  
  .news-feed {
    width: 100%;
    margin-left: 0;
    margin-top: 5rem;
  }

  .news-article{
    max-width: 100%;
    flex-direction: column;
    align-items: baseline;
  }

  .news-image {
    max-width: 100%;
    max-height: calc(width*0,66);
    float: none;
    margin: 0 0 10px 0;
  }

  .notification-section{
    margin-top: 3rem;
    margin-left: 0;
    width: 100%;
  }

}

</style>

<?php
$comingsoon=false;

if($comingsoon){
  require_once 'includes/startpage.php';
}else{

?>

<?php if(!isset($_SESSION['profile_id'])){ ?>
    <div class="loginbox">
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class='login-form'>
            <div class="flex-row">
                <label class="lf--label" for="username">
                <svg x="0px" y="0px" width="12px" height="13px">
                    <path fill="#B1B7C4" d="M8.9,7.2C9,6.9,9,6.7,9,6.5v-4C9,1.1,7.9,0,6.5,0h-1C4.1,0,3,1.1,3,2.5v4c0,0.2,0,0.4,0.1,0.7 C1.3,7.8,0,9.5,0,11.5V13h12v-1.5C12,9.5,10.7,7.8,8.9,7.2z M4,2.5C4,1.7,4.7,1,5.5,1h1C7.3,1,8,1.7,8,2.5v4c0,0.2,0,0.4-0.1,0.6 l0.1,0L7.9,7.3C7.6,7.8,7.1,8.2,6.5,8.2h-1c-0.6,0-1.1-0.4-1.4-0.9L4.1,7.1l0.1,0C4,6.9,4,6.7,4,6.5V2.5z M11,12H1v-0.5 c0-1.6,1-2.9,2.4-3.4c0.5,0.7,1.2,1.1,2.1,1.1h1c0.8,0,1.6-0.4,2.1-1.1C10,8.5,11,9.9,11,11.5V12z"/>
                </svg>
                </label>
                <input id="username" class='lf--input' name="email" placeholder='<?php switch($_SESSION['lang']){
                    case 1: echo "Email cím";
                    break;
                    case 2: echo "Email address";
                    break;
                    case 3: echo "E-Mail-Adresse";
                    break;
                }?>' type='text' value="<?php if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['email'])) echo $_POST['email'];?>">
            </div>
            <div class="flex-row">
                <label class="lf--label" for="password">
                <svg x="0px" y="0px" width="15px" height="5px">
                    <g>
                    <path fill="#B1B7C4" d="M6,2L6,2c0-1.1-1-2-2.1-2H2.1C1,0,0,0.9,0,2.1v0.8C0,4.1,1,5,2.1,5h1.7C5,5,6,4.1,6,2.9V3h5v1h1V3h1v2h1V3h1 V2H6z M5.1,2.9c0,0.7-0.6,1.2-1.3,1.2H2.1c-0.7,0-1.3-0.6-1.3-1.2V2.1c0-0.7,0.6-1.2,1.3-1.2h1.7c0.7,0,1.3,0.6,1.3,1.2V2.9z"/>
                    </g>
                </svg>
                </label>
                <input id="password" class='lf--input' name="password" placeholder='<?php switch($_SESSION['lang']){
                    case 1: echo "Jelszó";
                    break;
                    case 2: echo "Password";
                    break;
                    case 3: echo "Passwort";
                    break;
                }?>' type='password'>
            </div>
            <input class='lf--submit' type='submit' value='<?php switch($_SESSION['lang']){
            case 1: echo "Bejelentkezés";
            break;
            case 2: echo "Login";
            break;
            case 3: echo "Anmeldung";
            break;
        }?>'>
        </form>
        <a class='lf--reg' href='signup.php'><?php switch($_SESSION['lang']){
            case 1: echo "Regisztráció";
            break;
            case 2: echo "Sign up";
            break;
            case 3: echo "Registrieren";
            break;
        }?></a>
        <a class='lf--forgot' href='forgot-password.php'><?php switch($_SESSION['lang']){
            case 1: echo "Elfelejtett jelszó?";
            break;
            case 2: echo "Forgot password?";
            break;
            case 3: echo "Passwort vergessen?";
            break;
        }?></a>
    </div>

<?php }else{ ?>

<div class="indexcontainer">
  <div class="leagueselection">
    <h4>
      <?php switch($_SESSION['lang']){
              case 1: echo "Válassz bajnokságot";
              break;
              case 2: echo "Select league";
              break;
              case 3: echo "Liga auswählen";
              break;
          }?>
    </h4>
    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post"> 
      <button class="league" type="submit" name="league" value=10>
        <div class="card">
          <img class="card-img-top" src="img\matesz.png" alt="">
          <div class="card-body">
            <h5 class="card-title">Szuperliga</h5>
          </div>
        </div>
      </button>
      <button class="league" type="submit" name="league" value=20>
        <div class="card">
          <img class="card-img-top" src="img\dkbc.png" alt="">
          <div class="card-body">
            <h5 class="card-title">Bundesliga Men</h5>
          </div>
        </div>
      </button>
      <button class="league" type="submit" name="league" value=40>
        <div class="card">
          <img class="card-img-top" src="img\dkbc.png" alt="">
          <div class="card-body">
            <h5 class="card-title">Bundesliga Women</h5>
          </div>
        </div>
      </button>
      
    </form> 
  </div>

  <?php 
  $notificationlist = $crud->getNotificationTypesForUser($_SESSION['profile_id'],$_SESSION['lang']);
  $getNews = $crud->fetchNews($_SESSION['lang']);
  $newsArticles=$getNews->fetchAll();
  if($notificationlist->rowCount()>0 OR !empty($newsArticles)){
    //ha van vagy értesítés vagy hírek, akkor lesz ez a panel
  ?>
  <div class="sidepanel">

    <?php 
    if($notificationlist->rowCount()>0){
    ?>
    <div class="notification-section">
      <h2><?php switch($_SESSION['lang']){
              case 1: echo "Értesítések";
              break;
              case 2: echo "Notifications";
              break;
              case 3: echo "Benachrichtigungen";
              break;
          }?></h2>
      <div class="notifications">
        <?php
        foreach ($notificationlist as $notification) {
          ?>
          <a id="notibox" href="<?php echo $notification['navigation'].'?notype='.$notification['notification_type'];?>">
            <div class='noti-item'>
              <img src='img/notification-bell.svg' alt='' class='noti-image'>
              <div class='noti-text'>
                <p><?= $notification['text']; ?></p>
              </div>
            </div>
          </a>
        <?php }?>
      </div>
    </div>
    <?php }?>

    <?php 
    if(!empty($newsArticles)){
    ?>
    <div class="news-feed">
      <h2><?php switch($_SESSION['lang']){
              case 1: echo "Hírek";
              break;
              case 2: echo "News";
              break;
              case 3: echo "Nachrichten";
              break;
          }?></h2>
      <div class="articles">
        <?php
        

        foreach ($newsArticles as $article) {
          ?>
          <a id="newsbox" href="news.php?newsid=<?php echo $article['news_id'];?>">
            <div class='news-article'>
              <img src='img/news/<?= $article['image']; ?>' alt='' class='news-image'>
              <div class='news-text'>
                <h3><?= $article['newstitle']; ?></h3>
                <p><?= $article['short_description']; ?></p>
              </div>
            </div>
          </a>
        <?php }?>
      </div>
    </div>
  </div>
  
  <?php }}?>
</div>
  


<?php } ?>
<br>
<br>
<br>
<br>
<?php   require_once 'includes/footer_new2.php';

}?>





 