<?php
session_start();
require_once 'oracle.php';

if (!isset($_GET['id'])) {
    header("Location: userDashboard.php");
    exit();
}

$syairId = $_GET['id'];
$fromTab = isset($_GET['from']) ? $_GET['from'] : 'home';

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

$backLink = "userDashboard.php";
if (in_array($fromTab, ['homepage', 'mySyair', 'liked', 'bookmark'])) {
    $backLink .= "#$fromTab";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($syair['TITLE']) ?></title>
    <style>
        body {
            background: #f0f2f5;
            font-family: "Poppins", Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            width: 90%;
            margin: 40px auto;
            background: #fff;
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #800000;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .meta {
            color: #555;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        pre {
            white-space: pre-wrap;
            font-size: 1rem;
            line-height: 1.8;
            color: #333;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        a.button-back {
            display: inline-block;
            padding: 10px 18px;
            background: #800000;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: 0.3s;
        }

        a.button-back:hover {
            background: #660000;
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px 15px;
            }

            h1 {
                font-size: 1.5rem;
            }

            pre {
                font-size: 0.95rem;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <h1><?= htmlspecialchars($syair['TITLE']) ?></h1>
        <div class="meta"><b>Author:</b> <?= htmlspecialchars($syair['AUTHOR']) ?> | <?= $syair['YEAR'] ?></div>
        <hr>
        <pre><?= htmlspecialchars($syair['CONTENT']) ?></pre>
        <br>
        <a class="button-back" href="<?= $backLink ?>">← Back</a>
    </div>

</body>

</html>