<?php 
$title = "Kezdőlap";
require_once 'includes/header.php';
require_once 'db/conn.php';

?>
    '<html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header, .footer { background-color: #f1f1f1; padding: 10px 20px; }
            .header{display: flex; align-items: center;}
            .header h1 { margin: 0; background: linear-gradient(to right, #b927fc 0%, #2c64fc 100%);-webkit-background-clip: text;  background-clip: text;  -webkit-text-fill-color: transparent;  margin-bottom: 0; text-align: center;}
            .content { margin: 20px 0; }
            .content p { line-height: 1.5; }
            .content h4{ margin-bottom: 40px;}
            .content button{display: inline-block;  outline: 0; border: 0;
                background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% );
                box-shadow: 0px 2px 4px rgb(45 35 66 / 40%), 0px 7px 13px -3px rgb(45 35 66 / 30%), inset 0px -3px 0px rgb(58 65 111 / 50%);
                padding: 0 32px;
                border-radius: 6px;
                color: #fff;
                height: 48px;
                font-size: 18px;
                text-shadow: 0 1px 0 rgb(0 0 0 / 40%);
                margin-bottom: 50px;
                }
            .footer p { font-size: 12px; color: #777; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Welcome to Fantasy 9pin Leauge</h1>
            </div>
            <div class="content">
                <h4>Dear $alias,</h4>
                <p>We are excited that you decided to test your skills and become a fantasy 9pin trainer. The road to win the league is long and hard, but it starts with a simple step: confirm your registration.</p>
                <p>So in order to start assembling your fantasy team, please click on this link:</p>
                <a href="http://fantasy9pin.com/registration-confirmation.php?token=$token">
                    <button>CONFIRM REGISTRATION</button>
                </a>
                <p>See you down the alley, future trainer..</p>
            </div>
            <div class="footer">
                <p>&copy; 2024 Fantasy 9pin - All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>'