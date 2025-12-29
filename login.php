<?php
session_start();
require_once 'oracle.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header('Location: adminDashboard.php');
    } else {
        header('Location: userDashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email']);
        $pass  = $_POST['password'];

        $sql = "SELECT userId, username, email, password, role
                FROM BASE_USER
                WHERE email = :email";

        $stmt = oci_parse($db_conn, $sql);
        oci_bind_by_name($stmt, ':email', $email);
        oci_execute($stmt);

        $user = oci_fetch_assoc($stmt);

        if ($user && password_verify($pass, $user['PASSWORD'])) {

            $_SESSION['user_id']  = $user['USERID'];
            $_SESSION['username'] = $user['USERNAME'];
            $_SESSION['email']    = $user['EMAIL'];
            $_SESSION['role']     = $user['ROLE'];

            // 🔀 Redirect by role
            if ($user['ROLE'] === 'Admin') {
                header('Location: adminDashboard.php');
            } else {
                header('Location: userDashboard.php');
            }
            exit();

        } else {
            $error = 'Invalid email or password';
        }

    } catch (Exception $e) {
        $error = 'Login error: ' . $e->getMessage();
    }
}
?>
