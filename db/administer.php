<?php

    class admin{
        //private database object. I can only interact with it within the class, not outside\
        private $db;

        //a constructor to initialize private variable to the database connection
        function __construct($conn){
            $this->db = $conn; //this always refers to the current object, in this case the class
            }
            //function to enter a new record into attendee database

        public function NOtrade($gameweek,$league_id){
            try {
                $sql = "UPDATE `gameweeks` SET `open`= 0 WHERE `gameweek`= :gameweek AND league_id= :league_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':league_id',$league_id);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function YEStrade($gameweek,$league_id){
            try {
                $sql = "UPDATE `gameweeks` SET `open`= 1 WHERE `gameweek`= :gameweek AND league_id= :league_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':league_id',$league_id);
                $stmt->execute();
                return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function allProfilesByGroups(){
            $sql = "SELECT a.name, count(pr.profile_id) as count FROM `profile` pr inner join authorizations a on pr.authorization=a.authorization GROUP BY a.name ORDER BY a.name";
            $result = $this->db->query($sql);
            return $result;
        }

        public function trollProfiles(){
            $sql = "SELECT * FROM `profile` where authorization= 2";
            $result = $this->db->query($sql);
            return $result;
        }

        public function countRoster($gameweek,$league_id){
            $sql = "SELECT count(r.competitor_id) as count FROM `roster` r left join `competitor` c on r.competitor_id=c.competitor_id where r.gameweek= :gameweek AND c.league_id= :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch(); 
            return $result;
        }

        public function copyRosterstoNextWeek($gameweek,$league_id){
            try{
                $sql = "INSERT INTO `roster`(`competitor_id`, `gameweek`, `player1`, `player2`, `player3`, `player4`, `player5`, `player6`, `player7`, `player8`, `captain`) SELECT r.competitor_id, `gameweek`+1, `player1`, `player2`, `player3`, `player4`, `player5`, `player6`, `player7`, `player8`, `captain` FROM `roster` r left join `competitor` c on r.competitor_id=c.competitor_id where r.gameweek= :gameweek AND c.league_id= :league_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':league_id',$league_id);
                $stmt->execute();
                return true;
            }catch (PDOException $e){
                echo $e->getMessage();
                return false;
            }
        }
        
        public function countPrices($gameweek,$league_id){
            $sql = "SELECT count(pt.player_id) as count FROM `playertrade` pt left join `player` p on pt.player_id=p.player_id where pt.gameweek= :gameweek AND p.league_id= :league_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindparam(':gameweek',$gameweek);
            $stmt->bindparam(':league_id',$league_id);
            $stmt->execute();
            $result = $stmt->fetch(); 
            return $result;
        }

        public function updatePlayerPrice($player_id,$gameweek,$price){
            try{
                $sql = "UPDATE `playertrade` SET `price`=:price WHERE `player_id`= :player_id AND `gameweek`= :gameweek";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':player_id',$player_id);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':price',$price);
                $stmt->execute();
                return true;
            }catch (PDOException $e){
                echo $e->getMessage();
                return false;
            }
        }

        public function copyPricestoNextWeek($gameweek,$league_id){
            try{
                $sql = "INSERT INTO `playertrade`(`player_id`, `gameweek`, `price`) SELECT pt.player_id, `gameweek`+1, `price` FROM `playertrade` pt left join `player` p on pt.player_id=p.player_id where pt.gameweek= :gameweek AND p.league_id= :league_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':gameweek',$gameweek);
                $stmt->bindparam(':league_id',$league_id);
                $stmt->execute();
                return true;
            }catch (PDOException $e){
                echo $e->getMessage();
                return false;
            }
        }

        public function enterNewQuestion($gameweek,$question,$type){
            try{
                $sql = "INSERT INTO `trollbet`(`gameweek`, `question`, `type`, `result`) VALUES (:gameweek, :question, :type, 0)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':gameweek',$gameweek);
                    $stmt->bindparam(':question',$question);
                    $stmt->bindparam(':type',$type);                                    
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }
        
        public function enterCompetitorPoints($competitor_id,$gameweek,$weeklypoints){
            $result = $this->checkCompetitorpoints($competitor_id,$gameweek);
            try{
                if ($result['num'] > 0){
                    $sql = "UPDATE `teamresult` SET `weeklypoints`= :weeklypoints WHERE `competitor_id` = :competitor_id AND gameweek = :gameweek"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':competitor_id',$competitor_id);
                    $stmt->bindparam(':gameweek',$gameweek);
                    $stmt->bindparam(':weeklypoints',$weeklypoints);                               
                    $stmt->execute();
                    return true;
                }else{
                    $sql = "INSERT INTO `teamresult`(`competitor_id`, `gameweek`, `weeklypoints`) VALUES (:competitor_id, :gameweek, :weeklypoints)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':competitor_id',$competitor_id);
                    $stmt->bindparam(':gameweek',$gameweek);
                    $stmt->bindparam(':weeklypoints',$weeklypoints);                               
                    $stmt->execute();
                    return true;}
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function checkCompetitorpoints($competitor_id,$gameweek){
            try{
                $sql = "SELECT count(*) as num FROM `teamresult` WHERE competitor_id = :competitor_id AND gameweek = :gameweek";
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

        public function getRosters($gameweek){
            $sql = "SELECT * FROM `roster` r left join `competitor` c on r.competitor_id=c.competitor_id WHERE gameweek= $gameweek";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getHUCompetitorsForRanking($gameweek){
            $sql = "SELECT c.competitor_id, sum(tr.weeklypoints) FROM `competitor` c inner join `teamresult` tr on tr.competitor_id=c.competitor_id WHERE c.league_id=10 and tr.gameweek <= $gameweek group by c.competitor_id order by sum(tr.weeklypoints) DESC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getDECompetitorsForRanking($gameweek){
            $sql = "SELECT c.competitor_id, sum(tr.weeklypoints) FROM `competitor` c inner join `teamresult` tr on tr.competitor_id=c.competitor_id WHERE c.league_id=20 and tr.gameweek <= $gameweek group by c.competitor_id order by sum(tr.weeklypoints) DESC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function getDEWCompetitorsForRanking($gameweek){
            $sql = "SELECT c.competitor_id, sum(tr.weeklypoints) FROM `competitor` c inner join `teamresult` tr on tr.competitor_id=c.competitor_id WHERE c.league_id=40 and tr.gameweek <= $gameweek group by c.competitor_id order by sum(tr.weeklypoints) DESC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function enterCompetitorRank($competitor_id,$gameweek,$rank){
            try{
                $sql = "INSERT INTO `teamranking`(`competitor_id`, `gameweek`, `rank`) VALUES (:competitor_id, :gameweek, :rank)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':competitor_id',$competitor_id);
                    $stmt->bindparam(':gameweek',$gameweek);
                    $stmt->bindparam(':rank',$rank);                                    
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        public function updateCompetitorRank($competitor_id,$gameweek,$rank){
            try{
                $sql = "UPDATE `teamranking` SET `rank`=:rank WHERE competitor_id= :competitor_id AND gameweek= :gameweek"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':competitor_id',$competitor_id);
                    $stmt->bindparam(':gameweek',$gameweek);
                    $stmt->bindparam(':rank',$rank);                                    
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        //értesítések

        public function newNotification($notification_type,$profile_id,$gameweek,$picture_id){
            try{
                $sql = "INSERT INTO `notification`(`notification_type`, `profile_id`, `gameweek`, `picture_id`) VALUES (:notification_type, :profile_id, :gameweek, :picture_id)"; 
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

        public function fetchNewsletterGroups(){
            $sql = "SELECT name, GROUP_CONCAT(CONCAT(lang, ':', template_id)) as langs FROM newsletter_templates GROUP BY name ORDER BY created_at DESC";
            $result = $this->db->query($sql);
            return $result;
        }

        public function newNewsletterTemplate($name,$lang,$subject,$filepath){
            try{
                $sql = "INSERT INTO `newsletter_templates`(`name`, `lang`, `subject`, `file_path`) VALUES (:tname, :lang, :tsubject, :filepath)"; 
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindparam(':tname',$name);
                    $stmt->bindparam(':lang',$lang);
                    $stmt->bindparam(':tsubject',$subject);
                    $stmt->bindparam(':filepath',$filepath);                                                   
                    $stmt->execute();
                    return true;
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                } 
        }

        



}



        