<?php 
$title = "Hírek";
require_once 'includes/header.php';
require_once 'db/conn.php';
$id_article = (int)$_GET['newsid'];

if ( !empty($id_article) && $id_article > 0) {
    // Fecth news
    $article = $crud->getArticle($id_article,$_SESSION['lang']);
}else{
    $article = false;
    echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
}

switch($_SESSION['lang']){
    case 1: $honapok = Array( "", "január" , "február"  , "március"   ,"április", "május"    , "június"    ,"július" , "augusztus", "szeptember","október", "november" , "december"    );
    break;
    case 2: $honapok = Array( "", "January" , "February"  , "March"   ,"April", "May"    , "June"    ,"July" , "August", "September","October", "November" , "December"    );
    break;
    case 3: $honapok = Array( "", "Januar" , "Februar"  , "März"   ,"April", "Mai"    , "Juni"    ,"Juli" , "August", "September","Oktober", "November" , "Dezember"    );
    break;
}

?>

<style>
    .article-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        background-color: #fff;
    }

    .article-image img {
        width: 100%;
        height: auto;
        border-radius: 5px;
    }

    .article-details {
        padding: 20px;
    }

    .article-details h1 {
        font-size: 28px;
        margin-bottom: 10px;
    }

    .article-date {
        font-size: 14px;
        color: #777;
        margin-bottom: 20px;
    }

    .article-content p {
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .article-content img{
        max-width: 100%;
    }

    .back-button {
        margin-top: 20px;
        text-align: center;
    }

    .back-button a {
        display: inline-block;
        padding: 10px 20px;
        background-color: #007BFF;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.3s;
    }

    .back-button a:hover {
        background-color: #0056b3;
    }

    @media (max-width: 700px) {
        .article-container {
            padding: 10px;
        }

        .article-details {
            padding: 10px;
        }

        .article-details h1 {
            font-size: 24px;
        }

        .article-content p {
            font-size: 14px;
        }

        .article-content img{
            max-width: 100%;
        }

        .back-button a {
            padding: 8px 16px;
        }
    }
</style>

<?php if ( $article && !empty($article) ){ ?>
    <div class="article-container">
        <div class="article-image">
            <img src="img/news/<?php echo $article['image'];?>" alt="">
        </div>
        <div class="article-details">
            <h1><?php echo ($article['newstitle']) ?></h1>
            <p class="article-date">
                <?php 
                    $publishdate=explode("-", $article['published_on']);
                    switch($_SESSION['lang']){
                    case 1: echo $publishdate[0].". ".$honapok[number_format($publishdate[1],)]." ".$publishdate[2]."." ;
                    break;
                    case 2: 
                        $newdate=date('jS F Y', strtotime($article['published_on']));
                        echo $newdate ;
                    break;
                    case 3: echo $publishdate[2].". ".$honapok[number_format($publishdate[1],)]." ".$publishdate[0]."." ;
                    break;
                }?>
            </p>
            <div class="article-content">
                <p>
                <?php echo nl2br($article['full_content']) ?>
                </p>
            </div>
            <div class="back-button">
                <a href="index.php">&#11178 <?php switch($_SESSION['lang']){
                            case 1: echo "Vissza";
                            break;
                            case 2: echo "Back";
                            break;
                            case 3: echo "Zurück";
                            break;
                        }?></a>
            </div>
        </div>
    </div>
<?php }?>

<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>