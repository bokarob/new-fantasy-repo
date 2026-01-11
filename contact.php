<?php 
$title = "Kapcsolat";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

?>


<style>
.container{
	
	
	justify-content: center;
	align-items: center;
	padding: 20px 100px;
}


.contact-box{
	
	justify-content: center;
	align-items: center;
	text-align: center;
	background-color: #fff;
	box-shadow: 0px 0px 19px 5px rgba(0,0,0,0.19);
}


.right{
	padding: 25px 40px;
}

h2{
	position: relative;
	padding: 0 0 10px;
	margin-bottom: 10px;
}

h2:after{
	content: '';
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
    height: 4px;
    width: 50px;
    border-radius: 2px;
    background-color: #01cae4;
}

.field{
	width: 100%;
	border: 2px solid rgba(0, 0, 0, 0);
	outline: none;
	background-color: rgba(230, 230, 230, 0.6);
	padding: 0.5rem 1rem;
	font-size: 1.1rem;
	margin-bottom: 22px;
	transition: .3s;
}

.field:hover{
	background-color: rgba(0, 0, 0, 0.1);
}

textarea{
	min-height: 150px;
}

.btn{
	width: 100%;
	padding: 0.5rem 1rem;
	background-color: #01cae4;
	color: #fff;
	font-size: 1.1rem;
	border: none;
	outline: none;
	cursor: pointer;
	transition: .3s;
}

.btn:hover{
    background-color: #004165;
    color:white;
}

.field:focus{
    border: 2px solid rgba(30,85,250,0.47);
    background-color: #fff;
}

@media (max-width: 800px) {
    .container{
	    padding: 20px 20px;
    }
    .right{
        padding: 10px 10px;
    }
}


</style>


<form method="post" action="send-mail.php">
    <div class="container">
        <div class="contact-box">
            <div class="left"></div>
            <?php switch($_SESSION['lang']){
                case 1:
                ?>
                    <div class="right">
                        <h2>Kapcsolat</h2>
                        <input type="text" class="field" name="name" hidden value="<?php echo $_SESSION['alias'];?>">
                        <select type="form-control" class="field" name="subject">
                            <option value=' ' disabled selected>Téma</option>
                            <option value='Általános kérdés'>Általános kérdés</option>
                            <option value='Javítási, fejlesztési ötlet'>Javítási, fejlesztési ötlet</option>
                            <option value='Pontatlan adat, hiba az oldalon'>Pontatlan adat, hiba az oldalon</option>
                            <option value='Egyéb'>Egyéb</option>
                        </select>   
                        <textarea placeholder="Üzenet" class="field" name="message"></textarea>
                        <button type="submit" class="btn" name="submit">Küldés</button>
                    </div>
                <?php ;
                break;

                case 2:
                ?>
                    <div class="right">
                        <h2>Contact</h2>
                        <input type="text" class="field" name="name" hidden value="<?php echo $_SESSION['alias'];?>">
                        <select type="form-control" class="field" name="subject">
                            <option value=' ' disabled selected>Topic</option>
                            <option value='Általános kérdés'>General comment</option>
                            <option value='Javítási, fejlesztési ötlet'>Improvement ideas</option>
                            <option value='Pontatlan adat, hiba az oldalon'>Incorrect data / error on the page</option>
                            <option value='Egyéb'>Other</option>
                        </select>   
                        <textarea placeholder="Message" class="field" name="message"></textarea>
                        <button type="submit" class="btn" name="submit">Send</button>
                    </div>  
                <?php ;
                break;

                case 3:
                    ?>
                        <div class="right">
                            <h2>Kontakt</h2>
                            <input type="text" class="field" name="name" hidden value="<?php echo $_SESSION['alias'];?>">
                            <select type="form-control" class="field" name="subject">
                                <option value=' ' disabled selected>Betreff</option>
                                <option value='Általános kérdés'>Allgemeine Frage</option>
                                <option value='Javítási, fejlesztési ötlet'>Verbesserungsvorschläge</option>
                                <option value='Pontatlan adat, hiba az oldalon'>Falsche Daten / Fehler auf der Seite</option>
                                <option value='Egyéb'>Sonstiges</option>
                            </select>   
                            <textarea placeholder="Nachricht" class="field" name="message"></textarea>
                            <button type="submit" class="btn" name="submit">Senden</button>
                        </div>  
                    <?php ;
                    break;
    
            }?>
            
        </div>
    </div>
</form>






<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>