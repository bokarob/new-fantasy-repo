<?php
$title = "Newsletter sending";
require_once 'db/conn.php';
require_once 'includes/header.php';
require __DIR__ . '/vendor/autoload.php';

// CONFIG
$template_name = $_GET['name'];   // Selected from admin form
$test_mode   = false; // true = send only to test email
$test_email  = 'bokarob@gmail.com';
$batch_size  = 30;                     // emails per batch
$pause_time  = 4;                        // seconds pause between batches

// Language map
$langMap = [
    1 => 'hu',
    2 => 'en',
    3 => 'de'
];

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Fetch all templates for the given name
$stmt = $pdo->prepare("SELECT template_id, name, lang, subject FROM newsletter_templates WHERE name = ?");
$stmt->execute([$template_name]); // $template_name from POST
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$templates) {
    die("Template not found.");
}

// Build lookup
$templateByLang = [];
foreach ($templates as $tpl) {
    $templateByLang[$tpl['lang']] = $tpl;
}

// 2. Load subscribers or test recipient
if ($test_mode) {
    $subscribers = [[
        'email' => $test_email,
        'alias' => 'Test User',
        'lang_id'  => '1',
        'newsletter_unsubscribe_hash' => 'TEST123'
    ]];
} else {
    $sql = "SELECT email, alias, lang_id, newsletter_unsubscribe_hash
            FROM profile
            WHERE newsletter_subscribe = 1";
    $subscribers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$total = count($subscribers);
if ($total === 0) {
    die("No subscribers to send.");
}

// 3. Sending loop
$count = 0;
foreach ($subscribers as $user) {
    $lang = $langMap[$user['lang_id']] ?? 'en';
    if (!isset($templateByLang[$lang])) continue;

    $tplData = $templateByLang[$lang];

    // Check if already sent
    $checkStmt = $pdo->prepare("SELECT id FROM newsletterlog WHERE email = ? AND template_name = ?");
    $checkStmt->execute([$user['email'], $tplData['name']]);
    if ($checkStmt->fetch()) continue; // Already sent, skip

    // Load HTML file
    $file_path = __DIR__ . "/newsletters/{$tplData['name']}_{$tplData['lang']}.html";
    if (!file_exists($file_path)) continue;

    $html = file_get_contents($file_path);

    // Replace placeholders
    $html = renderTemplate($html, $user);
    $subject = renderTemplate($tplData['subject'], $user);

    // Send email
    $sendStatus = 'sent';
    try {
        sendEmail($user['email'], $subject, $html);
    } catch (Exception $e) {
        $sendStatus = 'error';
    }

    // Log the send
    $logStmt = $pdo->prepare("INSERT INTO newsletterlog (email, template_name, lang, sent_at, subject, status) VALUES (?, ?, ?, NOW(), ?, ?)");
    $logStmt->execute([$user['email'], $tplData['name'], $lang, $subject, $sendStatus]);

    echo "Sending to {$user['email']} with subject '$subject'<br>";
    $count++;
    if ($count % $batch_size === 0) {
        sleep($pause_time);
    }
}

//a newsletter_template táblába be kellene írni hogy mikor küldtük el, hány embernek, stb.

echo "Sent $count emails.";

// --- FUNCTIONS ---

function renderTemplate($content, $user) {
    $placeholders = [
        '{{username}}' => htmlspecialchars($user['alias']),
        '{{token}}' => urlencode($user['newsletter_unsubscribe_hash']),
        '{{unsubscribe_link}}' => "https://example.com/unsubscribe.php?token=" . urlencode($user['newsletter_unsubscribe_hash'])
    ];
    return strtr($content, $placeholders);
}

function sendEmail($to, $subject, $html) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@fantasy9pin.com';
        $mail->Password   = 'Rece.1v.311';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->Port       = 587;

        $mail->setFrom('info@fantasy9pin.com', 'Fantasy 9pin Newsletter');
        $mail->addAddress($to);
        //$mail->addAddress("bokarob@gmail.com");

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;

        $mail->send();
    } catch (Exception $e) {
        error_log("Error sending to $to: {$mail->ErrorInfo}");
        
    }
}
?>
