<?php 

$title = "Kezdőlap";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(isset($_GET['pic'])){$picture=$_GET['pic'];};

if(!isset($_GET['pic']) AND !isset($_SESSION['pic'])){
  echo '<script type="text/javascript">location.href="index.php";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
}

if(!isset($_SESSION['pic'])){
  switch ($picture) {
    case 'disco':
      $_SESSION['pic']=77;
      break;
    
    case 'inspector':
      $_SESSION['pic']=78;
      break;

      default:
      echo '<script type="text/javascript">location.href="index.php";</script>
          <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
          break;
  }
}

//bejelentkezés
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
            echo '<script type="text/javascript">location.href="teamcupsgame.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=teamcupsgame.php" /></noscript> ';
          }else{
            echo '<script type="text/javascript">location.href="waiting-confirmation.php";</script>
            <noscript><meta http-equiv="refresh" content="0; URL=waiting-confirmation.php" /></noscript> ';
          }
            
        }
    }
}


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

.rewardbox{
    font-family: "Roboto", helvetica, arial, sans-serif;    
    margin-top: 2rem;
    width: 100%;
}

.rewardbox h3{
    text-align: center;
}

@media screen and (min-width: 600px) {
    .rewardbox{
        width: 50vw;
        max-width: 20em;
        margin: auto;
        margin-top: 2rem;
    }
    
  }

  .congrats {
    font-family: "Roboto", helvetica, arial, sans-serif; 
  text-align: center;
  margin-bottom: 2rem;
  margin-top: 3rem;
}

.picdiv {
  display: flex;
  justify-content: center;
}

.picdiv img {
  max-width: 100%;
  height: auto;
  width: 100%;
  max-width: 400px;
  border-radius: 10px;
}

@media (max-width: 768px) {
  .picdiv img {
    width: 100%;
    max-width: none;
  }
}





</style>



<?php if(!isset($_SESSION['profile_id'])){ ?>
    <div class="rewardbox">
        <div>
            <h3>Login to unlock reward 😎</h3>
        </div>
    </div>
    <div class="loginbox">
        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class='login-form'>
            <div class="flex-row">
                <label class="lf--label" for="username">
                <svg x="0px" y="0px" width="12px" height="13px">
                    <path fill="#B1B7C4" d="M8.9,7.2C9,6.9,9,6.7,9,6.5v-4C9,1.1,7.9,0,6.5,0h-1C4.1,0,3,1.1,3,2.5v4c0,0.2,0,0.4,0.1,0.7 C1.3,7.8,0,9.5,0,11.5V13h12v-1.5C12,9.5,10.7,7.8,8.9,7.2z M4,2.5C4,1.7,4.7,1,5.5,1h1C7.3,1,8,1.7,8,2.5v4c0,0.2,0,0.4-0.1,0.6 l0.1,0L7.9,7.3C7.6,7.8,7.1,8.2,6.5,8.2h-1c-0.6,0-1.1-0.4-1.4-0.9L4.1,7.1l0.1,0C4,6.9,4,6.7,4,6.5V2.5z M11,12H1v-0.5 c0-1.6,1-2.9,2.4-3.4c0.5,0.7,1.2,1.1,2.1,1.1h1c0.8,0,1.6-0.4,2.1-1.1C10,8.5,11,9.9,11,11.5V12z"/>
                </svg>
                </label>
                <input id="username" class='lf--input' name="email" placeholder='Email address' type='text' value="<?php if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['email'])) echo $_POST['email'];?>">
            </div>
            <div class="flex-row">
                <label class="lf--label" for="password">
                <svg x="0px" y="0px" width="15px" height="5px">
                    <g>
                    <path fill="#B1B7C4" d="M6,2L6,2c0-1.1-1-2-2.1-2H2.1C1,0,0,0.9,0,2.1v0.8C0,4.1,1,5,2.1,5h1.7C5,5,6,4.1,6,2.9V3h5v1h1V3h1v2h1V3h1 V2H6z M5.1,2.9c0,0.7-0.6,1.2-1.3,1.2H2.1c-0.7,0-1.3-0.6-1.3-1.2V2.1c0-0.7,0.6-1.2,1.3-1.2h1.7c0.7,0,1.3,0.6,1.3,1.2V2.9z"/>
                    </g>
                </svg>
                </label>
                <input id="password" class='lf--input' name="password" placeholder='Password' type='password'>
            </div>
            <input class='lf--submit' type='submit' value='Login'>
        </form>
    </div>

<?php }else{ 
  $picdetail=$crud->getPicture($_SESSION['pic']);
  $check=$crud->findExtraPicture($_SESSION['pic'],$_SESSION['profile_id']);
  if($check['count']==0){
    $newpic=$crud->newExtraPicture($_SESSION['profile_id'],$_SESSION['pic'],6);
    unset($_SESSION['pic']);    
  }

  
  ?>
<div class="congrats">
  <h2>You have a new profile picture!</h2>
</div>
<div class="picdiv">
  <img src="img\profilepic\<?php echo $picdetail['link'] ?>" alt="">
</div>

<?php } ?>