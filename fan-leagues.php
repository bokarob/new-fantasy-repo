<?php 
$title = "FanLeagues";
require_once 'includes/header.php';
require_once 'db/conn.php';

if(!isset($_SESSION['profile_id'])) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';


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
        case 1: echo "Szurkolói Ligák";
        break;
        case 2: echo "Fan Leagues";
        break;
        case 3: echo "Fan-Ligen";
        break;
    }?>
</h2>

<?php
$columns = array('league_type','league_name','members','average_result');
$column = isset($_GET['column']) && in_array($_GET['column'], $columns) ? $_GET['column'] : $columns[0];
$sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'ASC' : 'DESC';

if ($results = $crud->listFanLeagues($column,$sort_order)) {
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
                <th><a href="fan-leagues.php?column=league_name&order=<?php echo $asc_or_desc; ?>">Csapat <i class="<?php echo $column == 'league_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=league_type&order=<?php echo $asc_or_desc; ?>">Liga <i class="<?php echo $column == 'league_type' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=members&order=<?php echo $asc_or_desc; ?>">Tagok <i class="<?php echo $column == 'members' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=average_result&order=<?php echo $asc_or_desc; ?>">Átlag pont <i class="<?php echo $column == 'average_result' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
            <?php ;
            break;

            case 2:
            ?>
                <th><a href="fan-leagues.php?column=league_name&order=<?php echo $asc_or_desc; ?>">Team <i class="<?php echo $column == 'league_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=league_type&order=<?php echo $asc_or_desc; ?>">League <i class="<?php echo $column == 'league_type' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=members&order=<?php echo $asc_or_desc; ?>">Members <i class="<?php echo $column == 'members' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=average_result&order=<?php echo $asc_or_desc; ?>">Avg result <i class="<?php echo $column == 'average_result' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
            <?php ;
            break;

            case 3:
            ?>
                <th><a href="fan-leagues.php?column=league_name&order=<?php echo $asc_or_desc; ?>">Teamname <i class="<?php echo $column == 'league_name' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=league_type&order=<?php echo $asc_or_desc; ?>">Liga <i class="<?php echo $column == 'league_type' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=members&order=<?php echo $asc_or_desc; ?>">Mitglieder <i class="<?php echo $column == 'members' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
                <th><a href="fan-leagues.php?column=average_result&order=<?php echo $asc_or_desc; ?>">∅ Pkt <i class="<?php echo $column == 'average_result' ? 'bi bi-sort-' . $up_or_down : ''; ?>"></i></a></th>
            <?php ;
            break;
        }?>
        
    </tr>
    <?php $counter=0; while($r = $results->fetch(PDO::FETCH_ASSOC)){ ?>
        <?php 
             
            $counter=$counter+1;
            
        ?>
        <tr>
            <td>
                <a href="fanleague.php?leagueid=<?php echo $r['privateleague_id']?>"><?php echo $r['league_name']?></a>
            </td>
            <td><?php if($r['league_type']==10){echo "🇭🇺";}elseif($r['league_type']==20){echo "🇩🇪 Men";}elseif($r['league_type']==40){echo "🇩🇪 Women";};?></td>
            <td><?php echo $r['members'];?></td>
            <td><?php echo number_format($r['average_result'],1);?></td>
        </tr>
        
    <?php }?>
</table>

<?php } ?>




<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>