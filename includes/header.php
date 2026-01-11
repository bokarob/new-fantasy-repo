<?php 
include_once 'includes/session.php'; 
require_once 'db/conn.php';

if(isset($_SESSION['profile_id'])){
  $felhaszn=$webuser->getUserbyID($_SESSION['profile_id']);
}

$started=true;

if(!isset($_SESSION['lang'])){
  if(isset($_SESSION['profile_id'])){
    $_SESSION['lang']=$felhaszn['lang_id'];
  }else{
    $_SESSION['lang']=1;
    //require_once 'geoip.php';
  }
}

if(isset($_POST['language'])){
  $_SESSION['lang']=$_POST['language'];
}
?>

<!doctype html>
<html lang="hu">
  <head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-R42MENMLL6"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
    
      gtag('config', 'G-R42MENMLL6');
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
    
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:ital,wght@1,600;1,700&family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="../css/site.css">
    <title>Fantasy 9pin </title>
    <link rel="icon" type="image/icon" href="img\9pinlogo3icon16.png">
  </head>
  <body>

<style>
    
  * {
  box-sizing: border-box;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

body {
  padding: 0;
  margin: 0;
  font-family: "Poppins", sans-serif;
}

nav {
  padding: 5px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px,
    rgba(0, 0, 0, 0.3) 0px 1px 3px -1px;
  z-index: 1;
}
nav .logo {
  display: flex;
  align-items: center;
}
nav .logo img {
  height: 60px;
  width: auto;
  /* margin-right: 10px; */
}
nav .logo h1 {
  font-size: 1.1rem;
  background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 0;
}

.logolink{
  text-decoration: none;
}

nav ul {
  list-style: none;
  display: flex;
  margin-bottom: 0;
}
nav ul li {
  margin-left: 1rem;
  display:flex;
  align-items:center;
}
nav ul li a {
  text-decoration: none;
  color: #000;
  font-size: 95%;
  font-weight: 400;
  padding: 4px 8px;
  border-radius: 5px;
}

nav ul li a:hover {
  background-color: #f5f5f5;
}

.hamburger {
  display: none;
  cursor: pointer;
  caret-color: transparent;
}

.hamburger .line {
  width: 25px;
  height: 1px;
  background-color: #1f1f1f;
  display: block;
  margin: 7px auto;
  transition: all 0.3s ease-in-out;
}
.hamburger-active {
  transition: all 0.3s ease-in-out;
  transition-delay: 0.6s;
  transform: rotate(45deg);
}

.hamburger-active .line:nth-child(2) {
  width: 0px;
}

.hamburger-active .line:nth-child(1),
.hamburger-active .line:nth-child(3) {
  transition-delay: 0.3s;
}

.hamburger-active .line:nth-child(1) {
  transform: translateY(12px);
}

.hamburger-active .line:nth-child(3) {
  transform: translateY(-5px) rotate(90deg);
}

.menubar {
  position: absolute;
  top: 0;
  left: -60%;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  width: 60%;
  max-width:250px;
  padding-top: 20%;
  background: rgba(255, 255, 255);
  transition: all 0.5s ease-in;
  z-index: 2;
}
.active {
  left: 0;
  box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
}

.menubar ul {
  padding: 0;
  list-style: none;
  margin-bottom: 2rem;
}
.menubar ul li {
  margin-bottom: 32px;
}

.menubar ul li a {
  text-decoration: none;
  color: #000;
  font-size: 95%;
  font-weight: 400;
  padding: 5px 10px;
  border-radius: 5px;
}

.menubar ul li a:hover {
  background-color: #f5f5f5;
}

#active a{
    font-weight:bold;
}
@media screen and (max-width: 990px) {
  .hamburger {
    display: block;
  }
  nav ul {
    display: none;
  }
}

.language-dropdown {
        position: relative;
        display: inline-block;
        margin-right: 40px;
    }

    .selected-language {
        display: flex;
        align-items: center;
        background-color: #3498db;
        color: #fff;
        padding: 6px 10px;
        border: none;
        cursor: pointer;
        border-radius: 5px;
    }

    .selected-language img {
        width: 20px;
        margin-right: 10px;
    }

    .selected-language span {
        font-size: 10px;
        margin-left: auto;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: #fff;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        border-radius: 5px;
        overflow: hidden;
        z-index: 2;
    }

    .dropdown-content button {
        display: flex;
        align-items: center;
        padding: 10px;
        border: none;
        background-color: #fff;
        width: 100%;
        cursor: pointer;
        font-size: 10px;
    }

    .dropdown-content img {
        width: 20px;
        margin-right: 10px;
    }

    .dropdown-content button:hover {
        background-color: #f1f1f1;
    }

    .language-dropdown:hover .dropdown-content {
        display: block;
    }

    @media (max-width: 440px) {
        
      .language-dropdown {
        margin: 10px;
        margin-left: 0;
      }

      .dropdown-content button {
        font-size: 0px;
      }

      .logo{
        margin-right: 0;
      }
    }
    .profile-link-picture {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        border: 1px solid #ccc; /* You can change the border color as needed */
        object-fit: cover; /* Ensures the image covers the entire circle without distortion */
        cursor: pointer; /* Changes the cursor to a pointer when hovering over the image */
    }
    .profile-link{
        padding:0;
    }

