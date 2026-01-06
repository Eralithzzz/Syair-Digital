<?php
session_start();
require_once 'oracle.php';

$userId = $_SESSION['user_id'];
$syairId = $_GET['id'];

$sql = "SELECT 1 FROM BOOKMARK WHERE userId = :u AND syairId = :s";
$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':u', $userId);
oci_bind_by_name($stmt, ':s', $syairId);
oci_execute($stmt);

if (oci_fetch($stmt)) {
    $sql = "DELETE FROM BOOKMARK WHERE userId = :u AND syairId = :s";
} else {
    $sql = "INSERT INTO BOOKMARK VALUES (bookmark_seq.NEXTVAL, :u, :s, SYSTIMESTAMP)";
}

$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':u', $userId);
oci_bind_by_name($stmt, ':s', $syairId);
oci_execute($stmt);

header("Location: " . $_SERVER['HTTP_REFERER']);
