<?php
$title = "Statisztika";
require_once 'includes/header.php';
require_once 'db/conn.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_POST['league'])){
    switch ($_POST['league']) {
        case '10':
            $_SESSION['league']=10;
            break;    
        case '20':
            $_SESSION['league']=20;
            break;
        case '40':
            $_SESSION['league']=40;
            break;
    }
  }

?>
<style>
    .wrapper{
    margin-right:0;
    padding-right:0;
    overflow-x: auto;
    width: 100%;
    }

    h2{color:#170202}
    table{
        margin-right:0;
        padding-right:0;
        width: 100%;
        border-collapse: collapse; 
    }
    th, td {
        padding: 10px;
        text-align: center;
        border: 1px solid #ccc;
    }
    th {
        background-color: #f2f2f2;
        font-weight: bold;
    }
    table tr {
        vertical-align: middle;
        color:#170202;
    }
    table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    table th a{
        color:black;
    }
    .leagueselection{
        display: flex;
        margin-top: 5rem;
        align-items: center;
        justify-content: center;
    }

    .league{
        margin: 2rem;
    }

    .card-img-top{
        height: 150px;
    }

    #teamname{
        font-style: italic;
        font-size: 10pt;
    }

    .tradeinput{
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .tradeinput + img {
        cursor: pointer;
    }
    .subspic{
        width:20px;
    }

    @media (min-width: 600px) and (max-width: 770px) {
    .wrapper {
        margin-left: 5px;
        font-size: 12px;
    }
}
    @media  (max-width: 599px) {
    .wrapper {
        margin-left: 0;
        font-size: 12px;
    }
    table td:nth-of-type(1) {
        text-align: left;
        padding-left: 15px;
    }
    table th:nth-of-type(1) {
        text-align: left;
        padding-left: 15px;
    }
    table td:nth-of-type(2) {
        text-align: left;
    }
    table th:nth-of-type(2) {
        text-align: left;
    }
    #teamname{
        font-size: 11px;
    }
}
</style>


<h2 style="text-align: center; margin-top: 4vh; margin-bottom: 5vh">
<?php switch($_SESSION['lang']){
    case 1: echo "Játékos statisztikák";
    break;
    case 2: echo "Player statistics";
    break;
    case 3: echo "Spielerstatistiken";
    break;
}?>
</h2>
<div>
    <?php
    if(isset($_SESSION['league'])){
    require_once 'includes/minileagueselect.php';}
    ?>
</div>

