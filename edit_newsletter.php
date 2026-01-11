<?php
$title = "Newsletter edit";
require_once 'db/conn.php';
require_once 'includes/header.php';

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM newsletter_templates WHERE template_id = ?");
$stmt->execute([$id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) die("Template not found");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'];
    $body_html = $_POST['body_html'];

    // Save HTML to file
    file_put_contents($template['file_path'], $body_html);

    // Update subject
    $stmt = $pdo->prepare("UPDATE newsletter_templates SET subject=? WHERE template_id=?");
    $stmt->execute([$subject, $id]);

    echo '<script type="text/javascript">location.href="newsletter_template_list.php";</script>
<noscript><meta http-equiv="refresh" content="0; URL=newsletter_template_list.php" /></noscript> ';
    exit;
}

// Load file content
$body_html = file_exists($template['file_path']) ? file_get_contents($template['file_path']) : '';
?>

    <script src="https://cdn.tiny.cloud/1/yn8wk3ue9n72g39gmuumouxk6qn3yrc97uydhdwkhap8p75w/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
    <script>
    tinymce.init({
        selector: '#body_html',
        height: 500,
        plugins: 'link image lists table code',
        toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | link image | code',
        content_style: 'body { font-family:Arial,Helvetica,sans-serif; font-size:16px; }'
        });
    </script>

<h1>Edit Template: <?= htmlspecialchars($template['name']) ?> (<?= strtoupper($template['lang']) ?>)</h1>
<form method="post">
    Subject: <input type="text" name="subject" value="<?= htmlspecialchars($template['subject']) ?>" size="80"><br><br>
    <textarea id="body_html" name="body_html"><?= htmlspecialchars($body_html) ?></textarea><br>
    <button type="submit">Save</button>
</form>
</body>
</html>
