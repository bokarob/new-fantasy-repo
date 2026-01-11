<?php
    class webuser{
        private $db;
        function __construct($conn){
            $this->db = $conn; 
            }
    

        public function insertUser($email,$password,$profilename,$alias,$lang_id,$picture_id,$reg_token_hash,$newsletter_subscribe){
            try {
                $result = $this->getUserbyemail($email);
                if ($result['num'] > 0){
                    return false;
                }else{
                    $new_password = md5($password.$email);
                    if($newsletter_subscribe == 1){
                        $newsletter_subs_timestamp = date("Y-m-d H:i:s", time());
                        $unsc_token=bin2hex(random_bytes(16));//creating unsubscribe hash
                        $unsubscribe_hash=hash("sha256", $unsc_token);
                    }else{
                        $newsletter_subs_timestamp = NULL;
                        $unsubscribe_hash = NULL;
                    }

                    $sql = "INSERT INTO `profile`(`email`, `password`, `profilename`, `alias`, `lang_id`, `picture_id`, `reg_token_hash`, `newsletter_subscribe`, `newsletter_subs_timestamp`, `newsletter_unsubscribe_hash`) VALUES (:email, :password, :profilename, :alias, :lang_id, :picture_id, :reg_token_hash, :newsletter_subscribe, :newsletter_subs_timestamp, :newsletter_unsubscribe_hash)"; 
                    $stmt = $this->db->prepare($sql);
                    
                    $stmt->bindparam(':email',$email);
                    $stmt->bindparam(':password',$new_password);
                    $stmt->bindparam(':profilename',$profilename);
                    $stmt->bindparam(':alias',$alias);
                    $stmt->bindparam(':lang_id',$lang_id);
                    $stmt->bindparam(':picture_id',$picture_id);
                    $stmt->bindparam(':reg_token_hash',$reg_token_hash);
                    $stmt->bindparam(':newsletter_subscribe',$newsletter_subscribe);
                    $stmt->bindparam(':newsletter_subs_timestamp',$newsletter_subs_timestamp);
                    $stmt->bindparam(':newsletter_unsubscribe_hash',$unsubscribe_hash);
                    $stmt->execute();
                    return true;
                }
            } catch (PDOException $e) {
                echo $e->getMessage();
                return false;
                }    
                   
            }

        public function getUserbyID($profile_id){
            try{
                $sql = "SELECT * FROM `profile` WHERE profile_id = :profile_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
            }catch(PDOException $e){
                echo $e->getMessage();
                return false;
            }
        }    

        public function getUser($email,$password){
            try{
                $sql = "SELECT * FROM `profile` WHERE UPPER(email) = UPPER(:email) AND password = :password";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':email',$email);
                $stmt->bindparam(':password',$password);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        } 
        
        public function getUserbyemail($email){
            try{
                $sql = "SELECT count(*) as num FROM `profile` WHERE email = :email";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':email',$email);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function getUserdetailsbyemail($email){
            try{
                $sql = "SELECT * FROM `profile` WHERE UPPER(email) = UPPER(:email)";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':email',$email);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function updateProfilename($profile_id,$profilename){
            try {
                $sql = "UPDATE `profile` SET `profilename`= :profilename WHERE `profile_id` = :profile_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':profilename',$profilename);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }


        public function updateAlias($profile_id,$alias){
            try {
                $sql = "UPDATE `profile` SET `alias`= :alias WHERE `profile_id` = :profile_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':alias',$alias);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function updateProfilepic($profile_id,$picture_id){
            try {
                $sql = "UPDATE `profile` SET `picture_id`= :picture_id WHERE `profile_id` = :profile_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':picture_id',$picture_id);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }


        public function updatePassword($profile_id,$password_hash){
            try {
                $sql = "UPDATE `profile` SET `password`= :password_hash, `reset_token_hash`= NULL, `reset_token_expire`= NULL WHERE `profile_id` = :profile_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':password_hash',$password_hash);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function enterNewPassword($profile_id,$email,$password){
            try {
                $new_password = md5($password.$email);
                $sql = "UPDATE `profile` SET `password`= :password WHERE `profile_id` = :profile_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':password',$new_password);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function updateLanguage($profile_id,$lang_id){
            try {
                $sql = "UPDATE `profile` SET `lang_id`= :lang_id WHERE `profile_id` = :profile_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':lang_id',$lang_id);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function updateNewsletterSub($profile_id,$newsletter_subscribe){
            $result = $this->getUserbyID($profile_id);
            if($result['newsletter_subscribe'] == $newsletter_subscribe){
                return true; // No change needed
            }
            if($newsletter_subscribe == 1){
                $newsletter_subs_timestamp = date("Y-m-d H:i:s", time());
                $unsc_token=bin2hex(random_bytes(16));//creating unsubscribe hash
                $unsubscribe_hash=hash("sha256", $unsc_token);
            }else{
                $newsletter_subs_timestamp = NULL;
                $unsubscribe_hash = NULL;
            }
            try {
                $sql = "UPDATE `profile` SET `newsletter_subscribe`= :newsletter_subscribe, `newsletter_subs_timestamp`= :newsletter_subs_timestamp, `newsletter_unsubscribe_hash`= :newsletter_unsubscribe_hash WHERE `profile_id` = :profile_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':profile_id',$profile_id);
                $stmt->bindparam(':newsletter_subscribe',$newsletter_subscribe);
                $stmt->bindparam(':newsletter_subs_timestamp',$newsletter_subs_timestamp);
                $stmt->bindparam(':newsletter_unsubscribe_hash',$unsubscribe_hash);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function findUserByUnsubscribeToken($newsletter_unsubscribe_hash){
            try{
                $sql = "SELECT * FROM `profile` WHERE newsletter_unsubscribe_hash = :newsletter_unsubscribe_hash";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':newsletter_unsubscribe_hash',$newsletter_unsubscribe_hash);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function updateToken($email,$reset_token_hash,$reset_token_expire){
            try {
                $sql = "UPDATE `profile` SET `reset_token_hash`= :reset_token_hash, `reset_token_expire`= :reset_token_expire WHERE UPPER(email) = UPPER(:email)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':reset_token_hash',$reset_token_hash);
                $stmt->bindparam(':reset_token_expire',$reset_token_expire);
                $stmt->bindparam(':email',$email);
                $stmt->execute();
                return true;
            
            }catch (PDOException $e) {
                echo $e->getMessage();
                return false;
            }
        }

        public function findUserByToken($reset_token_hash){
            try{
                $sql = "SELECT * FROM `profile` WHERE reset_token_hash = :reset_token_hash";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':reset_token_hash',$reset_token_hash);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function findRegistrationToken($reg_token_hash){
            try{
                $sql = "SELECT * FROM `profile` WHERE reg_token_hash = :reg_token_hash";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':reg_token_hash',$reg_token_hash);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        public function deleteRegistrationToken($email){
            try{
                $sql = "UPDATE `profile` SET `reg_token_hash`= NULL WHERE email = :email";
                $stmt = $this->db->prepare($sql);
                $stmt->bindparam(':email',$email);
                $stmt->execute();
                $result = $stmt->fetch(); 
                return $result;
                }catch(PDOException $e){
                    echo $e->getMessage();
                    return false;
                }
        }

        

    }
?>