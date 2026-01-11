<?php 
$title = "Üzenet elküldve";
require_once 'includes/header.php';
require_once 'db/conn.php';


?>
<?php switch($_SESSION['lang']){
    case 1: ?>

    <h3 style="font-style:italic">Üzenet elküldve!</h3>

<?php ;
    break;
    case 2: ?>

    <h3 style="font-style:italic">Message sent!</h3>

<?php ;
    break;
    case 3: ?>

    <h3 style="font-style:italic">Nachricht gesendet!</h3>

<?php ;
    break; 
}
?>


<br>
<br>
<br>
<br>
<?php require_once 'includes/footer.php'; ?>