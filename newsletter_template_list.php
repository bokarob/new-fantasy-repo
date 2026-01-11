<?php
$title = "Newsletter list";
require_once 'db/conn.php';
require_once 'includes/header.php';

// Fetch grouped templates

$templates = $admin->fetchNewsletterGroups();
?>

<h1>Newsletter Templates</h1>
<a href="create_newsletter_template.php">Create New Template</a>
<table border="1" cellpadding="5">
<tr><th>Name</th><th>Languages</th><th>Actions</th></tr>
<?php foreach($templates as $t): ?>
<tr>
    <td><?= htmlspecialchars($t['name']) ?></td>
    <td>
        <?php
        $langs = explode(',', $t['langs']);
        foreach ($langs as $l) {
            list($lang,$id) = explode(':', $l);
            echo strtoupper($lang)." <a href='edit_newsletter.php?id=$id'>(Edit)</a> ";
            echo "<a href='preview_newsletter.php?id=$id' target='_blank'>(Preview)</a> ";
        }
        ?>
    </td>
    <td><a href="send_newsletter.php?name=<?= urlencode($t['name']) ?>">Send</a></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
