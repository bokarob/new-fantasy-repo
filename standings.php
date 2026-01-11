<?php 
$title = "Verseny";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(isset($_GET['notype'])){
    $notification=$crud->getPointsNotificationForUser($_SESSION['profile_id'],$_GET['notype']);
    switch ($_GET['notype']) {
        case 'B1':
          $_SESSION['league']=10;
          $comp=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
          $_SESSION['competitor_id']=$comp['competitor_id'];
          break;    
        case 'B2':
          $_SESSION['league']=20;
          $comp=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
          $_SESSION['competitor_id']=$comp['competitor_id'];
          break;
        case 'B4':
          $_SESSION['league']=40;
          $comp=$crud->getCompetitorID($_SESSION['profile_id'],$_SESSION['league']);
          $_SESSION['competitor_id']=$comp['competitor_id'];
          break;
      }
    $markread=$crud->markNotificationAsRead($notification['notification_id']);
    
}

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
    
    table tr {
        vertical-align: middle;
        color:#170202;
    }
    table th a{
        color:#170202;
    }
    h2{color:#170202}
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

    .profilepic{
        width: 40px;
        height: 40px;
        border-radius: 50%;
    }

    /* ősz és tavasz pontokat kivenni egyelőre */
    table th:nth-of-type(6) {
        display: none;
    }
    table td:nth-of-type(7) {
        display: none;
    }
    table th:nth-of-type(7) {
        display: none;
    }
    table td:nth-of-type(8) {
        display: none;
    }

    @media (max-width: 475px) {
        table th:nth-of-type(6) {
            display: none;
        }
        table th:nth-of-type(7) {
            display: none;
        }
        table th:nth-of-type(8) {
            display: none;
        }

        table td:nth-of-type(7) {
            display: none;
        }
        table td:nth-of-type(8) {
            display: none;
        }
        table td:nth-of-type(9) {
            display: none;
        }
        table th {
            font-size:11px;
        }
        table td {
            font-size:12px;
            
        }
        table td:nth-of-type(3), table td:nth-of-type(4) {
            max-width: 150px;
            word-break: break-all;
            word-wrap:break-word;
        }
        .profilepic{
            width: 30px;
            height: 30px;
        }
    }

    .myleagues{
        /* max-width: 200px; */
        margin: auto;
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    #meinenligen{
        
        /* width: 100%; */
        max-width: 160px;
        align-content: center;
        font-size: 14px;
        padding: 8px 30px;
        border: 1px solid #ccc;
        border-radius: 8px;
        margin-bottom: 1rem;
        background-color: #e3f2f2;
    }
</style>

<h2 style="text-align: center; margin-top: 4vh; margin-bottom: 5vh">
    <?php switch($_SESSION['lang']){
        case 1: echo "Fantasy 9pin Liga állása";
        break;
        case 2: echo "Fantasy 9pin League standings";
        break;
        case 3: echo "Fantasy 9pin Liga Rangliste";
        break;
    }?>
</h2>

<div>
    <?php
    require_once 'includes/minileagueselect.php';
    ?>
</div>

<div class="myleagues">
    <a class="btn btn-muted" id="meinenligen" href="myleagues.php">
        <?php switch($_SESSION['lang']){
            case 1: echo "Saját ligáim";
            break;
            case 2: echo "My Leagues";
            break;
            case 3: echo "Meine Ligen";
            break;
        }?>
    </a>  
    <a class="btn btn-muted" id="meinenligen" href="fan-leagues.php">
        <?php switch($_SESSION['lang']){
            case 1: echo "Szurkolói ligák";
            break;
            case 2: echo "Fan Leagues";
            break;
            case 3: echo "Fan Ligen";
            break;
        }?>
    </a>
</div>

<?php
if(isset($_SESSION['league'])){
    $gameweek = $crud->getGameweek($_SESSION['league']);
    $week = $gameweek['gameweek'];
    
    // $results=$crud->getCompetitorRanked($week);
    
    if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';
    
    $columns = array('TP','WP','osz','tavasz');
    $column = isset($_GET['column']) && in_array($_GET['column'], $columns) ? $_GET['column'] : $columns[0];
    $sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'ASC' : 'DESC';
    
    if ($results = $crud->getCompetitorRanked($column,$sort_order,$week,$_SESSION['league'])) {
        // Some variables we need for the table to be able to sort it
        $up_or_down = str_replace(array('ASC','DESC'), array('down-alt','down'), $sort_order); 
        $asc_or_desc = $sort_order == 'DESC' ? 'asc' : 'desc';
        $add_class = ' class="highlight"';

?>

<table class="table">
    <tr>
        <?php switch($_SESSION['lang']){
            case 1:
            ?>
                <th >Hely</th>
                <th colspan="2">Fantasy 9pin csapat</th>
                <th>Edző</th>
                <th><a href="standings.php?column=TP&order=<?php echo $asc_or_desc; ?>">Összes pont <i class="<?php echo $column == 'TP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=WP&order=<?php echo $asc_or_desc; ?>">Heti <i class="<?php echo $column == 'WP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=osz&order=<?php echo $asc_or_desc; ?>">Őszi fordulók <i class="<?php echo $column == 'osz' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=tavasz&order=<?php echo $asc_or_desc; ?>">Tavaszi fordulók <i class="<?php echo $column == 'tavasz' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th>Csapatérték</th>
            <?php ;
            break;

            case 2:
            ?>
                <th>Rank</th>
                <th colspan="2">Fantasy 9pin team</th>
                <th>Trainer</th>
                <th><a href="standings.php?column=TP&order=<?php echo $asc_or_desc; ?>">Total ponts <i class="<?php echo $column == 'TP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=WP&order=<?php echo $asc_or_desc; ?>">Weekly <i class="<?php echo $column == 'WP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=osz&order=<?php echo $asc_or_desc; ?>">Fall season <i class="<?php echo $column == 'osz' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=tavasz&order=<?php echo $asc_or_desc; ?>">Spring season <i class="<?php echo $column == 'tavasz' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th>Team value</th>
            <?php ;
            break;

            case 3:
            ?>
                <th>Platz</th>
                <th colspan="2">Fantasy 9pin team</th>
                <th>Trainer</th>
                <th><a href="standings.php?column=TP&order=<?php echo $asc_or_desc; ?>">Gesamtpunkte <i class="<?php echo $column == 'TP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=WP&order=<?php echo $asc_or_desc; ?>">Wochenpunkte <i class="<?php echo $column == 'WP' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=osz&order=<?php echo $asc_or_desc; ?>">Herbstsaison <i class="<?php echo $column == 'osz' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="standings.php?column=tavasz&order=<?php echo $asc_or_desc; ?>">Frühlingssaison <i class="<?php echo $column == 'tavasz' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th>Teamwert</th>
            <?php ;
            break;
        }?>
        
    </tr>
    <?php $counter=0; while($r = $results->fetch(PDO::FETCH_ASSOC)){ ?>
        <?php 
            $teamweekly=$crud->getWeeklyteamresult($r['competitor_id'],$week-1); 
            $counter=$counter+1;
            $lastrank=$crud->getTeamrank($r['competitor_id'],$week-2);
            $currentrank=$crud->getTeamrank($r['competitor_id'],$week-1);
            $roster=$crud->getRoster($r['competitor_id'],$week-1);
            if($roster){
            
                $value1=$player->getPrice($roster['player1'],$week-1);
                $value2=$player->getPrice($roster['player2'],$week-1);
                $value3=$player->getPrice($roster['player3'],$week-1);
                $value4=$player->getPrice($roster['player4'],$week-1);
                $value5=$player->getPrice($roster['player5'],$week-1);
                $value6=$player->getPrice($roster['player6'],$week-1);
                $value7=$player->getPrice($roster['player7'],$week-1);
                $value8=$player->getPrice($roster['player8'],$week-1);
                }else{
                    $roster=$crud->getRoster($r['competitor_id'],$week);
                    $value1=$player->getPrice($roster['player1'],$week);
                    $value2=$player->getPrice($roster['player2'],$week);
                    $value3=$player->getPrice($roster['player3'],$week);
                    $value4=$player->getPrice($roster['player4'],$week);
                    $value5=$player->getPrice($roster['player5'],$week);
                    $value6=$player->getPrice($roster['player6'],$week);
                    $value7=$player->getPrice($roster['player7'],$week);
                    $value8=$player->getPrice($roster['player8'],$week);
                }

            $valuetotal = 0;
            $valuetotal = $value1['price'] + $value2['price'] + $value3['price'] + $value4['price'] + $value5['price'] + $value6['price'] + $value7['price'] + $value8['price'];
        ?>
        <?php if(isset($currentrank['rank']) AND $currentrank['rank']>0){ ?>
        <tr <?php if(isset($_SESSION['competitor_id'])){if($r['competitor_id']==$_SESSION['competitor_id']){echo 'style="background-color:#90F0FD"';}}?>>
            <td><?php echo $currentrank['rank']." "; if(($lastrank) AND $lastrank['rank'] > $currentrank['rank']){echo '<i class="bi bi-arrow-up-circle-fill text-success"></i>';}elseif(($lastrank) AND $lastrank['rank'] < $currentrank['rank']){echo '<i class="bi bi-arrow-down-circle-fill text-danger"></i>';}else{echo '<i class="bi bi-dash-circle-fill"></i>';} ?></td>
            <td><?php $picture=$crud->findPicture($r['picture_id']);?><a href="team.php?teamid=<?php echo $r['competitor_id'];?>"><img class="profilepic" src="img/profilepic/<?=$picture['link'] ?>" alt=""></a></td>
            <td><a href="team.php?teamid=<?php echo $r['competitor_id'];?>" style="text-decoration:none; color:black;"><?php echo $r['teamname'];?></a></td>
            <td><?php echo $r['alias'];?></td>
            <td><?php echo $r['TP'];?></td>
            <td><?php echo $r['WP'];?></td>
            <td><?php echo $r['osz'];?></td>
            <td><?php echo $r['tavasz'];?></td>
            <td><?php echo $valuetotal;?></td>

        </tr>
        <?php }?>
    <?php }?>
</table>

<?php }} ?>

<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>