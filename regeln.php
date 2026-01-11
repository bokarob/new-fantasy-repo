<?php 
$title = "Szabályok";
require_once 'includes/header.php';
require_once 'db/conn.php';

if($_SESSION['lang']==1){ 
    echo '<script type="text/javascript">location.href="szabalyok.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=szabalyok.php" /></noscript> ';
}elseif($_SESSION['lang']==2){ 
    echo '<script type="text/javascript">location.href="rules.php";</script>
    <noscript><meta http-equiv="refresh" content="0; URL=rules.php" /></noscript> ';
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

<h2 id="title">Regeln</h2>



<div class="accordion" id="rules">
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse0" aria-expanded="false" aria-controls="collapse0">
        Was ist dieses Spiel?
    </button>
    </h2>
    <div id="collapse0" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Dies ist ein Fantasy-Sportspiel, bei dem es darum geht, ein Team von Keglern aus der ungarischen oder deutschen Kegelliga zusammenzustellen. Diese Spieler sammeln wöchentlich Fantasiepunkte für dein Team basierend auf ihrer Leistung auf der Bahn. Du hast ein festes Budget, um Spieler für dein Team zu verpflichten. Außerdem hast du jede Woche die Möglichkeit, Spieler in deiner Aufstellung zu wechseln. Das Ziel ist es, so viele Punkte wie möglich zu erreichen, indem du die Form deiner Spieler vorhersagst, und die Fantasy 9pin Liga zu gewinnen!</p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
    Teamerstellung
    </button>
    </h2>
    <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Jedes Fantasy 9pin Team besteht aus 8 Personen – nicht mehr und nicht weniger. Du hast ein Budget von 80 Millionen zur Verfügung, um Spieler für dein Team zu verpflichten. Natürlich musst du nicht die gesamte Summe ausgeben. Der Betrag, den du nicht ausgibst, bleibt in deiner Tasche und kann später für Spielertransfers verwendet werden.</br></br>
        Jeder Spieler hat einen Startpreis (wie viel es kostet, ihn in dein Team zu holen), der auf seinen Kegeldurchschnitten in den vorherigen Saisons, der Anzahl der gespielten Spiele, der erwarteten Anzahl der Spiele usw. basiert. Dieser Preis ändert sich im Laufe der Saison basierend auf der Leistung der Spieler und darauf, wie die Fantasy-Trainer sie handeln.</br></br>
        Ein weiterer wichtiger Aspekt bei der Teamerstellung ist, dass du zu jedem Zeitpunkt maximal 2 Spieler von jedem Szuperliga/Bundesliga-Team in deinem Fantasy-Team haben kannst.
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
    Liga-Management
    </button>
    </h2>
    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Die größte Veränderung zur letzten Saison ist, dass in diesem Jahr auch die Frauen-Bundesliga zur Fantasy 9pin Welt dazugekommen ist. In der Praxis bedeutet das, dass du mit einer Registrierung nun drei Teams verwalten kannst. Du kannst keine Spielerinnen und Spieler aus den drei Ligen in ein einziges Fantasy-Team mischen – die Wettbewerbe sind getrennt.
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
      Team-Management
      </button>
    </h2>
    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <strong>Auswahl deines Startteams</strong></br>
		<p>Von den acht Spielern in deinem Kader musst du für jede Spielwoche 6 Startspieler und 2 Ersatzspieler auswählen. Die Punkte deines Fantasy-Teams bestehen hauptsächlich aus den Punkten, die deine Startspieler verdienen. Das Ergebnis der Ersatzspieler zählt nur, wenn einer oder mehrere deiner Startspieler am Wochenende nicht gespielt haben.</p></br>
		<strong>Auswahl des Kapitäns</strong></br>
		<p>Wähle für jede Spielwoche den Kapitän deines Teams. Die Punkte deines Kapitäns werden bei der Berechnung des Teamergebnisses verdoppelt. Der Kapitän kann nur ein Startspieler sein. Wenn du einen deiner Ersatzspieler zum Kapitän machen möchtest, musst du ihn zuerst in die Startaufstellung verschieben.</br></br>
        Wen du als Kapitän nominierst, hat große Bedeutung, denn wenn dieser Spieler in dieser Woche nicht spielt, werden die doppelten Punkte nicht automatisch einem anderen deiner Spieler zugewiesen – was effektiv dazu führt, dass du in dieser Woche doppelte Punkte verlierst. Daher lohnt es sich, jede Spielwoche zu überprüfen, wo deine Spieler am Wochenende spielen werden, und dein Team entsprechend zu verwalten.
    </p></br>
		<strong>Automatische Auswechslung</strong></br>
		<p>Die automatische Auswechslung gibt dir die Chance, keine Punkte zu verlieren, auch wenn jemand aus deinem Startteam in der Spielwoche nicht gespielt hat. Wenn einer oder mehrere deiner Startspieler keine Punkte erzielt haben, werden die Punkte deiner Ersatzspieler zu deinen wöchentlichen Punkten hinzugefügt.</br></br>
        Achte auf die Reihenfolge deiner Ersatzspieler: Zuerst werden die Punkte des 7. Spielers und dann die des 8. Spielers berücksichtigt (falls erforderlich). Wenn auch sie keine Punkte erzielen, berechnen wir die Punkte deines Teams basierend auf der Anzahl der Spieler, die gespielt haben.</br></br>
        Du musst keine Änderungen für die Spielwoche vornehmen, wenn du es nicht für notwendig hältst. Wenn du dein Team unverändert lässt, bleibt es in seiner aktuellen Form für die nächste Spielwoche gültig. Aber wenn du an der Spitze der Liga stehen willst, solltest du dir Gedanken machen.
    </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
      Spielertransfers
      </button>
    </h2>
    <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Nach der Erstellung eines Benutzerkontos und der Auswahl deines Startteams kannst du unbegrenzt viele Spielertransfers vornehmen – bis zum ersten Spielwochenende, für das dein Team gültig ist.</br></br>
        Nachdem du deine ersten Fantasiepunkte erzielt hast, hast du pro Woche 2 Transfermöglichkeiten. Aber sei dir bewusst: Die Preise der Spieler ändern sich: Spieler, die bei den Fantasy-Trainern beliebter sind, werden teurer, Spieler, die öfter verkauft werden, werden billiger. Das Gleiche gilt hier wie bei der Teamerstellung: Wenn du nicht dein ganzes Geld ausgibst, bleibt es in deiner Tasche und kann später verwendet werden.</br></br>
        Nicht alles am Handel ist so flexibel. Du kannst keine Transfermöglichkeiten ansammeln. Selbst wenn du eine Woche lang keinen Transfer durchführst, hast du nächste Woche nicht 4 freie Transferplätze, sondern nur 2 wie alle anderen.</br></br>
        Ein weiterer wichtiger Punkt ist, dass Transfers nicht rückgängig gemacht werden können. Wenn du einen Transfer bereust und noch eine Transfermöglichkeit übrig hast, kannst du den Spieler zurücktauschen. Wenn du keine mehr hast, musst du bis zur nächsten Woche warten.</br></br>

        Was passiert, wenn einer meiner Spieler verletzt wird oder aus anderen Gründen längere Zeit fehlt (aufgrund eines bekannten Grundes)?</br>
        Diese Fälle sind, so bedauerlich sie auch sind, Teil einer Sportsaison. Du kannst den Spieler mit einer deiner 2 Transfermöglichkeiten tauschen, aber es gibt keinen speziellen Transfer für solche Ereignisse.
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
        <p>Das Spiel folgt dem Zeitplan der ungarischen und deutschen Kegelliga. Jede Spielwoche hast du bis zur Deadline Zeit, Transfers vorzunehmen oder dein Team zu verwalten (Startteam ändern, Kapitän bestimmen).</p></br>
		<p>Die genaue Deadline kann variieren, aber im Allgemeinen ist es <strong>der Freitag vor den Spieltagen</strong> der Spielwoche. Wenn du diese Deadline verpasst, trittst du mit der gleichen Aufstellung wie in der Vorwoche an.</p></br>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
      Punktesystem
      </button>
    </h2>
    <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Die Spieler deines Fantasy 9pin-Teams verdienen Punkte basierend auf ihren Leistungen am Wochenende nach folgendem Punktesystem:</p></br>
		<ul>
			<li>Jeder Kegel: 0,1 Punkte</li>
			<li>Jeder gewonnene Satzpunkt: 5 Punkte</li>
			<li>Jeder gewonnene Mannschaftspunkt: 15 Punkte</li>
		</ul></br>
    <p>Was passiert im Falle einer Auswechslung? Wer verdient in diesem Fall Fantasiepunkte?</br>
    Jeder Spieler verdient Punkte basierend auf den Sätzen, die er gespielt hat. Für diese Sätze berechnen wir die Fantasiepunkte nach dem oben genannten Punktesystem. Wenn die Spieler zusammen den Teampunkt gewinnen, erhalten sie jeweils 7,5 Fantasiepunkte dafür, unabhängig davon, wann die Auswechslung stattgefunden hat.</br></br>

    Was passiert, wenn ein Spiel verschoben wird?</br>
    Die Ergebnisse der verschobenen Spiele werden später, nach Abschluss, berechnet und die Ergebnisse werden deinem Team hinzugefügt, wenn einer deiner Spieler betroffen war. Es kann also vorkommen, dass du vorübergehend weniger Punkte hast aufgrund des verschobenen Spiels, aber nachdem es gespielt wurde, hast du die gleichen Gesamtpunkte, als ob alle Spiele zur regulären Zeit gespielt worden wären.
    </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
      Verschiedene Regeln
      </button>
    </h2>
    <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Ein paar allgemeine Regeln für das Spiel, quasi die Geschäftsbedingungen:</p></br>
        <p>Das Spiel kann mit einem gültigen Benutzerkonto gespielt werden. Die Anmeldung und Teilnahme am Spiel ist kostenlos, es können keine zusätzlichen Dienste oder Vorteile auf der Seite gekauft werden.</p>
        <p>Die meisten Daten des Benutzerprofils sind privat und werden anderen Spielern oder Dritten nicht angezeigt oder anderweitig zur Verfügung gestellt. Eine Ausnahme bildet der Name des Fantasy 9pin-Teams und der Traineralias. Da diese für andere Spieler sichtbar sind (z.B. in der Liga-Tabelle), bitten wir dich, auf Schimpfwörter oder beleidigende Ausdrücke zu verzichten. Wenn wir solche finden, werden wir die jeweilige Benennung beim ersten Mal ändern, beim nächsten Mal wird das Benutzerkonto gesperrt.</p>
        <p>Du kannst dein Benutzerprofil jederzeit im Menü „Einstellungen“ über die Schaltfläche „Profil löschen“ löschen. In diesem Fall werden alle Daten und erzielten Punkte gelöscht und können später nicht wiederhergestellt werden.</p>
        <p>Bei Streitigkeiten oder Missverständnissen bezüglich der Spielregeln entscheiden die Entwickler über die Angelegenheit. Obwohl wir unser Bestes getan haben, um klare Regeln für alle Möglichkeiten zu erstellen, kann es vorkommen, dass ein Szenario fehlt (oder zumindest hier nicht klar beschrieben ist). Wenn du ein solches Szenario findest oder Fragen zu den Regeln hast, kontaktiere uns bitte unter: <strong>info@fantasy9pin.com</strong> </p>
        <p>Wir sind bemüht, genaue und aktuelle Daten im Spiel anzuzeigen, können jedoch nicht garantieren, dass keine Fehler auftreten. Wenn du falsche Daten findest, kontaktiere uns bitte und wir werden diese korrigieren.</p>
        <p>Die Ergebnisse und verdienten Fantasiepunkte der Spieler und Fantasy-Teams werden aktualisiert, nachdem alle Spielergebnisse auf den offiziellen Ergebnisseiten der jeweiligen Ligen verfügbar sind. Dies bedeutet in der Regel, dass die Punkte sonntags, spätestens am Montag nach dem Spieltag, aktualisiert werden.</p>
        <p>Du kannst uns unter info@fantasy9pin.com kontaktieren. Fragen, Anregungen zur Website oder zum Spiel sowie Fehler oder falsche Daten kannst du uns hier mitteilen.</p>

        
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