<?php 
$title = "Szabályok";
require_once 'includes/header.php';
require_once 'db/conn.php';

if($_SESSION['lang']==1){ 
  echo '<script type="text/javascript">location.href="szabalyok.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=szabalyok.php" /></noscript> ';
}elseif($_SESSION['lang']==3){ 
  echo '<script type="text/javascript">location.href="regeln.php";</script>
  <noscript><meta http-equiv="refresh" content="0; URL=regeln.php" /></noscript> ';
}

?>
<style>

@media (min-width: 900px){
  #rules{
    max-width: 70%;
    margin: auto;
  }
}
  #title{
    margin-left:5vw; 
    margin-top: 4vh; 
    margin-bottom: 5vh;
    text-align: center;
  }
.accordion-button {
    /*font-family: 'Arial, sans-serif';*/
    font-size: 1.1rem;
    font-weight: 500;
    background-color: #f8f9fa;
    color: #333;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.accordion-button:not(.collapsed) {
    background-color: #e2e6ea;
    color: #000;
}

.accordion-button:hover {
    background-color: #e9ecef;
    color: #000;
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
    outline: none;
}

.accordion-item {
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    margin-bottom: 1rem;
}

.accordion-collapse {
    transition: height 0.3s ease;
}

.accordion-body {
    padding: 1rem;
    background-color: #ffffff;
    color: #333;
    line-height: 1.5;
}

</style>

<h2 id="title">Rules</h2>



