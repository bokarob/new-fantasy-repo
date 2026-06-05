<?php
$title = "Newsletter creation";
require_once 'db/conn.php';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = preg_replace('/[^a-z0-9_\-]/i', '', $_POST['name']); // safe filename
    $langs = ['en','de','hu'];

    $starterLayout = <<<HTML
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f5f5;padding:20px 0;">
        <tr>
            <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border:1px solid #ddd;font-family:Arial,Helvetica,sans-serif;">
                <!-- HEADER -->
                <tr>
                <td align="center" style="background:#004aad;color:#ffffff;padding:20px;font-size:24px;font-weight:bold;">
                    Fantasy 9pin Newsletter
                </td>
                </tr>
                <!-- BODY -->
                <tr>
                <td style="padding:20px;font-size:16px;line-height:1.5;color:#333333;">
                    <h2 style="margin-top:0;">Title here</h2>
                    <p>
                    Welcome to our newsletter, {{username}}! Replace this text with your content.  
                    You can add <strong>bold text</strong>, <a href="#">links</a>, and images.
                    </p>
                    <p>
                    Remember to keep content short and clear for best results.
                    </p>
                </td>
                </tr>
                <!-- FOOTER -->
                <tr>
                <td align="center" style="padding:15px;font-size:12px;color:#999999;">
                    📩 You’re receiving this email because you signed up for Fantasy 9pin reminders and updates. If you’ve changed your mind, you can <a href="https://fantasy9pin.com/unsubscribe.php?token={{token}}" style="color:#004aad;text-decoration:none;">UNSUBSCRIBE</a> from the newsletter anytime.
                </td>
                </tr>
            </table>
            </td>
        </tr>
        </table>
        HTML;


    foreach ($langs as $lang) {
        $filename = "newsletters/{$name}_{$lang}.html";
        file_put_contents($filename, $starterLayout); // empty template

        $newtemplate=$admin->newNewsletterTemplate($name, $lang, '',$filename);
    }

    echo '<script type="text/javascript">location.href="newsletter_template_list.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=newsletter_template_list.php" /></noscript> ';
    exit;
}
?>

<h1>Create New Template</h1>
<form method="post">
    Template Name (no spaces): <input type="text" name="name" required>
    <button type="submit">Create</button>
</form>
</body>
</html>
