<?php

    class crud{
        //private database object. I can only interact with it within the class, not outside\
        private $db;

        //a constructor to initialize private variable to the database connection
        function __construct($conn){
            $this->db = $conn; //this always refers to the current object, in this case the class
            }
            //function to enter a new record into attendee database
        
        public function insertCompetitor($profile_id,$teamname,$credits,$league_id){
            try {
                //define sql statement to be executed
                $sql = "INSERT INTO competitor (profile_id, teamname, credits,league_id) VALUES (:profile_id, :teamname, :credits, :league_id)"; //these values are just placeholders for security reasons
                //prepare the sql statment for execution
                $stmt = $this->db->prepare($sql);
                //bind all values to the actual values
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':teamname',$teamname);
                $stmt->bindparam(':credits',$credits);
                $stmt->bindparam(':league_id',$league_id);
                //execute the statement
                $stmt->execute();
                return true;
        
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function insertRoster($competitor_id,$gameweek,$player1,$player2,$player3,$player4,$player5,$player6,$player7,$player8){
            try {
                //define sql statement to be executed
                $sql = "INSERT INTO `roster`(`competitor_id`, `gameweek`, `player1`, `player2`, `player3`, `player4`, `player5`, `player6`, `player7`, `player8`) VALUES  (:competitor_id, :gameweek, :player1, :player2, :player3, :player4, :player5, :player6, :player7, :player8)";
                $stmt = $this->db->prepare($sql);
                
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':player1',$player1);
                $stmt->bindparam(':player2',$player2);
                $stmt->bindparam(':player3',$player3);
                $stmt->bindparam(':player4',$player4);
                $stmt->bindparam(':player5',$player5);
                $stmt->bindparam(':player6',$player6);
                $stmt->bindparam(':player7',$player7);
                $stmt->bindparam(':player8',$player8);
                
                $stmt->execute();
                return true;
        
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function insertRosterwithCap($competitor_id,$gameweek,$player1,$player2,$player3,$player4,$player5,$player6,$player7,$player8,$captain){
            try {
                //define sql statement to be executed
                $sql = "INSERT INTO `roster`(`competitor_id`, `gameweek`, `player1`, `player2`, `player3`, `player4`, `player5`, `player6`, `player7`, `player8`, `captain`) VALUES  (:competitor_id, :gameweek, :player1, :player2, :player3, :player4, :player5, :player6, :player7, :player8, :captain)";
                $stmt = $this->db->prepare($sql);
                
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':player1',$player1);
                $stmt->bindparam(':player2',$player2);
                $stmt->bindparam(':player3',$player3);
                $stmt->bindparam(':player4',$player4);
                $stmt->bindparam(':player5',$player5);
                $stmt->bindparam(':player6',$player6);
                $stmt->bindparam(':player7',$player7);
                $stmt->bindparam(':player8',$player8);
                $stmt->bindparam(':captain',$captain);
                
                $stmt->execute();
                return true;
        
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function insertTransfer($competitor_id,$gameweek,$playerout,$playerin){
            try {
                if($playerin > 90000 OR $playerout > 90000){
                    $sql = "INSERT INTO `transfers`(`competitor_id`, `gameweek`, `playerout`, `playerin`,`normal`) VALUES  (:competitor_id, :gameweek, :playerout, :playerin,0)";
                }else{
                    $sql = "INSERT INTO `transfers`(`competitor_id`, `gameweek`, `playerout`, `playerin`) VALUES  (:competitor_id, :gameweek, :playerout, :playerin)";
                }
                $stmt = $this->db->prepare($sql);
                
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':playerout',$playerout);
                $stmt->bindparam(':playerin',$playerin);
                
                $stmt->execute();
                return true;
        
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getTransfers($competitor_id,$gameweek){
            try{
                $sql = "SELECT sum(`normal`) as num, count(transfer_id) as total FROM `transfers` WHERE competitor_id = :competitor_id AND gameweek= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function getAllTransfers($competitor_id){
            try{
                $sql = "SELECT sum(`normal`) as num, count(transfer_id) as total FROM `transfers` WHERE competitor_id = :competitor_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function getWeeklyTransfers($competitor_id,$gameweek){
                $sql = "SELECT * FROM `transfers` WHERE competitor_id = $competitor_id AND gameweek= $gameweek";
                $result = $this->db->query($sql);
                return $result;
        }

        public function getCaptainsLast4weeks($competitor_id,$gameweek){
            $sql = "SELECT COUNT(DISTINCT captain) AS count FROM roster WHERE competitor_id = :competitor_id AND gameweek BETWEEN :gameweek - 3 AND :gameweek";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':competitor_id',$competitor_id);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->execute();
            $result = $stmt->fetch(); 
            return $result;
        }

        public function getRoster($competitor_id,$gameweek){
            $sql = "SELECT * FROM `roster` WHERE competitor_id= :competitor_id AND gameweek= :gameweek";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':competitor_id',$competitor_id);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->execute();
            $result = $stmt->fetch(); 
            return $result;
        }

        public function getRosterwithDetails($competitor_id,$gameweek){
            $sql = "SELECT * FROM `roster` r inner join competitor c on r.competitor_id=c.competitor_id WHERE r.competitor_id= :competitor_id AND r.gameweek= :gameweek";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':competitor_id',$competitor_id);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->execute();
            $result = $stmt->fetch(); 
            return $result;
        }

        public function existRoster($competitor_id,$gameweek){
            try{
                $sql = "SELECT count(*) as num FROM `roster` WHERE competitor_id = :competitor_id AND gameweek= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function updateCaptain($competitor_id,$captain,$gameweek){
            try {
                $sql = "UPDATE `roster` SET `captain`= :captain WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':captain',$captain);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function transferToRoster($competitor_id,$gameweek,$playerout,$playerin){
            try{
                $sql="UPDATE `roster` SET `player1`=REPLACE(`player1`, :playerout, :playerin),`player2`=REPLACE(`player2`,:playerout, :playerin),`player3`=REPLACE(`player3`,:playerout, :playerin),`player4`=REPLACE(`player4`,:playerout, :playerin),`player5`=REPLACE(`player5`,:playerout, :playerin),`player6`=REPLACE(`player6`,:playerout, :playerin),`player7`=REPLACE(`player7`,:playerout, :playerin),`player8`=REPLACE(`player8`,:playerout, :playerin) WHERE `competitor_id`= :competitor_id AND `gameweek`= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':playerout',$playerout);
                $stmt->bindparam(':playerin',$playerin);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function updateSubstitute1($competitor_id,$player_id,$gameweek){
            try {                
                $sql = "UPDATE `roster` SET `player1`=`player7`,`player7`=`player8`, `player8`= :player_id WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }
        public function updateSubstitute2($competitor_id,$player_id,$gameweek){
            try {                
                $sql = "UPDATE `roster` SET `player2`=`player7`,`player7`=`player8`, `player8`= :player_id WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }
        public function updateSubstitute3($competitor_id,$player_id,$gameweek){
            try {                
                $sql = "UPDATE `roster` SET `player3`=`player7`,`player7`=`player8`, `player8`= :player_id WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }
        public function updateSubstitute4($competitor_id,$player_id,$gameweek){
            try {                
                $sql = "UPDATE `roster` SET `player4`=`player7`,`player7`=`player8`, `player8`= :player_id WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }
        public function updateSubstitute5($competitor_id,$player_id,$gameweek){
            try {                
                $sql = "UPDATE `roster` SET `player5`=`player7`,`player7`=`player8`, `player8`= :player_id WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }

        }
        
        public function updateSubstitute6($competitor_id,$player_id,$gameweek){
            try {                
                $sql = "UPDATE `roster` SET `player6`=`player7`,`player7`=`player8`, `player8`= :player_id WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function switchSubstitutes($competitor_id,$player_id,$gameweek){
            try {                
                $sql = "UPDATE `roster` SET `player7`=`player8`, `player8`= :player_id WHERE `competitor_id` = :competitor_id and gameweek= :gameweek";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getGameweek($league_id){
            $sql = "SELECT * FROM `gameweeks` WHERE `league_id` = :league_id and curdate() BETWEEN datefrom AND dateto" ;
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
                
        }

        public function checkGameweek($gameweek,$league_id){
            $sql = "SELECT * FROM `gameweeks` WHERE gameweek= :gameweek AND `league_id` = :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch(); 
            return $result;
        }

        public function checkDeadline($league_id, $gameweek){
            $sql = "SELECT IF(curdate() <= `deadline`,1,0) FROM `gameweeks` WHERE gameweek= :gameweek AND `league_id` = :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch(); 
            return $result;
        }
        
        public function getCompetitorID($profile_id,$league_id){
            $sql = "SELECT * FROM `competitor` WHERE profile_id = :profile_id AND `league_id` = :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':profile_id',$profile_id);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getAllProfiles(){
            $sql = "SELECT * FROM `profile`";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getCompetitorCount($profile_id){
            $sql = "SELECT count(competitor_id) as count FROM `competitor` WHERE profile_id = :profile_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':profile_id',$profile_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function countAllCompetitor(){
            $sql = "SELECT count(DISTINCT profile_id) as count FROM `competitor`";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function listAllCompetitorsForWeek($gameweek){
            $sql = "SELECT * FROM `competitor` c inner join teamresult t on c.competitor_id=t.competitor_id inner join teamranking tr on c.competitor_id=tr.competitor_id WHERE t.gameweek=$gameweek AND tr.gameweek=$gameweek";
            $result = $this->db->query($sql);
            return $result;
        }

        public function listAllCompetitorsWithRosters($gameweek){
            $sql = "SELECT * FROM `competitor` c inner join roster r on c.competitor_id=r.competitor_id WHERE r.gameweek=$gameweek";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getCompetitorInLeague($profile_id,$league_id){
            $sql = "SELECT count(competitor_id) as count FROM `competitor` WHERE profile_id = :profile_id and `league_id` = :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':profile_id',$profile_id);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getAllCompetitorForProfile($profile_id){
            $sql = "SELECT * FROM `competitor` WHERE profile_id = $profile_id";
            $result = $this->db->query($sql);
            return $result;
        }


        public function updateCredits($competitor_id,$credits){
            try {
                $sql = "UPDATE `competitor` SET `credits`= :credits WHERE `competitor_id` = :competitor_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':credits',$credits);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function updateTeamname($competitor_id,$teamname){
            try {
                $sql = "UPDATE `competitor` SET `teamname`= :teamname WHERE `competitor_id` = :competitor_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':teamname',$teamname);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function updateFavoriteTeam($competitor_id,$favorite_team_id){
            $competitor=$this->getCompetitor($competitor_id);
            if($competitor['favorite_team_id'] == 0 && $competitor['favorite_team_changed'] == 0){
                $favorite_team_changed = 0;}
                else{
                    $favorite_team_changed = 1;
                }
            try {
                $sql = "UPDATE `competitor` SET `favorite_team_id`= :favorite_team_id,`favorite_team_changed`= :favorite_team_changed WHERE `competitor_id` = :competitor_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':favorite_team_id',$favorite_team_id);
                $stmt->bindparam(':favorite_team_changed',$favorite_team_changed);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getTotalteamresult($competitor_id,$gameweek){
            try{
                $sql = "SELECT sum(`weeklypoints`) AS 'totalpoints' FROM `teamresult` WHERE competitor_id = :competitor_id AND gameweek < :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getWeeklyteamresult($competitor_id,$gameweek){
            try{
                $sql = "SELECT `weeklypoints` FROM `teamresult` WHERE competitor_id = :competitor_id AND gameweek= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getWeeklyResultwithDetails($competitor_id,$gameweek){
            try{
                $sql = "SELECT * FROM competitor c INNER JOIN `teamresult` t on c.competitor_id=t.competitor_id WHERE c.competitor_id = :competitor_id AND t.gameweek= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getWeeklyRankwithDetails($competitor_id,$gameweek){
            try{
                $sql = "SELECT * FROM competitor c INNER JOIN `teamranking` t on c.competitor_id=t.competitor_id WHERE c.competitor_id = :competitor_id AND t.gameweek= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getTeamresultcount($competitor_id,$gameweek){
            try{
                $sql = "SELECT count(`weeklypoints`) AS 'count' FROM `teamresult` WHERE competitor_id = :competitor_id AND gameweek < :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getTeamresultMax($competitor_id){
            try{
                $sql = "SELECT max(`weeklypoints`) AS 'max' FROM `teamresult` WHERE competitor_id = :competitor_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getHighestTeamresult($gameweek,$league_id){
            try{
                $sql = "SELECT * FROM `teamresult` tr inner join competitor c on tr.competitor_id=c.competitor_id WHERE tr.gameweek = :gameweek AND c.league_id= :league_id ORDER BY tr.weeklypoints DESC LIMIT 1";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':league_id',$league_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getAverageTeamresult($gameweek){
            try{
                $sql = "SELECT avg(`weeklypoints`) AS 'avg' FROM `teamresult` WHERE gameweek = :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getDetailedTeamresult($competitor_id){
            $sql = "SELECT * FROM `teamresult` tr INNER JOIN `teamranking` rank on tr.gameweek = rank.gameweek WHERE tr.competitor_id=$competitor_id AND rank.competitor_id=$competitor_id";
            $result = $this->db->query($sql);
            return $result;
        }


        public function getTeamrank($competitor_id,$gameweek){
            try{
                $sql = "SELECT `rank` FROM `teamranking` WHERE competitor_id = :competitor_id AND `gameweek`= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function getCompetitorRanked($column, $sort_order, $gameweek, $league_id){
            $gameweekx = $gameweek - 1;
            $sql = "SELECT 
                c.competitor_id, 
                c.teamname, 
                p.alias, 
                p.picture_id, 
                COALESCE(SUM(tr.weeklypoints),0) as TP,
                COALESCE(SUM(CASE WHEN tr.gameweek = $gameweekx THEN tr.weeklypoints ELSE 0 END),0) AS WP,
                COALESCE(SUM(CASE WHEN tr.gameweek<=9 THEN tr.weeklypoints ELSE 0 END),0) as osz,
                COALESCE(SUM(CASE WHEN tr.gameweek>9 THEN tr.weeklypoints ELSE 0 END),0) as tavasz
            FROM competitor c
            INNER JOIN profile p ON c.profile_id = p.profile_id
            LEFT JOIN teamresult tr ON tr.competitor_id = c.competitor_id AND tr.gameweek < $gameweek
            WHERE c.league_id = $league_id
            GROUP BY c.competitor_id
            ORDER BY $column $sort_order";
    $result = $this->db->query($sql);
    return $result;
        }


        public function getCompetitor($competitor_id){
            $sql = "SELECT * FROM `competitor` c inner join `profile` p on c.profile_id=p.profile_id WHERE competitor_id= :competitor_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':competitor_id',$competitor_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }


        public function checkHometeam($team_id,$gameweek,$league_id){
            try{
                $sql = "SELECT count(*) as num FROM `matches` WHERE hometeam = :team_id AND gameweek= :gameweek AND league_id=:league_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':team_id',$team_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':league_id',$league_id);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function getNextopponent($team_id,$gameweek,$league_id){
            try{
                $home=$this->checkHometeam($team_id,$gameweek,$league_id);
                if($home['num']==1){
                    $sql = "SELECT * FROM `matches` a inner join team s on a.awayteam = s.team_id WHERE hometeam = :team_id AND gameweek= :gameweek AND a.league_id=:league_id";
                }else{
                    $sql = "SELECT * FROM `matches` a inner join team s on a.hometeam = s.team_id WHERE awayteam = :team_id AND gameweek= :gameweek AND a.league_id=:league_id";
                }
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':team_id',$team_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':league_id',$league_id);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function getMatches($gameweek,$league_id){
            $sql = "SELECT * FROM `matches` WHERE gameweek= $gameweek AND league_id=$league_id";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getTeamsinLeague($league_id){
            $sql = "SELECT * FROM `team` WHERE league_id=$league_id ORDER BY `name` ASC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function teamname($team_id){
            $sql = "SELECT * FROM `team` WHERE team_id= :team_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':team_id',$team_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function findPicture($picture_id){
            $sql = "SELECT * FROM `pictures` WHERE picture_id= :picture_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':picture_id',$picture_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }



        //trollteke

        public function getQuestions($gameweek){
            $sql = "SELECT * FROM `trollbet` WHERE gameweek= $gameweek";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getAnswers($gameweek){
            $sql = "SELECT * FROM `trollanswers` ta inner join trollbet tb on ta.question_id = tb.question_id inner join `profile` p on ta.profile_id=p.profile_id WHERE tb.gameweek= $gameweek";
            $result = $this->db->query($sql);
            return $result;
        }

        public function checkAnswer($profile_id, $question_id){
            try{
                $sql = "SELECT count(*) as num FROM `trollanswers` WHERE profile_id = :profile_id AND question_id = :question_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':question_id',$question_id);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function getAnswerbyID($profile_id, $question_id){
            try{
                $sql = "SELECT * FROM `trollanswers` WHERE profile_id = :profile_id AND `question_id`= :question_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':question_id',$question_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return $result;    
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        
        public function enterAnswer($profile_id, $question_id, $bet, $textbet){
            try {
                $result = $this->checkAnswer($profile_id, $question_id);
                if ($result['num'] > 0){
                    $sql = "UPDATE `trollanswers` SET `bet`= :bet, textbet = :textbet WHERE `question_id` = :question_id AND profile_id = :profile_id";
                    $stmt = $this->db->prepare($sql);
                    
                    $stmt->bindparam(':question_id',$question_id);
                    $stmt->bindparam(':profile_id',$profile_id);
                    $stmt->bindparam(':bet',$bet);
                    $stmt->bindparam(':textbet',$textbet);
                                    
                    $stmt->execute();
                    return true;
                }else{
                    $sql = "INSERT INTO `trollanswers`(`question_id`, `profile_id`, `bet`, `textbet`) VALUES (:question_id, :profile_id, :bet, :textbet)"; 
                    $stmt = $this->db->prepare($sql);
                    
                    $stmt->bindparam(':question_id',$question_id);
                    $stmt->bindparam(':profile_id',$profile_id);
                    $stmt->bindparam(':bet',$bet);
                    $stmt->bindparam(':textbet',$textbet);
                                    
                    $stmt->execute();
                    return true;
                }
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function getTrollResults(){
                $sql = "SELECT p.alias, sum(trp.points) AS 'TP' FROM `trollpoints` trp inner join `profile` p on trp.profile_id = p.profile_id GROUP BY trp.profile_id ORDER BY TP DESC";
                $result = $this->db->query($sql);
            return $result;
        }

        //hírek

        public function fetchNews($lang_id){
            $sql = "SELECT * FROM `news` WHERE `live`=1 AND lang_id= $lang_id ORDER BY published_on DESC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getArticle($news_id,$lang_id){
            $sql = "SELECT * FROM `news` WHERE news_id= :news_id AND lang_id= :lang_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':news_id',$news_id);
            $stmt->bindparam(':lang_id',$lang_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getOtherArticles($news_id){
            $sql = "SELECT * FROM `news` WHERE news_id != :news_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':news_id',$news_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        //profil törlés

        public function deleteTeamranking($competitor_id){
            try {
                $sql = "DELETE FROM `teamranking` WHERE competitor_id = :competitor_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return true;
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }
        
        public function deleteTeamresult($competitor_id){
            try {
                $sql = "DELETE FROM `teamresult` WHERE competitor_id = :competitor_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return true;
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function deleteTeamtransfers($competitor_id){
            try {
                $sql = "DELETE FROM `transfers` WHERE competitor_id = :competitor_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return true;
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function deleteTeamroster($competitor_id){
            try {
                $sql = "DELETE FROM `roster` WHERE competitor_id = :competitor_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return true;
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function deleteCompetitor($competitor_id){
            try {
                $sql = "DELETE FROM `competitor` WHERE competitor_id = :competitor_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return true;
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function deleteProfile($profile_id){
            try {
                $sql = "DELETE FROM `profile` WHERE profile_id = :profile_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->execute();
                $result = $stmt->fetch();
                return true;
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }


        //szavazásos függvények
        
        public function getOpenSurveyNumber($league_id){
            $sql = "SELECT count(*) as surveycount from `votingtopics` WHERE `open`= 1 AND league_id= :league_id AND curdate() <= `end_date`";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
            
        }

        public function getSurveys($league_id){
            $sql = "SELECT * from `votingtopics` WHERE `open`= 1 AND league_id= $league_id AND curdate() <= `end_date`";
            $result = $this->db->query($sql);
            return $result;
            
        }

        public function getSurveybyID($survey_id){
            $sql = "SELECT * FROM `votingtopics` WHERE survey_id= :survey_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':survey_id',$survey_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getOptions($survey_id){
            $sql = "SELECT * FROM `votingoptions` WHERE survey_id= $survey_id";
            $result = $this->db->query($sql);
            return $result;
        }

        public function insertVote($survey_id,$competitor_id,$option_id){
            try {
                $sql = "INSERT INTO `votes`(`survey_id`, `competitor_id`, `option_id`) VALUES  (:survey_id, :competitor_id, :option_id)";
                $stmt = $this->db->prepare($sql);
                
                $stmt->bindparam(':survey_id',$survey_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':option_id',$option_id);
                                
                $stmt->execute();
                return true;
        
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function updateVote($survey_id,$competitor_id,$option_id){
            try {
                $sql = "UPDATE `votes` SET `option_id`=:option_id WHERE `survey_id`=:survey_id AND `competitor_id`=:competitor_id";
                $stmt = $this->db->prepare($sql);
                
                $stmt->bindparam(':survey_id',$survey_id);
                $stmt->bindparam(':competitor_id',$competitor_id);
                $stmt->bindparam(':option_id',$option_id);
                                
                $stmt->execute();
                return true;
        
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function checkVote($survey_id,$competitor_id){
            $sql = "SELECT * FROM `votes` WHERE survey_id= :survey_id AND `competitor_id`=:competitor_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':survey_id',$survey_id);
            $stmt->bindparam(':competitor_id',$competitor_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        //language

        public function getCurrentLanguage($lang_id){
            $sql = "SELECT * FROM `languages` WHERE lang_id= :lang_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':lang_id',$lang_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getLanguageList(){
            $sql = "SELECT * from `languages`";
            $result = $this->db->query($sql);
            return $result;
        }

        //képek

        public function getAllPictures(){
            $sql = "SELECT * from `pictures` WHERE `secret`=0 ORDER BY `basic` DESC,  picture_id ASC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getBasicPictures(){
            $sql = "SELECT * from `pictures` WHERE `basic`=1 AND `secret`=0";
            $result = $this->db->query($sql);
            return $result;
        }
        
        public function getExtraPicturesForCheck(){
            $sql = "SELECT * from `pictures` WHERE `basic`=0";
            $result = $this->db->query($sql);
            return $result;
        }

        public function countExtraPictureAssignment($picture_id){
            $sql = "SELECT count(profile_id) as count FROM `extrapictures` WHERE picture_id= :picture_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':picture_id',$picture_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getExtraPicturesForProfile($profile_id){
            $sql = "SELECT * from `extrapictures` WHERE `profile_id`= $profile_id ORDER BY `gameweek` DESC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function findExtraPicture($picture_id, $profile_id){
            $sql = "SELECT count(picture_id) as count FROM `extrapictures` WHERE picture_id= :picture_id AND profile_id= :profile_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':picture_id',$picture_id);
            $stmt->bindparam(':profile_id',$profile_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getPicture($picture_id){
            $sql = "SELECT * FROM `pictures` WHERE picture_id= :picture_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':picture_id',$picture_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getPictureText($picture_id,$lang_id){
            $sql = "SELECT * FROM `picturetexts` WHERE picture_id= :picture_id AND lang_id= :lang_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':picture_id',$picture_id);
            $stmt->bindparam(':lang_id',$lang_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function newExtraPicture($profile_id,$picture_id,$gameweek){
            try{
                $sql = "INSERT INTO `extrapictures`(`profile_id`, `picture_id`, `gameweek`) VALUES (:profile_id, :picture_id, :gameweek)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':profile_id',$profile_id);
                    $stmt->bindparam(':picture_id',$picture_id);
                    $stmt->bindparam(':gameweek',$gameweek);                                                      
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        //notifications

        public function getNotificationTypesForUser($profile_id,$lang_id){
            $sql = "SELECT count(n.notification_id) as count, n.notification_type, nty.navigation, nt.text FROM `notification` n JOIN notificationtype nty on n.notification_type=nty.notification_type JOIN notificationtext nt on nty.notification_type=nt.notification_type WHERE n.profile_id= $profile_id AND n.mark_read=0 AND nt.lang_id= $lang_id GROUP BY n.notification_type";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getAllNotificationsForUser($profile_id,$lang_id){
            $sql = "SELECT n.notification_id, n.notification_type, nt.text FROM `notification` n JOIN notificationtext nt on n.notification_type=nt.notification_type WHERE n.profile_id= $profile_id AND n.mark_read=0 AND nt.lang_id= $lang_id ";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getPictureNotificationsForUser($profile_id,$lang_id){
            $sql = "SELECT n.notification_id, n.notification_type, n.picture_id, p.link, pt.description, nt.text FROM `notification` n JOIN notificationtext nt on n.notification_type=nt.notification_type JOIN pictures p on n.picture_id=p.picture_id JOIN picturetexts pt on p.picture_id=pt.picture_id AND pt.lang_id=$lang_id WHERE n.profile_id= $profile_id AND n.mark_read=0 AND nt.lang_id= $lang_id AND n.notification_type='A1'";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getLeagueDeletionNotificationsForUser($profile_id,$lang_id){
            $sql = "SELECT n.notification_id, n.notification_type, n.picture_id, nt.text, pl.leaguename FROM `notification` n JOIN notificationtext nt on n.notification_type=nt.notification_type JOIN privateleague pl on n.picture_id=pl.privateleague_id WHERE n.profile_id= $profile_id AND n.mark_read=0 AND nt.lang_id= $lang_id AND (n.notification_type='D4' OR n.notification_type='D5')";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getLeagueApplicationNotificationsForUser($profile_id,$lang_id){
            $sql = "SELECT n.notification_id, n.notification_type, pl.privateleague_id, nt.text, pl.leaguename FROM `notification` n JOIN notificationtext nt on n.notification_type=nt.notification_type JOIN privateleague pl on n.picture_id=pl.privateleague_id WHERE n.profile_id= $profile_id AND n.mark_read=0 AND nt.lang_id= $lang_id AND n.notification_type='D2'";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getLeagueConfirmationNotificationsForUser($profile_id,$lang_id){
            $sql = "SELECT n.notification_id, n.notification_type, pl.privateleague_id, nt.text, pl.leaguename FROM `notification` n JOIN notificationtext nt on n.notification_type=nt.notification_type JOIN privateleague pl on n.picture_id=pl.privateleague_id WHERE n.profile_id= $profile_id AND n.mark_read=0 AND nt.lang_id= $lang_id AND (n.notification_type='D1' OR n.notification_type='D3')";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getNotificationsByTypeForUser($profile_id,$notification_type){
            $sql = "SELECT * FROM `notification` WHERE profile_id= $profile_id AND mark_read=0 AND notification_type= $notification_type ";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getPointsNotificationForUser($profile_id,$notification_type){
            $sql = "SELECT * FROM `notification` WHERE profile_id= :profile_id AND mark_read=0 AND notification_type= :notification_type";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':profile_id',$profile_id);
            $stmt->bindparam(':notification_type',$notification_type);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function markNotificationAsRead($notification_id){
            try {
                $sql = "UPDATE `notification` SET `mark_read`= 1 WHERE `notification_id` = :notification_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':notification_id',$notification_id);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function newPictureNotification($notification_type,$profile_id,$gameweek,$picture_id){
            try {
                $sql = "INSERT INTO `notification`(`notification_type`, `profile_id`, `gameweek`, `picture_id`) VALUES (:notification_type,:profile_id,:gameweek,:picture_id)";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':notification_type',$notification_type);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':picture_id',$picture_id);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        //privát ligák
        public function findUserInPrivateLeagues($profile_id){
            $sql = "SELECT * FROM `privateleaguemembers` plm inner join competitor c on plm.competitor_id=c.competitor_id inner join privateleague pl on pl.privateleague_id=plm.privateleague_id WHERE c.profile_id= $profile_id AND pl.admin !=2";
            $result = $this->db->query($sql);
            return $result;
        }

        public function findUserInFanLeagues($profile_id){
            $sql = "SELECT * FROM `privateleaguemembers` plm inner join competitor c on plm.competitor_id=c.competitor_id inner join privateleague pl on pl.privateleague_id=plm.privateleague_id WHERE c.profile_id= $profile_id AND pl.admin =2";
            $result = $this->db->query($sql);
            return $result;
        }

        public function listMembersofLeague($privateleague_id){
            $sql = "SELECT * FROM `privateleaguemembers` plm inner join competitor c on plm.competitor_id=c.competitor_id inner join privateleague pl on pl.privateleague_id=plm.privateleague_id WHERE plm.privateleague_id= $privateleague_id AND plm.confirmed=1 ORDER BY c.teamname ASC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function rankMembersofLeague($privateleague_id){
            $sql = "SELECT pl.privateleague_id, plm.competitor_id, SUM(tr.weeklypoints) AS tpoints FROM privateleague pl INNER JOIN privateleaguemembers plm ON pl.privateleague_id = plm.privateleague_id INNER JOIN teamresult tr ON plm.competitor_id = tr.competitor_id WHERE pl.privateleague_id = $privateleague_id AND plm.confirmed = 1 GROUP BY plm.competitor_id ORDER BY tpoints DESC ";
            $result = $this->db->query($sql);
            return $result;
        }

        public function listApplicantsofLeague($privateleague_id){
            $sql = "SELECT * FROM `privateleaguemembers` plm inner join competitor c on plm.competitor_id=c.competitor_id inner join privateleague pl on pl.privateleague_id=plm.privateleague_id WHERE plm.privateleague_id= $privateleague_id AND plm.confirmed=0 ORDER BY c.teamname ASC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function newPrivateLeague($leaguename,$league_id,$admin){
            try{
                $sql = "INSERT INTO `privateleague`(`leaguename`, `league_id`, `admin`) VALUES (:leaguename, :league_id, :admin)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':leaguename',$leaguename);
                    $stmt->bindparam(':league_id',$league_id);
                    $stmt->bindparam(':admin',$admin);                                                      
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function editPrivateLeague($privateleague_id,$leaguename){
            try{
                $sql = "UPDATE `privateleague` SET `leaguename`= :leaguename WHERE `privateleague_id`= :privateleague_id"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':privateleague_id',$privateleague_id);
                    $stmt->bindparam(':leaguename',$leaguename);                                                    
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function newPLmember($privateleague_id,$competitor_id){
            try{
                $sql = "INSERT INTO `privateleaguemembers`(`privateleague_id`, `competitor_id`, `confirmed`) VALUES (:privateleague_id, :competitor_id, 0)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':privateleague_id',$privateleague_id);
                    $stmt->bindparam(':competitor_id',$competitor_id);                                                     
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function removePLmember($privateleague_id,$competitor_id){
            try{
                $sql = "DELETE FROM `privateleaguemembers` WHERE privateleague_id= :privateleague_id AND competitor_id= :competitor_id"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':privateleague_id',$privateleague_id);
                    $stmt->bindparam(':competitor_id',$competitor_id);                                                     
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function confirmPLmember($privateleague_id,$competitor_id){
            try{
                $sql = "UPDATE `privateleaguemembers` SET `confirmed`=1 WHERE privateleague_id= :privateleague_id AND competitor_id= :competitor_id"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':privateleague_id',$privateleague_id);
                    $stmt->bindparam(':competitor_id',$competitor_id);                                                     
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function newPLmemberbyAdmin($privateleague_id,$competitor_id){
            try{
                $sql = "INSERT INTO `privateleaguemembers`(`privateleague_id`, `competitor_id`, `confirmed`) VALUES (:privateleague_id, :competitor_id, 1)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':privateleague_id',$privateleague_id);
                    $stmt->bindparam(':competitor_id',$competitor_id);                                                     
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function getPLbyName($leaguename,$league_id,$admin){
            $sql = "SELECT * FROM `privateleague` WHERE leaguename= :leaguename AND `admin`= :admin AND league_id= :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':leaguename',$leaguename);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->bindparam(':admin',$admin);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function getPLbyID($privateleague_id){
            $sql = "SELECT * FROM `privateleague` WHERE privateleague_id= :privateleague_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':privateleague_id',$privateleague_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function listCompetitorsForLeagues($league_id){
            $sql = "SELECT * FROM `competitor` c inner join `profile` p on p.profile_id=c.profile_id WHERE c.league_id= $league_id ORDER BY c.teamname ASC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function checkMembership($privateleague_id,$competitor_id){
            $sql = "SELECT count(competitor_id) as count FROM `privateleaguemembers` WHERE privateleague_id= :privateleague_id AND `competitor_id`= :competitor_id AND confirmed=1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':privateleague_id',$privateleague_id);
            $stmt->bindparam(':competitor_id',$competitor_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function checkApplication($privateleague_id,$competitor_id){
            $sql = "SELECT count(competitor_id) as count FROM `privateleaguemembers` WHERE privateleague_id= :privateleague_id AND `competitor_id`= :competitor_id AND confirmed=0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':privateleague_id',$privateleague_id);
            $stmt->bindparam(':competitor_id',$competitor_id);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result;
        }

        public function listPrivateLeagues($column, $sort_order){
            $sql = "SELECT 
                pl.privateleague_id, 
                pl.leaguename AS league_name, 
                p.alias AS admin_name, 
                pl.league_id AS league_type, 
                COUNT(plm.competitor_id) AS members, 
                AVG(tr.average_points) AS average_result
            FROM privateleague pl
            INNER JOIN `profile` p ON p.profile_id = pl.admin
            LEFT JOIN privateleaguemembers plm ON plm.privateleague_id = pl.privateleague_id AND plm.confirmed = 1
            LEFT JOIN competitor c ON c.competitor_id = plm.competitor_id
            LEFT JOIN (
                SELECT competitor_id, AVG(weeklypoints) AS average_points
                FROM teamresult
                GROUP BY competitor_id
            ) tr ON tr.competitor_id = c.competitor_id
            WHERE pl.admin != 2
            GROUP BY pl.privateleague_id
            ORDER BY $column $sort_order";
    $result = $this->db->query($sql);
    return $result;
        }

        public function listFanLeagues($column, $sort_order){
            $sql = "SELECT 
                pl.privateleague_id, 
                pl.leaguename AS league_name, 
                p.alias AS admin_name, 
                pl.league_id AS league_type, 
                COUNT(plm.competitor_id) AS members, 
                AVG(tr.average_points) AS average_result
            FROM privateleague pl
            INNER JOIN `profile` p ON p.profile_id = pl.admin
            LEFT JOIN privateleaguemembers plm ON plm.privateleague_id = pl.privateleague_id
            LEFT JOIN competitor c ON c.competitor_id = plm.competitor_id
            LEFT JOIN (
                SELECT competitor_id, AVG(weeklypoints) AS average_points
                FROM teamresult
                GROUP BY competitor_id
            ) tr ON tr.competitor_id = c.competitor_id
            WHERE pl.admin = 2
            GROUP BY pl.privateleague_id
            ORDER BY $column $sort_order";
    $result = $this->db->query($sql);
    return $result;
        }

        public function deletePLmembersFromLeague($privateleague_id){
            try{
                $sql = "DELETE FROM `privateleaguemembers` WHERE privateleague_id= :privateleague_id"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':privateleague_id',$privateleague_id);                                                     
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function deletePrivateLeague($privateleague_id){
            try{
                $sql = "DELETE FROM `privateleague` WHERE privateleague_id= :privateleague_id"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':privateleague_id',$privateleague_id);                                                     
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }


    }

?>