</style>

  <nav>
      <a class="logolink" href="index.php">
        <div class="logo">
          <img src="img/9pinlogo.webp" alt="logo" />
          <h1>FANTASY 9PIN</h1>
        </div>
      </a>
      <ul>
      <?php switch($_SESSION['lang']){
          case 1:
          ?>
            <?php
              if(!isset($_SESSION['profile_id'])){
            ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Kezdőlap</a></li>
              <li <?php if($title=="Szabályok" OR $title=="Kérdések"){echo 'id="active"';} ?>><a href="szabalyok.php">Szabályok</a></li>
              
            <?php }else{ ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Kezdőlap</a></li>
              <li <?php if($title=="Csapatom"){echo 'id="active"';} ?>><a href="myteam.php">Csapatom</a></li>
              <li <?php if($title=="Igazolások"){echo 'id="active"';} ?>><a href="transfer.php">Igazolások</a></li>
              <?php if($started){ ?>
              <li <?php if($title=="Verseny"){echo 'id="active"';} ?>><a href="standings.php">Verseny</a></li>
              <?php } ?>
              <?php if($started){ ?>
              <li <?php if($title=="Statisztika"){echo 'id="active"';} ?>><a href="statistics.php">Statisztika</a></li>
              <?php } ?>
              <li <?php if($title=="Meccsek"){echo 'id="active"';} ?>><a href="matches.php">Meccsek</a></li>
            <?php if(isset($_SESSION['league'])){ $surveynum=$crud->getOpenSurveyNumber($_SESSION['league']); if($surveynum['surveycount']>0){  ?>
              <li <?php if($title=="Szavazás"){echo 'id="active"';} ?>><a href="voting.php">Szavazás</a></li>
            <?php }} ?>
            
            <?php
              //ezt az oldalt csak admin kategóriájúak látják
              if($_SESSION['authorization'] == 3){
            ?>  
              <li <?php if($title=="Admin"){echo 'id="active"';} ?>><a href="adminmester.php">Admin</a></li>
            <?php } ?>
              <li><a href="logout.php">Kijelentkezés</a></li>
              <?php } ?>
              <?php ;
          break;

          case 2:
        ?>
            <?php
              if(!isset($_SESSION['profile_id'])){
            ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
              <li <?php if($title=="Szabályok" OR $title=="Kérdések"){echo 'id="active"';} ?>><a href="rules.php">Rules</a></li>
              
            <?php }else{ ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
              <li <?php if($title=="Csapatom"){echo 'id="active"';} ?>><a href="myteam.php">My Team</a></li>
              <li <?php if($title=="Igazolások"){echo 'id="active"';} ?>><a href="transfer.php">Transfers</a></li>
              <?php if($started){ ?>
              <li <?php if($title=="Verseny"){echo 'id="active"';} ?>><a href="standings.php">Competition</a></li>
              <?php } ?>
              <?php if($started){ ?>
              <li <?php if($title=="Statisztika"){echo 'id="active"';} ?>><a href="statistics.php">Statistics</a></li>
              <?php } ?>
              <li <?php if($title=="Meccsek"){echo 'id="active"';} ?>><a href="matches.php">Fixtures</a></li>
            <?php if(isset($_SESSION['league'])){ $surveynum=$crud->getOpenSurveyNumber($_SESSION['league']); if($surveynum['surveycount']>0){  ?>
              <li <?php if($title=="Szavazás"){echo 'id="active"';} ?>><a href="voting.php">Voting</a></li>
            <?php }} ?>
              <li><a href="logout.php">Logout</a></li>
            <?php } ?>
            <?php ;
            break;

            case 3:
              ?>
                  <?php
                    if(!isset($_SESSION['profile_id'])){
                  ?>
                    <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
                    <li <?php if($title=="Szabályok" OR $title=="Kérdések"){echo 'id="active"';} ?>><a href="regeln.php">Regeln</a></li>
                    
                  <?php }else{ ?>
                    <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
                    <li <?php if($title=="Csapatom"){echo 'id="active"';} ?>><a href="myteam.php">Mein Team</a></li>
                    <li <?php if($title=="Igazolások"){echo 'id="active"';} ?>><a href="transfer.php">Transfers</a></li>
                    <?php if($started){ ?>
                    <li <?php if($title=="Verseny"){echo 'id="active"';} ?>><a href="standings.php">Rangliste</a></li>
                    <?php } ?>
                    <?php if($started){ ?>
                    <li <?php if($title=="Statisztika"){echo 'id="active"';} ?>><a href="statistics.php">Statistiken</a></li>
                    <?php } ?>
                    <li <?php if($title=="Meccsek"){echo 'id="active"';} ?>><a href="matches.php">Spielplan</a></li>
                  <?php if(isset($_SESSION['league'])){ $surveynum=$crud->getOpenSurveyNumber($_SESSION['league']); if($surveynum['surveycount']>0){  ?>
                    <li <?php if($title=="Szavazás"){echo 'id="active"';} ?>><a href="voting.php">Abstimmung</a></li>
                  <?php }} ?>
                    <li><a href="logout.php">Abmelden</a></li>
                  <?php } ?>
                  <?php ;
                  break;
      
          }?>
          <?php
            if(isset($_SESSION['profile_id'])){
              $profilemenupic=$crud->getPicture($felhaszn['picture_id'])
          ?>
          <li>
            <div class="profilelink">
                <a href="profile.php" class="profile-link">
                    <img src="img/profilepic/<?= $profilemenupic['link']?>" alt="Profile Picture" class="profile-link-picture">
                </a>
            </div>
          </li>
          <?php
            }
          ?>
          <li>
            <div class="language-dropdown">
              <form form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="langselect">
                <?php 
                $currlang=$crud->getCurrentLanguage($_SESSION['lang']);
                $langlist=$crud->getLanguageList();
                ?>
                <button type="button" class="selected-language">
                  <img src="img/<?php echo $currlang['flag'];?>" alt="">
                  <span>&#9660;</span>
                </button>
                <div class="dropdown-content">
                <?php while($r = $langlist->fetch(PDO::FETCH_ASSOC)){ 
                  if($r['lang_id']!=$_SESSION['lang']){?> 
                  <button type="submit" name="language" value="<?php echo $r['lang_id'];?>">
                    <img src="img/<?php echo $r['flag'];?>" alt="">  
                    <?php echo $r['language']; ?>
                  </button>
                <?php }}?>
                </div>
              </form>
            </div>
          </li>
      </ul>
      <div class="hamburger">
        <span class="line"></span>
        <span class="line"></span>
        <span class="line"></span>
      </div>
    </nav>
    <div class="menubar">
      <ul>
      <?php switch($_SESSION['lang']){
          case 1:
          ?>
            <?php
              if(!isset($_SESSION['profile_id'])){
            ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Kezdőlap</a></li>
              <li <?php if($title=="Szabályok" OR $title=="Kérdések"){echo 'id="active"';} ?>><a href="szabalyok.php">Szabályok</a></li>
              
            <?php }else{ ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Kezdőlap</a></li>
              <li <?php if($title=="Csapatom"){echo 'id="active"';} ?>><a href="myteam.php">Csapatom</a></li>
              <li <?php if($title=="Igazolások"){echo 'id="active"';} ?>><a href="transfer.php">Igazolások</a></li>
              <?php if($started){ ?>
              <li <?php if($title=="Verseny"){echo 'id="active"';} ?>><a href="standings.php">Verseny</a></li>
              <?php } ?>
              <?php if($started){ ?>
              <li <?php if($title=="Statisztika"){echo 'id="active"';} ?>><a href="statistics.php">Statisztika</a></li>
              <?php } ?>
              <li <?php if($title=="Meccsek"){echo 'id="active"';} ?>><a href="matches.php">Meccsek</a></li>
            <?php if(isset($_SESSION['league'])){ $surveynum=$crud->getOpenSurveyNumber($_SESSION['league']); if($surveynum['surveycount']>0){  ?>
              <li <?php if($title=="Szavazás"){echo 'id="active"';} ?>><a href="voting.php">Szavazás</a></li>
            <?php }} ?>
            <?php
              //ezt az oldalt csak admin kategóriájúak látják
              if($_SESSION['authorization'] == 3){
            ?>  
              <li <?php if($title=="Admin"){echo 'id="active"';} ?>><a href="adminmester.php">Admin</a></li>
            <?php } ?>
              <li><a href="logout.php">Kijelentkezés</a></li>
              <?php } ?>
              <?php ;
          break;

          case 2:
        ?>
            <?php
              if(!isset($_SESSION['profile_id'])){
            ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
              <li <?php if($title=="Szabályok" OR $title=="Kérdések"){echo 'id="active"';} ?>><a href="rules.php">Rules</a></li>
              
            <?php }else{ ?>
              <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
              <li <?php if($title=="Csapatom"){echo 'id="active"';} ?>><a href="myteam.php">My Team</a></li>
              <li <?php if($title=="Igazolások"){echo 'id="active"';} ?>><a href="transfer.php">Transfers</a></li>
              <?php if($started){ ?>
              <li <?php if($title=="Verseny"){echo 'id="active"';} ?>><a href="standings.php">Competition</a></li>
              <?php } ?>
              <?php if($started){ ?>
              <li <?php if($title=="Statisztika"){echo 'id="active"';} ?>><a href="statistics.php">Statistics</a></li>
              <?php } ?>
              <li <?php if($title=="Meccsek"){echo 'id="active"';} ?>><a href="matches.php">Fixtures</a></li>
            <?php if(isset($_SESSION['league'])){ $surveynum=$crud->getOpenSurveyNumber($_SESSION['league']); if($surveynum['surveycount']>0){  ?>
              <li <?php if($title=="Szavazás"){echo 'id="active"';} ?>><a href="voting.php">Voting</a></li>
            <?php }} ?>
              <li><a href="logout.php">Logout</a></li>
            <?php } ?>
            <?php ;
            break;

            case 3:
              ?>
                  <?php
                    if(!isset($_SESSION['profile_id'])){
                  ?>
                    <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
                    <li <?php if($title=="Szabályok" OR $title=="Kérdések"){echo 'id="active"';} ?>><a href="regeln.php">Regeln</a></li>
                    
                  <?php }else{ ?>
                    <li <?php if($title=="Kezdőlap"){echo 'id="active"';} ?>><a href="index.php">Index</a></li>
                    <li <?php if($title=="Csapatom"){echo 'id="active"';} ?>><a href="myteam.php">Mein Team</a></li>
                    <li <?php if($title=="Igazolások"){echo 'id="active"';} ?>><a href="transfer.php">Transfers</a></li>
                    <?php if($started){ ?>
                    <li <?php if($title=="Verseny"){echo 'id="active"';} ?>><a href="standings.php">Rangliste</a></li>
                    <?php } ?>
                    <?php if($started){ ?>
                    <li <?php if($title=="Statisztika"){echo 'id="active"';} ?>><a href="statistics.php">Statistiken</a></li>
                    <?php } ?>
                    <li <?php if($title=="Meccsek"){echo 'id="active"';} ?>><a href="matches.php">Spielplan</a></li>
                  <?php if(isset($_SESSION['league'])){ $surveynum=$crud->getOpenSurveyNumber($_SESSION['league']); if($surveynum['surveycount']>0){  ?>
                    <li <?php if($title=="Szavazás"){echo 'id="active"';} ?>><a href="voting.php">Abstimmung</a></li>
                  <?php }} ?>
                    <li><a href="logout.php">Abmelden</a></li>
                  <?php } ?>
                  <?php ;
                  break;
      
          }?>
          <?php
            if(isset($_SESSION['profile_id'])){
              $profilemenupic=$crud->getPicture($felhaszn['picture_id'])
          ?>
          <li>
            <div class="profilelink">
                <a href="profile.php" class="profile-link">
                    <img src="img/profilepic/<?= $profilemenupic['link']?>" alt="Profile Picture" class="profile-link-picture">
                </a>
            </div>
          </li>
          <?php
            }
          ?>
          <li>
            <div class="language-dropdown">
              <form form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="langselect">
                <?php 
                $currlang=$crud->getCurrentLanguage($_SESSION['lang']);
                $langlist=$crud->getLanguageList();
                ?>
                <button type="button" class="selected-language">
                  <img src="img/<?php echo $currlang['flag'];?>" alt="">
                  <span>&#9660;</span>
                </button>
                <div class="dropdown-content">
                <?php while($r = $langlist->fetch(PDO::FETCH_ASSOC)){ 
                  if($r['lang_id']!=$_SESSION['lang']){?> 
                  <button type="submit" name="language" value="<?php echo $r['lang_id'];?>">
                    <img src="img/<?php echo $r['flag'];?>" alt="">  
                    <?php echo $r['language']; ?>
                  </button>
                <?php }}?>
                </div>
              </form>
            </div>
          </li>
      </ul>
    </div>
    

    <script>
        const mobileNav = document.querySelector(".hamburger");
        const navbar = document.querySelector(".menubar");

        const toggleNav = () => {
        navbar.classList.toggle("active");
        mobileNav.classList.toggle("hamburger-active");
        };
        mobileNav.addEventListener("click", () => toggleNav());

    </script>