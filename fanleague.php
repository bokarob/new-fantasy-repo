<?php 
$title = "Leagues";
require_once 'includes/header.php';
require_once 'db/conn.php';


//ha nem vagy bejelentkezve, bye bye
if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if(isset($_GET['notype'])){
    $notificationlist=$crud->getLeagueConfirmationNotificationsForUser($_SESSION['profile_id'],$_SESSION['lang']);
    $notifications=$notificationlist->fetchAll();
    foreach ($notifications as $n) {
        $markread=$crud->markNotificationAsRead($n['notification_id']);
        echo '<script type="text/javascript">location.href="fanleague.php?leagueid='.$n['privateleague_id'].'";</script>
        <noscript><meta http-equiv="refresh" content="0; URL=fanleague.php?leagueid='.$n['privateleague_id'].'" /></noscript> ';
        break;
    }
}

//ligaadatok
$leagueid = (int)$_GET['leagueid'];
$pr_league=$crud->getPLbyID($leagueid);
$gameweek = $crud->getGameweek($pr_league['league_id']);
$week = $gameweek['gameweek'];

//ha nem vagy a liga tagja, bye bye    
$compcheck=$crud->getCompetitorID($_SESSION['profile_id'],$pr_league['league_id']);
$membercheck=$crud->checkMembership($pr_league['privateleague_id'],$compcheck['competitor_id']);






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

    .table th{
        background-color: #e1f7f4;
        border-bottom: 1px solid lightblue;
        font-size: 14px;
    }

    .prleagueheader{
        display: flex;
        flex-direction: row;
        justify-content:space-between;
    }
    .title{
        margin-bottom: 2rem;
    }

    .title h6{
        font-style: italic;
    }

    .edit a{
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        margin-left: 10px;
        display: inline-flex;
        background-color: #3aafa9;
        color: white;
        text-decoration: none;
    }

    .edit a:hover {
        background-color: #308986;
    }

    .myleague-btn {
        display: block;
        text-align: center;
        margin: 4rem 0;
    }

    .myleague-btn a {
        font-size: 14px;
        padding: 10px 30px;
        background-color: #2b7a78;
        color: white;
        border: none;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        transition: background-color 0.3s ease;
    }

    .myleague-btn a:hover {
        background-color: #3aafa9;
    }

    @media (min-width:600px){
        .edit{
        margin-right: 30vh;
        }
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
</style>

<h2 style="text-align: center; margin-top: 4vh; margin-bottom: 5vh">
    <?php switch($_SESSION['lang']){
        case 1: echo "Szurkolói liga állása";
        break;
        case 2: echo "Fan league standings";
        break;
        case 3: echo "Fanliga Rangliste";
        break;
    }?>
</h2>
<div class="prleagueheader">
    <div class="title">
        <h2><?php echo $pr_league['leaguename'];?></h2>
    </div>

    <?php
    if($pr_league['admin']==$_SESSION['profile_id']){ ?>

    <div class="edit">
        <a href="editprivateleague.php?leagueid=<?php echo $pr_league['privateleague_id']?>"><?php switch($_SESSION['lang']){
        case 1: echo "Módosítás";
        break;
        case 2: echo "Edit";
        break;
        case 3: echo "Bearbeiten";
        break;
    }?></a>
    </div>
    <?php } ?>
</div>


<?php 
    
    if ($results = $crud->getCompetitorRanked('TP','DESC',$week,$pr_league['league_id'])) {
        
        

?>

<table class="table">
    <tr>
        <?php switch($_SESSION['lang']){
            case 1:
            ?>
                <th >Hely</th>
                <th colspan="2">Csapat</th>
                <th>Edző</th>
                <th>Összes pont</th>
                <th>Heti </th>
                <th>Őszi fordulók </th>
                <th>Tavaszi fordulók </th>
                <th>Csapatérték</th>
            <?php ;
            break;

            case 2:
            ?>
                <th>Rank</th>
                <th colspan="2">Team</th>
                <th>Trainer</th>
                <th>Total ponts </th>
                <th>Weekly </th>
                <th>Fall season </th>
                <th>Spring season </th>
                <th>Team value</th>
            <?php ;
            break;

            case 3:
            ?>
                <th>Platz</th>
                <th colspan="2">Team</th>
                <th>Trainer</th>
                <th>Gesamtpunkte </th>
                <th>Wochenpunkte </th>
                <th>Herbstsaison </th>
                <th>Frühlingssaison </th>
                <th>Teamwert</th>
            <?php ;
            break;
        }?>
        
    </tr>
    <?php $counter=0; while($r = $results->fetch(PDO::FETCH_ASSOC)){ 
        $check=$crud->checkMembership($pr_league['privateleague_id'],$r['competitor_id']);
        if($check['count']==1){
        ?>
        <?php 
            $teamweekly=$crud->getWeeklyteamresult($r['competitor_id'],$week-1); 
            $counter=$counter+1;
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
        <tr <?php if(isset($_SESSION['competitor_id'])){if($r['competitor_id']==$_SESSION['competitor_id']){echo 'style="background-color:#90F0FD"';}}?>>
            <td><?php echo $counter; ?></td>
            <td><?php $picture=$crud->findPicture($r['picture_id']);?><a href="team.php?teamid=<?php echo $r['competitor_id'];?>"><img class="profilepic" src="img/profilepic/<?=$picture['link'] ?>" alt=""></a></td>
            <td><a href="team.php?teamid=<?php echo $r['competitor_id'];?>" style="text-decoration:none; color:black;"><?php echo $r['teamname'];?></a></td>
            <td><?php echo $r['alias'];?></td>
            <td><?php echo $r['TP'];?></td>
            <td><?php echo $r['WP'];?></td>
            <td><?php echo $r['osz'];?></td>
            <td><?php echo $r['tavasz'];?></td>
            <td><?php echo $valuetotal;?></td>

        </tr>
    <?php }}?>
</table>

<?php } ?>

<div class="myleague-btn">
    <a href="fan-leagues.php">
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

<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>