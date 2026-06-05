
<?php 
$title = "Trollteke";
require_once 'db/conn.php';
require_once 'includes/header.php';
$gameweek = $crud->getGameweek();
$week = $gameweek['gameweek'];
$questions = $crud->getQuestions($week);
$answers = $crud->getAnswers($week);
$results=$crud->getTrollResults();

if(!isset($_SESSION['profile_id']) OR $_SESSION['authorization'] == 1) echo '<script type="text/javascript">location.href="index.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=index.php" /></noscript> ';

if (isset($_POST['submit'])){
    $transform = explode('_',$_POST['submit']);
    $qid=end($transform);
    
    if(isset($_POST["overunder_".$qid])){
        if($_POST["overunder_".$qid] == "over_".$qid){
            $answer=$crud->enterAnswer($_SESSION['profile_id'],$qid,1,0);
        }elseif($_POST["overunder_".$qid] == "under_".$qid){
            $answer=$crud->enterAnswer($_SESSION['profile_id'],$qid,0,0);
        }else{
            
        } ;
    }elseif(isset($_POST["optional_".$qid])){
        $answer=$crud->enterAnswer($_SESSION['profile_id'],$qid,0,$_POST["optional_".$qid]);
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST') echo '<script type="text/javascript">location.href="redirect.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=redirect.php" /></noscript> ';

?>

<style>
    table td {
        vertical-align: middle;
    }
    @media (max-width: 600px) {
        table td {
            padding:0;
        }
        .btn {
            padding:5px;
            font-size:11px;
            margin:0.3rem;

        }
        
    }
</style>


<h2 style="text-align: center; margin-top: 4vh">Trollteke fogadási oldal</h2>
<h6 style="text-align: center; margin-top: 1vh; margin-bottom: 5vh">Itt válik el a szar a májtól</h6>

<div class="container" id="troll">
    <div class="col" id="questions">
            <table class="table caption-top">
                <caption>Eheti nagyszerű kérdéseink</caption>
                <tr>
                    <th>id</th>
                    <th>Fogadási kérdés</th>
                    <th style="text-align: center">Tipp</th>
                    <th></th>
                </tr>
                <?php while($r = $questions->fetch(PDO::FETCH_ASSOC)){ ?>
                    <?php 
                        $check = $crud->checkAnswer($_SESSION['profile_id'], $r['question_id']);
                        if($check['num'] > 0){$questionresult=$crud->getAnswerbyID($_SESSION['profile_id'], $r['question_id']);};
                    ?>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" name="question_<?php echo $r['question_id'];?>">
                    <tr>
                        <td><?php echo $r['question_id'];?></td>
                        <td><?php echo $r['question'];?></td>
                        <td style="text-align: center">
                            <?php 
                                if($r['type'] == 1){?>
                                    
                                    <input type="radio" class="btn-check" name="overunder_<?php echo $r['question_id'];?>" id="over_<?php echo $r['question_id'];?>" value="over_<?php echo $r['question_id'];?>" autocomplete="off" <?php if($check['num'] > 0 and $questionresult['bet'] == 1)echo "checked";?>>
                                    <label class="btn btn-outline-success" for="over_<?php echo $r['question_id'];?>">Over</label>

                                    <input type="radio" class="btn-check" name="overunder_<?php echo $r['question_id'];?>" id="under_<?php echo $r['question_id'];?>" value="under_<?php echo $r['question_id'];?>" autocomplete="off" <?php if($check['num'] > 0 and $questionresult['bet'] == 0)echo "checked";?>>
                                    <label class="btn btn-outline-danger" for="under_<?php echo $r['question_id'];?>">Under</label>
                                
                                <?php }elseif($r['type'] == 2){ ?>
                                    <input type="text" class="form-control" id="optional_<?php echo $r['question_id'];?>" name="optional_<?php echo $r['question_id'];?>" style="border-color:darkblue" placeholder="ide írd a tipped" <?php if($check['num'] > 0) echo 'value="'.$questionresult['textbet'].'"';?>>
                                <?php }?> 
                            
                        </td>
                        <td>
                            <button type="submit" class="btn btn-primary" name="submit" value="submit_<?php echo $r['question_id'];?>">Tippelek</button>
                        </td>
                    </tr>
                    </form>
                <?php }?>        
            </table>
    </div>
    <hr class="divider" id="cut">
    <div class="col" id="answers">
        <table class="table caption-top">
            <caption>Leadott válaszok</caption>
            <tr>
                <th>Játékos</th>
                <th>kérdés #</th>
                <th>Válasz</th>
            </tr>
            <?php while($k = $answers->fetch(PDO::FETCH_ASSOC)){ ?>
                <tr>
                    <td><?php echo $k['alias'];?></td>
                    <td><?php echo $k['question_id'];?></td>
                    <td>
                        <?php 
                            if($k['type'] == 1 AND $k['bet']==1){
                                echo "OVER";
                             }elseif($k['type'] == 1 AND $k['bet']==0){
                                echo "UNDER";
                             }elseif($k['type'] == 2){
                                echo $k['textbet'];
                             }
                             ?> 
                        
                    </td>
                </tr>
                </form>
            <?php }?>        
        </table>
    </div>
    <div class="col" id="toplist">
        <table class="table caption-top">
            <caption>Pontlista</caption>
            <tr>
                <th>Féreg</th>
                <th>Eddigi összes pont</th>
            </tr>
            <?php while($f = $results->fetch(PDO::FETCH_ASSOC)){ ?>
                <tr>
                    <td><?php echo $f['alias'];?></td>
                    <td><?php echo $f['TP'];?></td>
                </tr>
                </form>
            <?php }?>        
        </table>
    </div>
  
</div>



<br>
<br>
<br>
<br>
<?php require_once 'includes/footer.php'; ?>