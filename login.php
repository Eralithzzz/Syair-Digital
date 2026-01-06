<?php
session_start();
require_once 'oracle.php';

$swal = null; // Initialize SweetAlert variable

// 1. Check for success message from registration
if (isset($_SESSION['register_success'])) {
    $swal = [
        'icon' => 'success',
        'title' => 'Registration Successful',
        'text' => $_SESSION['register_success']
    ];
    unset($_SESSION['register_success']);
}

// 2. Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header('Location: adminDashboard.php');
    } else {
        header('Location: userDashboard.php');
    }
    exit();
}

// 3. Handle Login Logic
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

            // Redirect by role
            if ($user['ROLE'] === 'Admin') {
                header('Location: adminDashboard.php');
            } else {
                header('Location: userDashboard.php');
            }
            exit();
        } else {
            $swal = [
                'icon' => 'error',
                'title' => 'Login Failed',
                'text' => 'Invalid email or password'
            ];
        }
    } catch (Exception $e) {
        $swal = [
            'icon' => 'error',
            'title' => 'System Error',
            'text' => $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Syair Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* 1. RESET & BODY */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* 2. MAIN CARD CONTAINER */
        .container {
            display: flex;
            max-width: 900px;
            width: 100%;
            height: 550px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* 3. LEFT PANEL (Maroon) */
        .left-panel {
            flex: 1;
            background-color: #800000;
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 36px;
            margin-right: 15px;
            color: #ffeb3b;
            /* Accent Gold */
        }

        .logo h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .tagline {
            font-size: 18px;
            line-height: 1.6;
            opacity: 0.9;
        }

        /* 4. RIGHT PANEL (Login Form) */
        .right-panel {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: white;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: #800000;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 16px;
        }

        /* 5. INPUTS */
        .form-group {
            margin-bottom: 25px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
        }

        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
            outline: none;
        }

        .input-with-icon input:focus {
            border-color: #800000;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        /* 6. LOGIN BUTTON */
        .login-button {
            background-color: #800000;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .login-button:hover {
            background-color: #660000;
        }

        /* 7. LINKS SECTION */
        .links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .links a {
            color: #800000;
            text-decoration: none;
            transition: color 0.3s;
        }

        .links a:hover {
            color: #660000;
            text-decoration: underline;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                max-width: 500px;
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
            <div class="logo">
                <i class="fas fa-pen-nib"></i>
                <h1>Syair Digital</h1>
            </div>
            <p class="tagline">
                Preserving Heritage in the Virtual Realm, Transparent Trading with Cultural Dignity.
            </p>
        </div>

        <div class="right-panel">
            <div class="login-header">
                <h2>Login</h2>
                <p>Sign in to your account</p>
            </div>

            <form action="" method="POST">
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="text" name="email" placeholder="Email Address" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                </div>

                <button type="submit" class="login-button">Login</button>
            </form>

            <div class="links">
                <p>New here? <span><a href="register.php">Register now!</a></span></p>
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
        </div>
    </div>

    <?php if ($swal): ?>
        <script>
            Swal.fire({
                icon: '<?php echo $swal['icon']; ?>',
                title: '<?php echo $swal['title']; ?>',
                text: '<?php echo $swal['text']; ?>',
                confirmButtonColor: '#800000'
            });
        </script>
    <?php endif; ?>

</body>

</html>