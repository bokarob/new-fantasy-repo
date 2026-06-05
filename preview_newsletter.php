<?php
$title = "Newsletter preview";
require_once 'db/conn.php';
require_once 'includes/header.php';

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM newsletter_templates WHERE template_id = ?");
$stmt->execute([$id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) die("Template not found");

echo "<h1>".htmlspecialchars($template['subject'])."</h1>";
echo file_get_contents($template['file_path']);
