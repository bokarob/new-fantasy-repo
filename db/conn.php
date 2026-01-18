<?php
    $host = 'myhost'; //that's the same as 'localhost'
    $db = 'database';
    $user = 'myuser'; //this is the user for phpmyadmin. For this case we didn't need one, that's root. When it goes to production, this will be important
    $pass = 'mypass';
    $charset = 'utf8mb4'; //usual charset

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset"; //dsn=data source name. mysql=driver. 
    
    //try-catch works in a way that it attempts to do something, which is defined in the {} of try, but if it fails it will go for the {} of catch
    try{
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e){                       //in the () after catch we define exceptions. If there is a PDO error during try, it will be stored in $e
        throw new PDOException($e->getMessage());  //throw will stop the execution. We don't need this all the time, we could just echo the error message, but this is critical for the database, so it can be a throw
    }

    //so this php attempts to make a connection to mysql database. If nothing goes wrong, we should see the message. If it does, we see the error

    require_once 'crud.php';
    require_once 'webuser.php';
    require_once 'player.php';
    require_once 'administer.php';
    $crud = new crud($pdo);
    $webuser = new webuser($pdo);
    $player = new player($pdo);
    $admin = new admin($pdo);

    $webuser->insertUser('admin@email.com','password','Admin Károly','a helyi bika',1,1,NULL,0);

?>