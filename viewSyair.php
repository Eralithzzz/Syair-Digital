<?php
session_start();
require_once 'oracle.php';

if (!isset($_GET['id'])) {
    header("Location: userDashboard.php");
    exit();
}

$syairId = $_GET['id'];
$fromTab = isset($_GET['from']) ? $_GET['from'] : 'home'; // default ke homepage

$sql = "
SELECT s.title, s.author, s.content, TO_CHAR(s.yearPublish,'YYYY') year
FROM SYAIR s
JOIN STATUS st ON s.syairId = st.syairId
WHERE s.syairId = :id AND st.currentStatus = 'Approved'
";

$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':id', $syairId);
oci_execute($stmt);

$syair = oci_fetch_assoc($stmt);

if (!$syair) {
    echo "Syair not found or not approved.";
    exit();
}

// Handle CLOB content
if ($syair['CONTENT'] instanceof OCILob) {
    $syair['CONTENT'] = $syair['CONTENT']->load();
}

// Tentukan redirect link untuk back button
$backLink = "userDashboard.php";
if (in_array($fromTab, ['home','mySyair','liked','bookmark'])) {
    $backLink .= "#$fromTab";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($syair['TITLE']) ?></title>
<style>
body { background:#f8f9fa; font-family:Arial, sans-serif; }
.container {
    max-width:800px;
    margin:40px auto;
    background:white;
    padding:30px;
    border-radius:15px;
}
h1 { color:#800000; margin-bottom:10px; }
p { margin-bottom:20px; }
pre {
    white-space: pre-wrap;
    font-size:16px;
    line-height:1.7;
}
a.button-back {
    display:inline-block;
    padding:8px 15px;
    background:#800000;
    color:white;
    text-decoration:none;
    border-radius:5px;
    font-weight:600;
    transition:0.3s;
}
a.button-back:hover {
    background:#660000;
}
</style>
</head>
<body>

<div class="container">
    <h1><?= htmlspecialchars($syair['TITLE']) ?></h1>
    <p><b>Author:</b> <?= htmlspecialchars($syair['AUTHOR']) ?> | <?= $syair['YEAR'] ?></p>
    <hr>
    <pre><?= htmlspecialchars($syair['CONTENT']) ?></pre>
    <br>
    <a class="button-back" href="<?= $backLink ?>">← Back</a>
</div>

</body>
</html>
