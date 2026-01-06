<?php
session_start();
require_once 'oracle.php';

$userId = $_SESSION['user_id'];
$syairId = $_GET['id'];

// Check already liked?
$sql = "SELECT 1 FROM LIKED WHERE userId = :u AND syairId = :s";
$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':u', $userId);
oci_bind_by_name($stmt, ':s', $syairId);
oci_execute($stmt);

if (oci_fetch($stmt)) {
    // Unlike
    $sql = "DELETE FROM LIKED WHERE userId = :u AND syairId = :s";
} else {
    // Like
    $sql = "INSERT INTO LIKED VALUES (liked_seq.NEXTVAL, :u, :s, SYSTIMESTAMP)";
}

$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':u', $userId);
oci_bind_by_name($stmt, ':s', $syairId);
oci_execute($stmt);

header("Location: " . $_SERVER['HTTP_REFERER']);
