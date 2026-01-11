<?php 
$title = "Leagues";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if(isset($_POST['apply'])){
    $applypart = explode('_',$_POST['apply']);
    $newmember=$crud->newPLmember($applypart[0],$applypart[1]);
    if($newmember){
        $league=$crud->getPLbyID($applypart[0]);
        $membernoti=$crud->newPictureNotification('D2',$league['admin'],1,$league['privateleague_id']);
    }
}


?>

<style>
    
    table{
        max-width: 800px;
        margin: auto;
        text-align: center;
    }
    table tr {
        vertical-align: middle;
        color:#170202;
        font-size: 12px;
    }
    table tr:hover{
        background-color: #f0f1f3;
    }
    .table th{
        background-color: #e1f7f4;
        border-bottom: 1px solid lightblue;
        font-size: 14px;
    }
    table th a{
        color:#170202;
    }
    h2{color:#170202}
    

    table th:nth-of-type(1) {
        text-align: left;
    }
    table td:nth-of-type(1) {
        text-align: left;
    }

    .apply{
        padding: 0px 15px;
    }
    .btn-custom {
        padding: 0px 15px;
        border-radius: 6px;
        font-size: 14px;
    }
    .btn-waiting {
        background-color: #ffcc00;
        color: black;
    }

    table td a {
        color: #0b2423;
        text-decoration: none;
        font-weight: bold;
    }



    @media (max-width: 475px) {

        .table{
            max-width: 100%;
        }
        
        .table th {
            font-size:11px;
        }
        .table td {
            font-size:12px;
            
        }

        /* table td:nth-of-type(1),table td:nth-of-type(2) {
            word-break: break-all;
        } */
        
    }
</style>

<h2 style="text-align: center; margin-top: 4vh; margin-bottom: 5vh">
    <?php switch($_SESSION['lang']){
        case 1: echo "Fantasy 9pin Privát Ligák";
        break;
        case 2: echo "Fantasy 9pin Private Leagues";
        break;
        case 3: echo "Fantasy 9pin Private Ligen";
        break;
    }?>
</h2>

<?php
$columns = array('league_name','admin_name','league_type','members', 'average_result');
$column = isset($_GET['column']) && in_array($_GET['column'], $columns) ? $_GET['column'] : $columns[0];
$sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'ASC' : 'DESC';

if ($results = $crud->listPrivateLeagues($column,$sort_order)) {
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
                <th><a href="private_leagues.php?column=league_name&order=<?php echo $asc_or_desc; ?>">Liga név <i class="<?php echo $column == 'league_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=admin_name&order=<?php echo $asc_or_desc; ?>">Admin <i class="<?php echo $column == 'admin_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=league_type&order=<?php echo $asc_or_desc; ?>">Liga <i class="<?php echo $column == 'league_type' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=members&order=<?php echo $asc_or_desc; ?>">Tagok <i class="<?php echo $column == 'members' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=average_result&order=<?php echo $asc_or_desc; ?>">Átlag pont <i class="<?php echo $column == 'average_result' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th></th>
            <?php ;
            break;

            case 2:
            ?>
                <th><a href="private_leagues.php?column=league_name&order=<?php echo $asc_or_desc; ?>">League name <i class="<?php echo $column == 'league_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=admin_name&order=<?php echo $asc_or_desc; ?>">Admin name<i class="<?php echo $column == 'admin_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=league_type&order=<?php echo $asc_or_desc; ?>">League <i class="<?php echo $column == 'league_type' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=members&order=<?php echo $asc_or_desc; ?>">Members <i class="<?php echo $column == 'members' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=average_result&order=<?php echo $asc_or_desc; ?>">Avg result <i class="<?php echo $column == 'average_result' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th></th>
            <?php ;
            break;

            case 3:
            ?>
                <th><a href="private_leagues.php?column=league_name&order=<?php echo $asc_or_desc; ?>">Liganame <i class="<?php echo $column == 'league_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=admin_name&order=<?php echo $asc_or_desc; ?>">Admin <i class="<?php echo $column == 'admin_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=league_type&order=<?php echo $asc_or_desc; ?>">Liga <i class="<?php echo $column == 'league_type' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=members&order=<?php echo $asc_or_desc; ?>">Mitglieder <i class="<?php echo $column == 'members' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="private_leagues.php?column=average_result&order=<?php echo $asc_or_desc; ?>">∅ Pkt <i class="<?php echo $column == 'average_result' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th></th>
            <?php ;
            break;
        }?>
        
    </tr>
    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
    <?php $counter=0; while($r = $results->fetch(PDO::FETCH_ASSOC)){ ?>
        <?php 
             
            $counter=$counter+1;
            $compcheck=$crud->getCompetitorID($_SESSION['profile_id'],$r['league_type']);
            if($compcheck){
                $membercheck=$crud->checkMembership($r['privateleague_id'],$compcheck['competitor_id']);
        ?>
        <tr>
            <td><?php if($membercheck['count']==1){ ?>
                <a href="privateleague.php?leagueid=<?php echo $r['privateleague_id']?>"><?php echo $r['league_name']?></a>
            <?php }else{echo $r['league_name'];} ?></td>
            <td><?php echo $r['admin_name'] ?></td>
            <td><?php if($r['league_type']==10){echo "🇭🇺";}elseif($r['league_type']==20){echo "🇩🇪 Men";}elseif($r['league_type']==40){echo "🇩🇪 Women";};?></td>
            <td><?php echo $r['members'];?></td>
            <td><?php echo number_format($r['average_result'],1);?></td>
            <td>
                <?php  
                $applicationcheck=$crud->checkApplication($r['privateleague_id'],$compcheck['competitor_id']);
                if($applicationcheck['count']==1){ ?>
                    <button class="btn-custom btn-waiting" disabled><i class="bi bi-hourglass-split"></i></button>
                <?php }elseif($membercheck['count']<1){?>
                    <button type="submit" class="btn btn-info apply" name="apply" value="<?php echo $r['privateleague_id']."_".$compcheck['competitor_id'];?>" >
                        <i class="bi bi-person-fill-up"></i>
                    </button>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
    <?php }?>
    </form>
</table>

<?php } ?>




<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>