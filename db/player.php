<?php
    class player{
        private $db;
        function __construct($conn){
            $this->db = $conn;
            }
        
            
        public function getPlayer($league_id){
            $sql = "SELECT * FROM `player` p inner join playertrade s on p.player_id = s.player_id where p.league_id=$league_id AND s.gameweek = (SELECT MAX(gameweek) FROM playertrade pt WHERE pt.player_id = p.player_id) order by s.price desc ;";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getPlayerByWeek($league_id,$gameweek){
            $sql = "SELECT * FROM `player` p inner join playertrade s on p.player_id = s.player_id where p.league_id=$league_id AND s.gameweek = $gameweek order by s.price desc ;";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getPersonell(){
            $sql = "SELECT * FROM `player` p inner join playertrade s on p.player_id = s.player_id where s.gameweek = (SELECT MAX(gameweek) FROM playertrade) AND p.player_id > 90000 order by s.price desc ;";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getPlayerbyID($player_id){
            try{
                $sql = "SELECT * FROM `player` WHERE `player_id` = :player_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function getPlayerforTrade($gameweek){
            $sql = "SELECT * FROM `player` a inner join playertrade s on a.player_id = s.player_id where gameweek = :gameweek";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':gameweek', $gameweek);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getPrice($player_id,$gameweek){
            $sql = "SELECT * FROM `playertrade` where gameweek = :gameweek AND player_id = :player_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':gameweek', $gameweek);
            $stmt->bindparam(':player_id', $player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getPlayerteam($player_id){
            $sql = "SELECT * FROM `player` a inner join team s on a.team_id = s.team_id where player_id = :player_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id', $player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getPlayedgames($player_id){
            $sql = "SELECT sum(`played`) as playedgames FROM `playerresult` where player_id = :player_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id', $player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getTotalPlayerpoints($player_id){
            $sql = "SELECT sum(`points`) AS 'totalpoints' FROM `playerresult` WHERE player_id = :player_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;    
        }

        public function getWeeklyPlayerpoints($player_id,$gameweek){
            $sql = "SELECT sum(`points`) as weekpoints, sum(matchpoints) as MP FROM `playerresult` WHERE player_id = :player_id AND gameweek= :gameweek";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;    
        }

        public function getPlayerresultForWeek($player_id,$gameweek){
            $sql = "SELECT * FROM `playerresult` WHERE player_id = :player_id AND gameweek= :gameweek";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;    
        }

        public function getPlayerstatistics($column, $sort_order, $gameweek, $league){
            $gameweekx=$gameweek-1;
            $sql = "SELECT pt.player_id, p.playername, t.name, pt.price, avg(CASE WHEN pr.substituted=0 THEN pins END) as pinavg, sum(points) as pointsum, avg(CASE WHEN pr.substituted=0 THEN points END) as pointavg, sum(played) as matches, SUM(CASE WHEN pr.gameweek = $gameweekx THEN points ELSE 0 END) AS WP FROM `playerresult` pr inner join player p on pr.player_id = p.player_id inner join team t on p.team_id = t.team_id left join playertrade pt on pr.player_id = pt.player_id AND pt.gameweek = $gameweek WHERE p.league_id = $league GROUP BY pr.player_id ORDER BY $column $sort_order";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getTeamoftheWeek($gameweek){
            $sql = "SELECT pr.player_id, p.playername, p.team_id, t.name, t.logo, pr.points, pr.pins, pr.setpoints, pr.matchpoints FROM `playerresult` pr inner join player p on pr.player_id=p.player_id inner join team t on p.team_id = t.team_id WHERE pr.gameweek= $gameweek ORDER BY pr.points DESC LIMIT 6";
            $result = $this->db->query($sql);
            return $result; 
        }

        //statisztikák

        public function getTransferOUT($gameweek){
            $sql = "SELECT p.playername, count(*) as count FROM `transfers` t inner join player p on t.playerout=p.player_id WHERE t.gameweek= $gameweek group by t.playerout order by count desc";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getTransferIN($gameweek){
            $sql = "SELECT p.playername, count(*) as count FROM `transfers` t inner join player p on t.playerin=p.player_id WHERE t.gameweek= $gameweek group by t.playerin order by count desc";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getTransferCount($gameweek){
            $sql = "SELECT count(*) as count FROM `transfers` WHERE gameweek= :gameweek";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':gameweek', $gameweek);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getTopCaptains($gameweek){
            $sql = "SELECT p.playername, count(*) as count FROM `roster` r inner join player p on r.captain=p.player_id WHERE r.gameweek= $gameweek group by r.captain order by count desc";
            $result = $this->db->query($sql);
            return $result;
        }

        //játékos oldalhoz függvények

        public function getMainstats($player_id){
            $sql = "SELECT avg(pins) as pinavg, sum(points) as pointsum, avg(points) as pointavg, sum(played) as matches FROM `playerresult` WHERE player_id = :player_id AND substituted=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getHomestats($player_id){
            $sql = "SELECT avg(pins) as pinavg, sum(points) as pointsum, avg(points) as pointavg, sum(played) as matches FROM `playerresult` WHERE player_id = :player_id and homegame=1 AND substituted=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getAwaystats($player_id){
            $sql = "SELECT avg(pins) as pinavg, sum(points) as pointsum, avg(points) as pointavg, sum(played) as matches FROM `playerresult` WHERE player_id = :player_id and homegame=0 AND substituted=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function get1rowstats($player_id){
            $sql = "SELECT avg(pins) as pinavg, sum(points) as pointsum, avg(points) as pointavg, sum(played) as matches FROM `playerresult` WHERE player_id = :player_id and `row`=1 AND substituted=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function get2rowstats($player_id){
            $sql = "SELECT avg(pins) as pinavg, sum(points) as pointsum, avg(points) as pointavg, sum(played) as matches FROM `playerresult` WHERE player_id = :player_id and `row`=2 AND substituted=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function get3rowstats($player_id){
            $sql = "SELECT avg(pins) as pinavg, sum(points) as pointsum, avg(points) as pointavg, sum(played) as matches FROM `playerresult` WHERE player_id = :player_id and `row`=3 AND substituted=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getMainTotalPt($player_id){
            $sql = "SELECT sum(points) as pointsum, sum(played) as matches, sum(substituted) as subs FROM `playerresult` WHERE player_id = :player_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getHomeTotalPt($player_id){
            $sql = "SELECT sum(points) as pointsum, sum(played) as matches, sum(substituted) as subs FROM `playerresult` WHERE player_id = :player_id and homegame=1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getAwayTotalPt($player_id){
            $sql = "SELECT sum(points) as pointsum, sum(played) as matches, sum(substituted) as subs FROM `playerresult` WHERE player_id = :player_id and homegame=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function get1rowTotalPt($player_id){
            $sql = "SELECT sum(points) as pointsum, sum(played) as matches, sum(substituted) as subs FROM `playerresult` WHERE player_id = :player_id and `row`=1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function get2rowTotalPt($player_id){
            $sql = "SELECT sum(points) as pointsum, sum(played) as matches, sum(substituted) as subs FROM `playerresult` WHERE player_id = :player_id and `row`=2";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function get3rowTotalPt($player_id){
            $sql = "SELECT sum(points) as pointsum, sum(played) as matches, sum(substituted) as subs  FROM `playerresult` WHERE player_id = :player_id and `row`=3";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getRosterSelections($player_id,$gameweek){
            $sql = "SELECT COUNT(*) AS occurrence_count
                        FROM roster
                        WHERE gameweek = :gameweek 
                        AND (
                            player1 = :player_id OR
                            player2 = :player_id OR
                            player3 = :player_id OR
                            player4 = :player_id OR
                            player5 = :player_id OR
                            player6 = :player_id OR
                            player7 = :player_id OR
                            player8 = :player_id
                        )";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':player_id',$player_id);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function countAllRosterInleague($league_id,$gameweek){
            $sql = "SELECT count(*) as allroster  FROM `roster` r inner join competitor c on r.competitor_id=c.competitor_id WHERE r.gameweek= :gameweek AND c.league_id= :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getAllMatchesforPlayer($player_id){
            $sql = "SELECT * from playerresult where player_id=$player_id";
            $result = $this->db->query($sql);
            return $result;
        }


        public function getResultsbyMatchID($match_id){
            $sql = "SELECT * from playerresult pr inner join player p on pr.player_id=p.player_id where match_id=$match_id ORDER BY homegame DESC, `row` ASC, starter DESC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getOpponentResult($match_id,$opponent_id){
            $sql = "SELECT *  FROM `playerresult` WHERE match_id= :match_id AND player_id= :opponent_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':match_id',$match_id);
            $stmt->bindparam(':opponent_id',$opponent_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getMatchAvg($match_id){
            $sql = "SELECT sum(pins)/12 as avg  FROM `playerresult` WHERE match_id= :match_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':match_id',$match_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function calculatePoints($roster){
            if(isset($_SESSION['checkweek'])){$weekforrow=$_SESSION['checkweek'];}

            $p1p=$this->getWeeklyPlayerpoints($roster['player1'],$weekforrow);
            $p2p=$this->getWeeklyPlayerpoints($roster['player2'],$weekforrow);
            $p3p=$this->getWeeklyPlayerpoints($roster['player3'],$weekforrow);
            $p4p=$this->getWeeklyPlayerpoints($roster['player4'],$weekforrow);
            $p5p=$this->getWeeklyPlayerpoints($roster['player5'],$weekforrow);
            $p6p=$this->getWeeklyPlayerpoints($roster['player6'],$weekforrow);
            $p7p=$this->getWeeklyPlayerpoints($roster['player7'],$weekforrow);
            $p8p=$this->getWeeklyPlayerpoints($roster['player8'],$weekforrow);
            $cskp=$this->getWeeklyPlayerpoints($roster['captain'],$weekforrow);
            if(!$p1p){$p1=0;}else{$p1=$p1p['weekpoints'];}
            if(!$p2p){$p2=0;}else{$p2=$p2p['weekpoints'];}
            if(!$p3p){$p3=0;}else{$p3=$p3p['weekpoints'];}
            if(!$p4p){$p4=0;}else{$p4=$p4p['weekpoints'];}
            if(!$p5p){$p5=0;}else{$p5=$p5p['weekpoints'];}
            if(!$p6p){$p6=0;}else{$p6=$p6p['weekpoints'];}
            if(!$p7p){$p7=0;}else{$p7=$p7p['weekpoints'];}
            if(!$p8p){$p8=0;}else{$p8=$p8p['weekpoints'];}
            if(!$cskp){$csk=0;}else{$csk=$cskp['weekpoints'];}

            $calculatedpoints=0;
            $missedp=0;
            if($p1==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p1;};
            if($p2==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p2;};
            if($p3==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p3;};
            if($p4==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p4;};
            if($p5==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p5;};
            if($p6==0){$missedp=$missedp+1;}else{$calculatedpoints=$calculatedpoints+$p6;};
            if($missedp==1 AND $p7>0){$calculatedpoints=$calculatedpoints+$p7;}elseif($missedp==1 AND $p7==0){$calculatedpoints=$calculatedpoints+$p8;}elseif($missedp>1){$calculatedpoints=$calculatedpoints+$p7+$p8;};
            $calculatedpoints=$calculatedpoints+$csk;
            return $calculatedpoints;
        }

    }



?>