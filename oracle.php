<?php
// php & Oracle DB connection file
$user = "SyairDigital"; //oracle username
$pass = "SyairDigital"; //Oracle password
$host = "localhost:1521/freepdb1"; //server name or ip address
$db_conn = oci_connect($user, $pass, $host);
if (!$db_conn) {
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
}

function executeQuery($query, $params = [])
{
    global $db_conn;

    $stmt = oci_parse($db_conn, $query);
    if (!$stmt) {
        $e = oci_error($db_conn);
        throw new Exception("SQL Error: " . $e['message']);
    }

    // Bind parameters if provided
    foreach ($params as $key => $value) {
        oci_bind_by_name($stmt, $key, $params[$key]);
    }

    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        throw new Exception("Execution Error: " . $e['message']);
    }

    return $stmt;
}

// Function to fetch all rows
function fetchAll($query, $params = [])
{
    $stmt = executeQuery($query, $params);
    $results = [];

    while ($row = oci_fetch_assoc($stmt)) {
        $results[] = $row;
    }

    return $results;
}