<div class="accordion" id="rules">
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse0" aria-expanded="false" aria-controls="collapse0">
      What is this game?
    </button>
    </h2>
    <div id="collapse0" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>This is a fantasy sports game, which is about assembling a team ninepine bowling players from either the Hungarian or the German ninepin bowling league. These players will collect fantasy points for your team week-by-week based on their performance on the alley. You have a fix budget to sign players to your team. Also if you want to do changes in your line-up, you have a chance to trade players every week. The aim is to achieve as many points as you can by anticipating the form of your players and to win the Fantasy 9pin League!</p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
      Team creation
    </button>
    </h2>
    <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Every Fantasy 9pin team consists of 8 people – no more no less. You have a budget of 80M at your disposal to sign players to your team. Of course you don’t need to use the whole sum. The amount you don’t spend will remain in your pocket and can be used later for trading players.</br></br>
        Every player has a starting price (how much they will cost you to sign them in your team), which is calculated based on their pin averages in previous seasons, how many games they played, how many they are expected to play, etc. This price is moving during the season based on their performance and based on how the fantasy trainers are trading them.</br></br>
        Another important aspect when creating a team is that you can have maximum 2 players from each Szuperliga/Bundesliga team at any given time in your fantasy team.
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
      League management
    </button>
    </h2>
    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>The biggest change from last season is that the Women's Bundesliga is joining the world of fantasy ninepin. In practice it means that with one registration you can manage three teams. You cannot mix players from the three leagues into one fantasy team, the competitions are separated.
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
        Team management
      </button>
    </h2>
    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <strong>Selecting your starting team</strong></br>
		<p>Out of the eight players in your roster you need to select 6 starting players and 2 reserves for each gameweek. The points of your fantasy team will mainly consist of the points earned by your starting players. The result of the substitutes matter only in case one or more of your starting players did not compete over the weekend.</p></br>
		<strong>Selecting Captain</strong></br>
		<p>Select the captain of your team for each gameweek. The points of your captain are doubled when counting the team result. Captain can only be a starting player. If you want one of your substitutes to be team captain, first you need to move him to the starting team.</br></br>
    Who you nominate team captain has high importance, because in case that player doesn’t compete that week, the chance to earn double points will not be given to any of your other players automatically – effectively causing you to lose double points that week. Therefore it is worth to check every gameweek where your players will play over the weekend and manage your team accordingly.
    </p></br>
		<strong>Automatic substitution</strong></br>
		<p>Automatic substitution gives you a chance of not losing points even if someone from your starting team did not play in the gameweek. If one or more of your starters didn’t earn any points, the points of your reserves will be added to your weekly points.</br></br>
    Pay attention to the sequence of your reserve players: first the 7th player’s, then the 8th player’s points are considered (in case needed). If they happen to not earn any points either, we calculate your team’s points based on the number of players who did compete.</br></br>
    You don’t have to make changes for the gameweek if you don’t find it necessary. If you keep your team unchanged, it will be valid for the next gameweek in its current form. But if you want to be at the top of the league, you better put your thinking cap on.
    </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
        Player transfers
      </button>
    </h2>
    <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>After creating a user account and selecting your initial team you can make unlimited number of player transfers – until the first game weekend, for which your team is valid.</br></br>
        After you earn your first fantasy points you will have 2 transfer possibilities per week. But be aware: the prices of the players are changing: players who are more popular among fantasy trainers will have increasing prices, players who are more sold on the market will decrease in price. The same is true here as for the initial team creation: if you don’t spend all your money, it will remain in your pocket and can be used later.</br></br>
        Not everything about trading is so flexible though. You cannot accumulate trading possibilities. So even if you don’t do any trade one week, you will not have 4 empty transfer sheets to use next week, only 2 as everyone else.</br></br>
        Another important thing to know is that transfers cannot be undone. If you regret a trade and you still have one transfer possibility left, you can swap the player back. If don’t have any more, you have to wait till next week.</br></br>

        What happens if one of my players gets injured or otherwise missing games for a longer time (due to some known reason)?</br>
        These cases, however unfortunate, are part of a sports season. You can trade the player with one of the 2 transfer possibilities you have, but there isn’t any special transfer granted for such events.
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
        Deadlines
      </button>
    </h2>
    <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>The game is following the timeline of the Hungarian and German 9pin league. Every gameweek you have time before the deadline to make transfers or manage your team (change starting team, appoint team captain).</p></br>
		<p>The exact deadline can change, but generally it is the <strong>Friday before the match days</strong> of the gameweek. If you miss this deadline, you will compete with the same line-up as the previous week.</p></br>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
        Scoring system
      </button>
    </h2>
    <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>The players of your Fantasy 9pin team will earn you points based on their performances over the weekend, according to the below pointing system:</p></br>
		<ul>
			<li>Every pin: 0,1 points</li>
			<li>Every set point earned: 5 points</li>
			<li>Every team point earned: 15 points</li>
		</ul></br>
    <p>What happens in case of substitution? Who will earn fantasy points in that case?</br>
    Every player earns points based on those sets which he performed. For these sets we are calculating fantasy points based on the scoring system above. If the players together earn the team point, they get 7,5-7,5 fantasy points for it, regardless when the substitution took place.</br></br>

    What happens if a game is postponed?</br>
    Results of the postponed games are calculated later, after it is finished, and the results will be added to your team if any of your players were concerned. This way it can happen that temporarily you have less points due to the postponed game, but after it is played, you will have the same total points as if all games were played in their due times.
    </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
        Misc. rules
      </button>
    </h2>
    <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>A few miscellaneous rules for the game, kind of a terms&conditions:</p></br>
        <p>The game can be played with a valid user account. Signing up and participating in the game is free of charge, no extra service or advantage can be purchased on the site.</p>
        <p>Most data of the user profile is private and is not shown or otherwise shared with any other player or 3rd party. Exception is the Fantasy 9pin team name and trainer alias. Since these are visible to other players (e.g. in the standings of the league) we are asking you to avoid any swear words or offensive expressions. If we find such, we will change the given naming for the first time, the next time the user account will be suspended.</p>
        <p>You can delete your user profile at any time in the "Settings" menu by using the "Delete profile" button. In this case all data and achieved points are deleted, we cannot restore this later.</p>
        <p>In case of any dispute or misunderstanding regarding the rules of the game, the developers are deciding on the matter in question. Even though we were doing our best to create clear rules for all possibilities, it can happen that some scenario is missing (or at least not written here clearly). If you find such, or have question regarding some detail of the rules, please contact us at: <strong>info@fantasy9pin.com</strong> address.</p>
        <p>We are aiming to display accurate and up-to-date data in the game, however we cannot guarantee that there are no mistakes. If you happen to find any incorrect data, please contact us and we will fix it.</p>
        <p>Results and fantasy points earned by the players and fantasy teams are updated after all match result are available on the official results pages of the respected leagues. This usually means that the points are updated on Sundays, latest the Monday following the match day.</p>
        <p>You can contact us at the info@fantasy9pin.com address. Any question, suggestion regarding the website or the game can be sent here, same as mistakes or incorrect data if you find.</p>

        
      </div>
    </div>
  </div>
</div>
</div>



<br>
<br>
<br>
<br>
<?php require_once 'includes/footer_new2.php'; ?>