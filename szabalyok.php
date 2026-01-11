<?php 
$title = "Szabályok";
require_once 'includes/header.php';
require_once 'db/conn.php';

if($_SESSION['lang']==2){ 
  echo '<script type="text/javascript">location.href="rules.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=rules.php" /></noscript> ';
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

<h2 id="title">Szabályok</h2>



<div class="accordion" id="rules">
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse0" aria-expanded="false" aria-controls="collapse0">
      Mi ez a játék?
    </button>
    </h2>
    <div id="collapse0" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Ez egy fantasy sport játék, aminek az a lényege, hogy összeállítsd a saját csapatodat vagy a magyar vagy a német teke liga játékosaiból, akik aztán hétről hétre fantasy pontokat gyűjtenek neked. A játékosaid a hétvégi eredményeik alapján szereznek pontokat a csapatodnak. A csapatválasztásra egy megadott keret áll rendelkezésedre. Ha nem vagy elégedett a csapatod összeállításával, lehetőséged nyílik igazolni minden játékhéten. Ebből kell kihoznod a legjobbat, a játékosok formája és a csapatok sorsolása alapján, hogy minél több fantasy pontot szerezz és végül Te végezz a Fantasy 9pin Liga táblázatának élén!</p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
      Csapat választás
    </button>
    </h2>
    <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Minden Fantasy 9pin csapat 8 főből áll, ennyi játékost kell igazolnod, hogy összeállítsd a keretedet. A csapatválasztáskor 80M keret áll rendelkezésedre, hogy játékosokat szerződtess. Ezt természetesen nem muszáj teljes egészében felhasználnod. Az az összeg, amit nem költesz el, a zsebedben marad és bármelyik későbbi fordulóban használható lesz, ha igazolni szeretnél.</br></br>
        Az egyes játékosoknak eltérő kezdő árral indítják a szezont, ami több összetevő alapján határozódik meg, például előző szezonokban hozott átlaguk, hány meccsen játszottak, hány meccsen fognak játszani várhatóan. Az ár később a szezon alatt is változik a mutatott teljesítményük és az alapján, hogy hogyan “kereskednek” velük a fantasy edzők.</br></br>
        További megkötés a keret alakításhoz, hogy egy Szuperliga/Bundesliga csapatból egyszerre maximum 2 játékos lehet a csapatodban.
    
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
    Liga menedzsment
    </button>
    </h2>
    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>A legnagyobb változás az előző szezon óta, hogy a magyar és német férfi bajnokság mellett a német női Bundesliga is csatlakozik a fantasy teke világába. Ez azt jelenti, hogy innentől kezdve egy regisztrációval lehetséges három csapatot menedzselni. Vegyes csapatot nem lehet csinálni, a három bajnokság külön zajlik egymás mellett. 
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
        Csapat menedzsment
      </button>
    </h2>
    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <strong>Kezdő csapat kiválasztása</strong></br>
		<p>A keretedet adó nyolc játékosból minden fordulóra ki kell választanod 6 kezdőjátékost és 2 cserét. A Fantasy 9pin csapatod heti pontjait elsősorban a kezdőjátékosaid pontjai fogják adni. A cserejátékosok pontjai akkor számítanak, ha valamelyik kezdő játékosod nem lépett pályára a hétvégén.</p></br>
		<strong>Csapatkapitány választása</strong></br>
		<p>Válassz egy csapatkapitányt minden héten a kezdőcsapatodból. A csapatkapitányod pontjai duplán számítanak a csapateredménybe. Csapatkapitány csak kezdőjátékos lehet. Ha valamelyik tartalék játékosodat szeretnéd csapatkapitánynak nevezni, előbb a kezdőcsapatba kell írnod.</br></br>
    A csapatkapitány kijelölése különösen fontos, mert ha véletlenül nem játszik az adott fordulóban a kapitányod, a pontduplázás lehetősége nem adódik át másik játékosnak automatikusan – ezzel dupla pontot veszítenél. Ezért is érdemes minden héten ránézni a csapatodra és megnézni hol és ki ellen játszanak a játékosaid és a kezdőcsapatot, csapatkapitányt ennek megfelelően kijelölni.
    </p></br>
		<strong>Automatikus cserék</strong></br>
		<p>Az automatikus cserék lehetőséget biztosítanak számodra, hogy ha valamelyik játékosod nem játszik az adott fordulóban, akkor se ess el pontoktól. Ha egy vagy több kezdőjátékosod nem játszik, olyankor automatikusan a tartalék játékosok pontjait vesszük számításba.</br></br>
    Figyelj a cserejátékosok sorrendjére: először a 7. és utána, ha szükséges, a 8. játékos eredménye adódik hozzá a csapat pontjaihoz. Ha esetleg ők sem játszottak, akkor annyi játékos eredményéből adódik össze a csapatod heti pontjainak száma, amennyi pályára lépett.</br></br>
    Általánosságban megéri figyelembe venni a következő forduló mérkőzéseit, várható eredményeket. Ha elégedett vagy a csapatod felállásával, nem muszáj változtatnod. Ilyenkor az előző heti csapatod lesz érvényes a következő fordulóra is. De ha az élen akarsz lenni, jobb ha ezzel fekszel és ezzel kelsz.
    </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
        Átigazolások
      </button>
    </h2>
    <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Miután regisztráltál és kiválasztottad az induló csapatodat, korlátlanul eladhatsz és igazolhatsz játékost a játékospiacról az első fordulóig, amire a csapatodat nevezed.</br></br>
        Az első forduló után, amin a csapatod is részt vesz, heti 2 igazolást tehetsz. A játékosok árai folyamatosan változhatnak: annak a játékosnak, akit több fantasy edző igazol a csapatába, az ára felfelé mozog, akit többen adnak el az adott fordulóban, annak az ára lefelé. Itt is igaz az, ami az induló csapatválasztánál: ha marad a zsebedben pénz (mondjuk mert olcsóbb játékost igazolsz drágább helyett), az nem veszik el, a későbbiekben bármikor felhasználhatod.</br></br>
        Ami viszont nem ennyire rugalmas, az az átigazolási kérelmek száma. Nem kötelező igazolnod a fordulók közt, de fel nem használt átigazolási kérelmek nem vihetőek át a következő játékhétre. Tehát ha az egyik fordulóban nem igazolsz, a következő héten nem lesz 4 lehetőséged, csak ugyanúgy 2, mint másoknak.</br></br>
        Másik fontos tudnivaló, hogy az átigazolási kérelmeket nem tudod visszavonni. Ha van még szabad kérelmed, vissza tudod vásárolni az eladott játékost. Ha nem, akkor a következő fordulóig várnod kell.</br></br>

        Ha valamelyik játékosom megsérül vagy köztudottan hosszabb ideig távol marad a játéktól, akkor őt szabadon eligazolhatom?</br>
        Ezek a történések a verseny részei, az adott játékost a heti 2 átigazolási kérelem egyikével el tudod adni, de nem jár extra kérelem ilyen esetekre.
        </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
        Határidők
      </button>
    </h2>
    <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>A játék a Szuperliga és Bundesliga fordulókat követi. Minden forduló határideje előtt van időd új játékosokat igazolni vagy a csapatodat menedzselni (csapatkapitány választás, kezdőcsapat kiválasztása).</p></br>
		<p>Az egyes fordulók határideje változhat, de általánosan a <strong>bajnoki mérkőzések előtti péntek</strong>. Ha ebből kicsúszol, akkor az előző heti kerettel mész neki a fordulónak. Nem tragédia, több szuperligás csapatnál is gyakorlatilag ez az alap koncepció.</p></br>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
        Pontrendszer
      </button>
    </h2>
    <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>A szezon közben Fantasy 9pin játékosaid az aktuális bajnoki fordulóban elért eredményeik alapján szereznek fantasy pontokat az alábbiak szerint:</p></br>
		<ul>
			<li>Minden ütött fa: 0,1 pont</li>
			<li>Szerzett szettpont: 5 pont</li>
			<li>Szerzett csapatpont: 15 pont</li>
		</ul></br>
    <p>Mi történik, ha az egyik játékosomat le kell cserélni? Számít az eredménye?</br>
    Minden játékos annyi szett alapján szerez pontot, amennyit ő teljesített. Az adott szettekre a fenti pontrendszer alapján számoljuk ki a fantasy pontokat. Ha a cseréjével együtt megszerzik a csapatpontot, azt fele-fele arányban osztják el függetlenül attól, hogy mikor történt a csere.</br></br>

    Mi történik ha elmarad egy mérkőzés a fordulóban és valamelyik játékosom érintett lett volna?</br>
    Az elmaradt mérkőzés eredményeit később, a meccs lejátszásakor írjuk jóvá a játékosoknak és az érintett csapatoknak. Így átmenetileg lehet kevesebb pontod elmaradt mérkőzés miatt, de miután a csapatok pótolták a találkozót, ezek jóváíródnak a csapatodnak. A jóváíráskor újraszámoljuk a csapatod adott fordulóbeli pontjait, mintha a forduló minden meccsét a kiírt időben játszották volna le.
    </p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
        Vegyes szabályok
      </button>
    </h2>
    <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        <p>Néhány vegyes szabály a játékhoz, afféle felhasználási feltételek:</p></br>
        <p>A játék élő regisztrációval vehető igénybe. A játékra való regisztráció és a játékban való részvétel díjmentes, semmilyen szolgáltatás vagy előny nem vásárolható az oldalon.</p>
        <p>A felhasználói fiók legtöbb adata nem látható másik játékosok által. Kivétel ez alól a Fantasy 9pin csapatnév és a beállított edzői név. Mivel ezek az adatok láthatóak más játékosok által is (a verseny ranglistájában), így kérünk tartózkodj a trágár illetve bárki más számára sértő elnevezésektől. Ha ilyen tartalommal találkozunk, első alkalommal megváltoztatjuk az adott elnevezést, következő alkalommal pedig a felhasználói fiók felfüggesztésre kerül.</p>
        <p>Felhasználói fiókodat bármikor törölheted a „Beállítások” menüben, „Felhasználói fiók törlése” pont alatt. Ebben az esetben minden korábbi adat és elért pont törlődik, később vissza nem állítható.</p>
        <p>Ha a játék szabályaival kapcsolatban valamilyen vitára kerülne sor vagy bármilyen félreértés adódna, a kérdésben a fejlesztők hozzák meg az érvényes döntést. Bár igyekeztünk a legtöbb eshetőségre szabályt alkotni, előfordulhat, hogy valami kimaradt vagy nem teljesen világos. Ha a játékszabályokban leírtakon kívül más részletre lennél kíváncsi vagy olyan dolog jut eszedbe, ami nem szerepel a leírásban, a Kapcsolat pontban található elérhetőségen vedd fel velünk a kapcsolatot.</p>
        <p>Törekszünk a játékban található adatok pontos megjelenítésére és frissen tartására, ennek ellenére nem tudjuk garantálni, hogy minden adat minden időben pontos, teljes és friss legyen. Ha hibás adatot találsz valahol, írj nekünk és javítjuk.</p>
        <p>A játékosok és a Fantasy 9pin csapatok pontjai az adott Szuperliga forduló végeztével frissülnek, mikor az Eredményközlőn elérhetőek a csapatok eredményei. Ez legtöbbször vasárnap, legkésőbb a forduló utáni hétfő.</p>
        <p>A játék és weboldal fejlesztőivel az info@fantasy9pin.com email címre írva vagy a weboldal Kapcsolat menüjén keresztül tudsz kapcsolatba lépni. Ide érkezhetnek a játékkal vagy weboldallal kapcsolatos fejlesztési, javítási ötletek, esetleges hibák, amiket felfedeztek, de általános kérdések, észrevételek is.</p>

        
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