<?php
if(isset($_SESSION['league'])){
    $gameweek = $crud->getGameweek($_SESSION['league']);
    $week = $gameweek['gameweek'];
    
    if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
    
    $columns = array('playername','name','price','matches','pinavg','pointavg','pointsum','WP');
    $column = isset($_GET['column']) && in_array($_GET['column'], $columns) ? $_GET['column'] : $columns[0];
    $sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'desc' ? 'DESC' : 'ASC';
    
    if ($results = $player->getPlayerstatistics($column,$sort_order,$week,$_SESSION['league'])) {
        // Some variables we need for the table to be able to sort it
        $up_or_down = str_replace(array('ASC','DESC'), array('down-alt','down'), $sort_order); 
        $asc_or_desc = $sort_order == 'ASC' ? 'desc' : 'asc';
        $add_class = ' class="highlight"';
    
?>

<div class="wrapper">
    <table class="table" style="overflow:auto">
        <tr>
            <?php switch($_SESSION['lang']){
                case 1:
                ?>
                <th><a href="statistics.php?column=playername&order=<?php echo $asc_or_desc; ?>">Játékos név <i class="<?php echo $column == 'playername' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=name&order=<?php echo $asc_or_desc; ?>">Csapat <i class="<?php echo $column == 'name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=price&order=<?php echo $asc_or_desc; ?>">Ár <i class="<?php echo $column == 'price' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=matches&order=<?php echo $asc_or_desc; ?>"><abbr title="Lejátszott meccs">Meccsek</abbr> <i class="<?php echo $column == 'matches' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=pinavg&order=<?php echo $asc_or_desc; ?>">Átlag eredmény <i class="<?php echo $column == 'pinavg' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=pointavg&order=<?php echo $asc_or_desc; ?>">Átlag pont <i class="<?php echo $column == 'pointavg' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=pointsum&order=<?php echo $asc_or_desc; ?>">Összes pont <i class="<?php echo $column == 'pointsum' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=WP&order=<?php echo $asc_or_desc; ?>">Heti pont <i class="<?php echo $column == 'WP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th>Leigazol</th>
                <?php ;
                break;

                case 2:
                ?>
                <th><a href="statistics.php?column=playername&order=<?php echo $asc_or_desc; ?>">Player name <i class="<?php echo $column == 'playername' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=name&order=<?php echo $asc_or_desc; ?>">Team <i class="<?php echo $column == 'name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=price&order=<?php echo $asc_or_desc; ?>">Price <i class="<?php echo $column == 'price' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=matches&order=<?php echo $asc_or_desc; ?>"><abbr title="Games played">Games</abbr> <i class="<?php echo $column == 'matches' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=pinavg&order=<?php echo $asc_or_desc; ?>">Avg result<i class="<?php echo $column == 'pinavg' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=pointavg&order=<?php echo $asc_or_desc; ?>">Avg points<i class="<?php echo $column == 'pointavg' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=pointsum&order=<?php echo $asc_or_desc; ?>">Total points <i class="<?php echo $column == 'pointsum' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="statistics.php?column=WP&order=<?php echo $asc_or_desc; ?>">Weekly points<i class="<?php echo $column == 'WP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th>Trade</th>
                <?php ;
                break;

                case 3:
                    ?>
                    <th><a href="statistics.php?column=playername&order=<?php echo $asc_or_desc; ?>">Name <i class="<?php echo $column == 'playername' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th><a href="statistics.php?column=name&order=<?php echo $asc_or_desc; ?>">Mannschaft <i class="<?php echo $column == 'name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th><a href="statistics.php?column=price&order=<?php echo $asc_or_desc; ?>">Preis <i class="<?php echo $column == 'price' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th><a href="statistics.php?column=matches&order=<?php echo $asc_or_desc; ?>"><abbr title="Spiele">Sp.</abbr> <i class="<?php echo $column == 'matches' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th><a href="statistics.php?column=pinavg&order=<?php echo $asc_or_desc; ?>">∅ Kegel<i class="<?php echo $column == 'pinavg' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th><a href="statistics.php?column=pointavg&order=<?php echo $asc_or_desc; ?>">∅ Punkte<i class="<?php echo $column == 'pointavg' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th><a href="statistics.php?column=pointsum&order=<?php echo $asc_or_desc; ?>">Ges.pkt <i class="<?php echo $column == 'pointsum' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th><a href="statistics.php?column=WP&order=<?php echo $asc_or_desc; ?>"><abbr title="Wochenpunkte - Punkte in der vorherigen Spielwoche">WP</abbr><i class="<?php echo $column == 'WP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                    <th>Verpflichten</th>
                    <?php ;
                break;
    
            }?>
            
            
        </tr>
        <?php while($r = $results->fetch(PDO::FETCH_ASSOC)){ ?>
            <?php $lastprice=$player->getPrice($r['player_id'],$week-1); ?>
            <tr>
                <td<?php echo $column == 'playername' ? $add_class : ''; ?>><a href="playerdata.php?id=<?php echo $r['player_id'];?>" style="text-decoration:none; color:black;"><?php echo $r['playername'];?></a></td>
                <td <?php echo $column == 'name' ? $add_class : ''; ?> id="teamname"><?php echo $r['name']; ?></td>
                <td<?php echo $column == 'price' ? $add_class : ''; ?>><?php if($r['price']){echo ($r['price']);}else{echo "NA";}; if($lastprice AND $lastprice['price']<$r['price']){echo '<i class="bi bi-arrow-up text-success"></i>';}elseif($lastprice AND $lastprice['price']>$r['price']){echo '<i class="bi bi-arrow-down text-danger"></i>';} ?></td>
                <td<?php echo $column == 'matches' ? $add_class : ''; ?>><?php echo $r['matches']; ?></td>
                <td<?php echo $column == 'pinavg' ? $add_class : ''; ?>><?php if($r['pinavg']){echo number_format($r['pinavg'],1);}else{echo '-';} ?></td>
                <td<?php echo $column == 'pointavg' ? $add_class : ''; ?>><?php if($r['pointavg']){echo number_format($r['pointavg'],1);}else{echo '-';} ?></td>
                <td<?php echo $column == 'pointsum' ? $add_class : ''; ?>><?php echo $r['pointsum']; ?></td>    
                <td<?php echo $column == 'WP' ? $add_class : ''; ?>><?php echo $r['WP']; ?></td>
                <td>
                    <form action="transfer.php" method="post">
                        <label>
                            <input type="submit" class="tradeinput" name="player1in" value="<?php echo $r['player_id'];?>" id="player1trade" onchange="this.form.submit()">
                            <img src="img\trade.webp" class="subspic" alt="">
                        </label>
                    </form>
                </td>    
            </tr>
        <?php }?>
    </table>
</div>

<?php
	
}}else{ require_once 'includes/minileagueselect.php'; } ?>



<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>