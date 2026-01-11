</div>
    <script src="/includes/autocollapse.js"> </script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>


<style>
    footer {
  background-color: #222; /* Dark background */
  color: white;
  padding-top: 40px;
  text-align: center;
  font-family: Arial, sans-serif;
  position: relative;
}

.footer-container {
  margin: 0 auto;
}

/* Social Media Icons */
.footer-social-icons {
  margin-bottom: 20px;
}

.circle-icon {
  background-color: #4CAF50;
  color: white;
  display: inline-block;
  margin: 0 10px;
  width: 40px;
  height: 40px;
  line-height: 40px;
  border-radius: 50%;
  text-align: center;
  font-size: 0; 
}

.circle-icon img{
  width: 20px;
}

.circle-icon:hover {
  background-color: #285d2a;
}

/* Main Navigation Links */
.footer-nav {
  margin: 30px 0;
}

.footer-nav a {
  color: white;
  margin: 0 15px;
  text-decoration: none;
  font-weight: bold;
}

.footer-nav a:hover {
  color: #4CAF50;
}

/* Secondary Links */
.footer-secondary-links {
  margin: 20px 0;
}

.footer-secondary-links a {
  color: #aaa;
  text-decoration: none;
  margin: 0 10px;
  font-size: 14px;
}

.footer-secondary-links span {
  color: #aaa;
}

.footer-secondary-links a:hover {
  color: #4CAF50;
}

/* Bottom Bar */
.footer-bottom {
  /* background-color: #4CAF50; */
  background-color: #1FB7D0; 
  padding: 10px 0;
  color: white;
  font-size: 14px;
  margin-top: 40px;
}

.footer-bottom p {
  margin: 5px 0;
}

/* Back to Top Button */
.back-to-top {
  position: absolute;
  right: 20px;
  top: 20px;
}

.back-to-top a {
  /* background-color: #4CAF50; */
  color: white;
  border: 1px solid white;
  padding: 5px;
  /* border-radius: 50%; */
  display: inline-block;
}

.back-to-top img{
  width: 35px;
}

.back-to-top a:hover {
  background-color: #333;
}

.back-to-top a i {
  font-size: 18px;
}

/* Responsive Styles */
@media (max-width: 768px) {
  .footer-nav, .footer-secondary-links {
    display: block;
  }

  .footer-secondary-links a {
    display: block;
    margin: 10px 0;
  }

  .footer-secondary-links span{
    display: none;
  }
}
</style>    
</body>

<footer>
  <div class="footer-container">
    <!-- Social Media Icons -->
    <div class="footer-social-icons">
      <a href="https://www.facebook.com/fantasy9pin" target="_blank" class="circle-icon">
        <img src="img/icons8-facebook-30.png" alt="">
      </a>
      <a href="https://www.instagram.com/fantasy9pin/" target="_blank" class="circle-icon">
        <img src="img/icons8-instagram-50.png" alt="">
      </a>
      <a href="mailto:info@fantasy9pin.com" class="circle-icon">
        <img src="img/icons8-email-50.png" alt="">
      </a>
    </div>

    <!-- Main Navigation Links -->
    <div class="footer-nav">
      <a href="index.php">Home</a>
      <?php switch($_SESSION['lang']){
                    case 1:
                    ?>
                      <a href="szabalyok.php">Szabályok</a>
                    <?php
                    break;
                    case 2:
                    ?>
                      <a href="rules.php">Rules</a>
                    <?php
                    break;
                    case 3:
                    ?>
                      <a href="regeln.php">Regeln</a>
                    <?php
                    break;
                }?>
      <?php if(isset($_SESSION['profile_id'])){ ?>
      <a href="contact.php"><?php switch($_SESSION['lang']){
                    case 1: echo "Kontakt";
                    break;
                    case 2: echo "Contact";
                    break;
                    case 3: echo "Kontakt";
                    break;
                }}?></a>
    </div>

    <!-- Secondary Links with Dividers -->
    <div class="footer-secondary-links">
      <a href="https://eredmenykozlo.csaka9.hu/" target="_blank"><?php switch($_SESSION['lang']){
                    case 1: echo "MATESZ Eredményközlő";
                    break;
                    case 2: echo "MATESZ Results";
                    break;
                    case 3: echo "MATESZ Ergebnisdienst";
                    break;
                }?></a>
      <span>|</span>
      <a href="https://dkbc.sportwinner.de/" target="_blank"><?php switch($_SESSION['lang']){
                    case 1: echo "DKBC Eredményközlő";
                    break;
                    case 2: echo "DKBC Results";
                    break;
                    case 3: echo "DKBC Ergebnisdienst";
                    break;
                }?></a>
      <span>|</span>
      <a href="https://www.wnba-nbc.com/" target="_blank">WNBA-NBC</a>
      <?php if($_SESSION['lang']==3){ ?>
        <span>|</span>
        <a href="https://talentfreie-kegler.myforum.community/forum/index.php?thread/41-fantasy-9pin-in-der-bundesliga/" target="_blank">Talentfreies Kegelforum</a>
      <?php } ?>
    </div>

    <!-- Copyright & Bottom Bar -->
    <div class="footer-bottom">
      <p id="footerbottomtext">MADE WITH <span id="heart">❤️</span> FOR NINEPIN BOWLING ENTHUSIASTS</p>
      <p>&copy; <?php echo date('Y'); ?> Fantasy 9pin. All rights reserved.</p>
    </div>

    <!-- Back to Top Button -->
    <div class="back-to-top">
      <a href="#top">
        <img src="img/icons8-chevron-up-64.png" alt="">
      </a>
    </div>
  </div>
</footer>


<?php if(isset($_SESSION['profile_id'])){ ?>
  <script src="footerjs.js"></script>
<?php } ?>

</html>
