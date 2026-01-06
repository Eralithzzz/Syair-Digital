<?php
session_start();
require_once 'oracle.php';

if (!isset($_GET['id'])) {
    header("Location: userDashboard.php");
    exit();
}

$syairId = $_GET['id'];

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

if ($syair['CONTENT'] instanceof OCILob) {
    $syair['CONTENT'] = $syair['CONTENT']->load();
}

?>

<!DOCTYPE html>
<html>
<head>
<title><?= htmlspecialchars($syair['TITLE']) ?></title>
<style>
body { background:#f8f9fa; font-family:Arial; }
.container {
    max-width:800px;
    margin:40px auto;
    background:white;
    padding:30px;
    border-radius:15px;
}
h1 { color:#800000; }
pre {
    white-space: pre-wrap;
    font-size:16px;
    line-height:1.7;
}
a { color:#800000; text-decoration:none; font-weight:600; }
</style>
</head>
<body>

<div class="container">
    <h1><?= htmlspecialchars($syair['TITLE']) ?></h1>
    <p><b>Author:</b> <?= htmlspecialchars($syair['AUTHOR']) ?> | <?= $syair['YEAR'] ?></p>
    <hr>
    <pre><?= htmlspecialchars($syair['CONTENT']) ?></pre>
    <br>
    <a href="userDashboard.php">← Back</a>
</div>

</body>
</html>
