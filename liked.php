<?php
session_start();
require_once 'oracle.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Not logged in";
    exit;
}

$userId = $_SESSION['user_id'];
$syairId = intval($_GET['id'] ?? 0);
if (!$syairId) { echo "Invalid ID"; exit; }

// Check if already liked
$checkSql = "SELECT COUNT(*) AS CNT FROM LIKED WHERE userId = :userId AND syairId = :syairId";
$stmt = oci_parse($db_conn, $checkSql);
oci_bind_by_name($stmt, ':userId', $userId);
oci_bind_by_name($stmt, ':syairId', $syairId);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

if ($row['CNT'] > 0) {
    // Already liked, remove it
    $delSql = "DELETE FROM LIKED WHERE userId = :userId AND syairId = :syairId";
    $stmt = oci_parse($db_conn, $delSql);
    oci_bind_by_name($stmt, ':userId', $userId);
    oci_bind_by_name($stmt, ':syairId', $syairId);
    oci_execute($stmt);
    echo json_encode(['status' => 'unliked']);
} else {
    // Not liked, insert
    $insSql = "INSERT INTO LIKED(likedId, userId, syairId, dateLikes) VALUES (liked_seq.NEXTVAL, :userId, :syairId, SYSTIMESTAMP)";
    $stmt = oci_parse($db_conn, $insSql);
    oci_bind_by_name($stmt, ':userId', $userId);
    oci_bind_by_name($stmt, ':syairId', $syairId);
    oci_execute($stmt);
    echo json_encode(['status' => 'liked']);
}
