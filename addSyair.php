<?php
session_start();
require_once 'oracle.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $author = trim($_POST['author']);
    $yearPublish = $_POST['yearPublish'];

    if (!$title || !$content || !$author) {
        $error = "Please fill in all required fields.";
    } else {
        // Get new syairId
        $sql = "SELECT syair_seq.NEXTVAL AS SYAIRID FROM dual";
        $stmt = oci_parse($db_conn, $sql);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $syairId = $row['SYAIRID'];

        // Insert into SYAIR
        $sql = "INSERT INTO SYAIR (syairId, title, content, author, yearPublish, userId)
                VALUES (:syairId, :title, :content, :author, TO_DATE(:yearPublish,'YYYY-MM-DD'), :userId)";
        $stmt = oci_parse($db_conn, $sql);
        oci_bind_by_name($stmt, ':syairId', $syairId);
        oci_bind_by_name($stmt, ':title', $title);
        oci_bind_by_name($stmt, ':content', $content);
        oci_bind_by_name($stmt, ':author', $author);
        oci_bind_by_name($stmt, ':yearPublish', $yearPublish);
        oci_bind_by_name($stmt, ':userId', $userId);

        if (oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            // Insert initial status = Pending
            $sqlStatus = "INSERT INTO STATUS (statusId, syairId, currentStatus, status_date)
                          VALUES (status_seq.NEXTVAL, :syairId, 'Pending', SYSTIMESTAMP)";
            $stmtStatus = oci_parse($db_conn, $sqlStatus);
            oci_bind_by_name($stmtStatus, ':syairId', $syairId);

            if (oci_execute($stmtStatus, OCI_NO_AUTO_COMMIT)) {
                oci_commit($db_conn);
                $success = "Syair added successfully and is pending approval!";
            } else {
                oci_rollback($db_conn);
                $error = "Failed to set syair status.";
            }
        } else {
            oci_rollback($db_conn);
            $error = "Failed to add syair.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Syair - Syair Digital</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background-color:#f8f9fa; padding:20px; min-height:100vh; }
.container { max-width:700px; margin:auto; background:white; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); overflow:hidden; padding:30px; }
h2 { color:#800000; margin-bottom:15px; }
input, textarea { width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; outline:none; font-size:15px; margin-bottom:15px; }
input:focus, textarea:focus { border-color:#800000; box-shadow:0 0 0 2px rgba(128,0,0,0.1); }
textarea { resize:vertical; height:120px; }
button, a.btn { cursor:pointer; transition:0.3s; }
.btn-primary { background-color:#800000; color:white; border:none; padding:10px 20px; border-radius:8px; text-decoration:none; display:inline-block; }
.btn-primary:hover { background-color:#660000; }
.error { color:#dc3545; background:rgba(220,53,69,0.1); padding:10px; border-radius:5px; margin-bottom:20px; text-align:center; font-size:14px; }
.success { color:#28a745; background:rgba(40,167,69,0.1); padding:10px; border-radius:5px; margin-bottom:20px; text-align:center; font-size:14px; }
</style>
</head>
<body>
<div class="container">
    <h2>Add New Syair</h2>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="content" placeholder="Content" required></textarea>
        <input type="text" name="author" placeholder="Author" required>
        <input type="date" name="yearPublish" required>
        <button type="submit" class="btn-primary">Submit Syair</button>
    </form>
    <br>
    <a href="userDashboard.php" class="btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
</body>
</html>
