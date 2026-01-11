<?php
    // Set the target date (1st September)
    $targetDate = strtotime("2025-09-01");
    // Get the current date
    $currentDate = time();
    // Calculate the difference in days
    $daysUntilLaunch = ceil(($targetDate - $currentDate) / 86400);
?>

<style>
    .startpage{
        margin: auto;
        margin-top: 2rem;
        text-align: center;
        
    }
    .container {
        max-width: 50%;
        margin: auto;
        position: relative;
        display: inline-block;
        text-align: center;
        overflow: hidden;
        border-radius: 30px;

    }

    /* .container::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: url('img/season_3_coming_soon.jpg'); /* Replace with the blurred image path */
        /* background-size: cover; Ensure it covers the container */
        /* background-position: center; Center the background */
        /* filter: blur(20px); Apply the blur only to the background */
        /* z-index: -1; Ensure it stays behind the main image 
    } */

    .startpage h1 {
        font-size: 3rem;
        margin-bottom: 2rem;
        color: #333;
        font-family: 'Helvetica Neue', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        margin-bottom: 20px;

        /* Applying a subtle gradient to the text */
        background: linear-gradient(to right, #333, #888);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-style: italic;
    }
    h1 span {
        font-size: 3.5rem;
        color: #333; /* Dark color for the main number */
        display: inline-block;
    }
    

    .comingsoonimg {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 0 auto;
        /* border: 5px solid #333; */
        border-radius: 20px;
        
        position: relative;
        z-index: 1;
        padding: 10px;
        /* mask-image: radial-gradient(circle, rgb(0, 0, 0) 50%, rgba(0,0,0,0) 80%); */
        
    }
    

@media (max-width: 768px) {
    .startpage h1 {
        font-size: 2rem;
    }
    h1 span{
        font-size: 2.5rem;
    }
    .container{
        max-width: 90%;
        border-radius: 10px;
    }
}
</style>
<div class="startpage">
    <?php 
    if(isset($_SESSION['lang'])){
        switch($_SESSION['lang']){
            case 1: ?>
                <!-- <h1><span><?php echo $daysUntilLaunch; ?></span> nap az indulásig</h1> -->
            <?php    ;
            break;
            case 2: ?>
                <!-- <h1><span><?php echo $daysUntilLaunch; ?></span> day<?php if ($daysUntilLaunch != 1) echo 's'; ?> until start</h1> -->
            <?php    ;
            break;
            case 3: ?>
                <!-- <h1><span><?php echo $daysUntilLaunch; ?></span> Tag<?php if ($daysUntilLaunch != 1) echo 'e'; ?> bis zum Start</h1> -->
            <?php    ;
            break;
        }
    }else{
    ?>
    <!-- <h1><span><?php echo $daysUntilLaunch; ?></span> day<?php if ($daysUntilLaunch != 1) echo 's'; ?> until start</h1> -->
    <?php } ?>
    <div class="container">
        <img class="comingsoonimg" src="img/season_3_coming_soon.jpg" alt="Coming Soon">
    </div> 
</div>
