</div>
    <script src="/includes/autocollapse.js"> </script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>

    <style>
        .footerbody {
            margin: 0;
            padding: 0;
            height: 100%;
            display: flex;
            align-items: flex-end;
        }

        footer {
            position: relative;
            width: 100%;
            height: 200px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 50px;
        }

        #bowler {
            position: relative;
            width: 200px;
            height: 150px;
            background: url('img/body.webp') no-repeat bottom;
            background-size: contain;
        }

        #hand {
            position: absolute;
            width: 75px;
            height: 100px;
            background: url('img/arm.png') no-repeat center center;
            background-size: contain;
            top: 20px;
            left: 120px;
            transform: rotate(5deg);
            transform-origin: 10% 45%;
            animation: rollHand 1s ease-in-out forwards;
        }

        @keyframes rollHand {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(55deg); }
        }

        #ball {
            position: absolute;
            width: 35px;
            height: 35px;
            background: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ7ZABwe8B4fAbvNNxEb5vxTPFHhLSARlrdJA&s') no-repeat center center;
            border-radius: 50%;
            z-index: 3;
            left: 220px;
            bottom: 50px;
        }
        @keyframes swingBall {
            0% { left: 220px; bottom: 50px;}
            10% { left: 215px; bottom: 40px;}
            50% { left: 200px; bottom: 20px;}
            70% { left: 185px; bottom: 10px;}
            80% { left: 200px; bottom: 10px;}
            100% { left: 210px; bottom: 0px; }
        }

        @keyframes spinBall {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #pins {
            position: absolute;
            width: 100px;
            height: 100px;
            background: url('img/pinset_copy.webp') no-repeat center center;
            background-size: contain;
            right: calc(10%);
            bottom: 0;
            z-index: 2;
            animation: jumpPins 2s ease-in-out paused;
        }

        @keyframes jumpPins {
            0%, 100% { bottom: 0; height: 100px; }
            
            20% { bottom: 0; height: 80px; }
            30% { bottom: 0; height: 90px; }
            
            
            70% { bottom: 100px; height: 100px; }
        }
    </style>

    <footer>
        <div class="footerbody">
            <div id="bowler">
                <div id="hand"></div>
            </div>
            <div id="ball"></div>
            <div id="pins"></div>
        </div>
        
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ball = document.getElementById('ball');
            const pins = document.getElementById('pins');
            const hand = document.getElementById('hand');

            const ballDuration = 8 * 1000;  // 10 seconds

            function startAnimation() {
                // Start the hand animation
                hand.style.animation = 'rollHand 1s ease-in-out forwards';
                ball.style.animation = 'swingBall 500ms ease-in-out '

                // Move the ball across the screen
                setTimeout(() => {
                    ball.style.bottom = '0px';
                    ball.style.transition = `left ${ballDuration}ms linear`;
                    ball.style.left = `calc(100% - 50px)`;
                    ball.style.animation = `spinBall ${ballDuration / 4}ms linear infinite`;
                }, 500);

                // Schedule the pins jump and reset the animation
                setTimeout(() => {
                    pins.style.animation = 'jumpPins 2s ease-in-out';
                }, ballDuration - 2000);

                // Reset everything after the animation completes
                setTimeout(() => {
                    resetAnimation();
                }, ballDuration);
            }

            function resetAnimation() {
                ball.style.transition = 'none';
                ball.style.left = '220px';
                ball.style.bottom = '50px';
                pins.style.animation = 'none';
                hand.style.animation = 'none';
                ball.style.animation = 'none';

                // Force reflow to restart the pins' animation
                void pins.offsetWidth;

                // Start the sequence again after a brief pause
                setTimeout(() => {
                    startAnimation();
                }, 1000);
            }

            // Start the initial animation
            startAnimation();
        });
    </script>
</body>
</html>