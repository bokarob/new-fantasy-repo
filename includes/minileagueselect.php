<style>
    .leagueselection{
        display: flex;
        margin-top: 0;
        align-items: center;
        justify-content: center;
        gap:0.5rem;
    }

    .league{
        width: 150px;
        border-radius: 15px;
        margin:1rem;
        margin-bottom: 2rem;
    }

    .card{
        padding-top: 0.3rem;
        padding-bottom: 0;
        border-radius: 10px;
    }

    .card-img-top{
        height: 30px;
        width: 30px;
        margin:auto;
    }
    .card-title{
        font-size: 12px;
        font-style: italic;
        margin-top: 0.1rem;
        margin-bottom: 0.3rem;
    }
    @media(max-width: 450px){
        .league{
            width:110px;
            margin:0rem;
        }
        .card-title{
            font-size: 10px;
        }
        .leagueselection{
            margin-top: 1rem;
            margin-bottom: 2rem;
        }
    }
    
</style>

<div class="leagueselection">
    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post"> 
      <button class="league" type="submit" name="league" value=10>
        <div class="card">
          <img class="card-img-top" src="img\matesz.png" alt="">
            <h5 class="card-title">Szuperliga</h5>
        </div>
      </button>
      <button class="league" type="submit" name="league" value=20>
        <div class="card">
          <img class="card-img-top" src="img\dkbc.png" alt="">
            <h5 class="card-title">Bundesliga Men</h5>
        </div>
      </button>
      <button class="league" type="submit" name="league" value=40>
        <div class="card">
          <img class="card-img-top" src="img\dkbc.png" alt="">
            <h5 class="card-title">Bundesliga Women</h5>
        </div>
      </button>
    </form> 
</div>