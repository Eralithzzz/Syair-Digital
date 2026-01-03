<?php
session_start();
require_once 'oracle.php';

$error = '';
$success = '';
$step = 1; // Default step

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ACTION 1: Verify Email
    if (isset($_POST['action']) && $_POST['action'] === 'verify_email') {
        $email = trim($_POST['email']);
        $sql = "SELECT userId FROM BASE_USER WHERE email = :email";
        $stmt = oci_parse($db_conn, $sql);
        oci_bind_by_name($stmt, ':email', $email);
        oci_execute($stmt);

        if (oci_fetch_assoc($stmt)) {
            $_SESSION['reset_email_temp'] = $email;
            $step = 2; // Move to password reset form
        } else {
            $error = "No account found with that email address.";
        }
    }

    // ACTION 2: Update Password
    if (isset($_POST['action']) && $_POST['action'] === 'reset_now') {
        $new_pass = $_POST['password'];
        $confirm_pass = $_POST['confirm_password'];

        if ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match!";
            $step = 2; // Keep user on step 2
        } else {
            $hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);
            $email = $_SESSION['reset_email_temp'];

            $sql = "UPDATE BASE_USER SET password = :pass WHERE email = :email";
            $stmt = oci_parse($db_conn, $sql);
            oci_bind_by_name($stmt, ':pass', $hashed_password);
            oci_bind_by_name($stmt, ':email', $email);

            if (oci_execute($stmt)) {
                unset($_SESSION['reset_email_temp']);
                $_SESSION['register_success'] = "Password updated! You can now login.";
                header('Location: login.php');
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Account Recovery - Syair Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            display: flex;
            max-width: 900px;
            width: 100%;
            height: 550px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Left Panel */
        .left-panel {
            flex: 1;
            background-color: #800000;
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo i {
            font-size: 40px;
            margin-bottom: 20px;
            color: #ffeb3b;
        }

        .left-panel h1 {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .tagline {
            font-size: 18px;
            line-height: 1.6;
            opacity: 0.9;
            font-style: italic;
        }

        /* Right Panel */
        .right-panel {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }

        h2 {
            color: #800000;
            font-size: 32px;
            margin-bottom: 10px;
        }

        p {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
        }

        input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            transition: 0.3s;
        }

        input:focus {
            border-color: #800000;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        .btn {
            background-color: #800000;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
        }

        .btn:hover {
            background-color: #660000;
        }

        .error {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }

        .links {
            margin-top: 25px;
            text-align: center;
        }

        .links a {
            color: #800000;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                height: auto;
            }

            .left-panel,
            .right-panel {
                padding: 30px;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="left-panel">
            <div class="logo"><i class="fas fa-shield-alt"></i></div>
            <h1>Recovery Portal</h1>
            <p class="tagline">"Forgot your password? Don't worry, we've got you covered."</p>
        </div>

        <div class="right-panel">

            <?php if ($step === 1): ?>
                <h2>Forgot Password?</h2>
                <p>Enter your email address to recover your account.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="verify_email">
                    <div class="form-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                    <button type="submit" class="btn">Verify Account</button>
                </form>

            <?php else: ?>
                <h2>New Password</h2>
                <p>Set a new password for <br><strong><?php echo htmlspecialchars($_SESSION['reset_email_temp']); ?></strong></p>

                <form method="POST">
                    <input type="hidden" name="action" value="reset_now">
                    <div class="form-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="New Password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <i class="fas fa-check-double"></i>
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required minlength="6">
                    </div>
                    <button type="submit" class="btn">Update Password</button>
                </form>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="links">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>

</body>

</html>