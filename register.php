<?php
session_start();
require_once 'oracle.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $pass     = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $error = 'Passwords do not match';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check email
        $sql = "SELECT 1 FROM BASE_USER WHERE email = :email";
        $stmt = oci_parse($db_conn, $sql);
        oci_bind_by_name($stmt, ':email', $email);
        oci_execute($stmt);

        if (oci_fetch($stmt)) {
            $error = 'Email already registered';
        } else {
            // Check username 
            $sql = "SELECT 1 FROM BASE_USER WHERE username = :username";
            $stmt = oci_parse($db_conn, $sql);
            oci_bind_by_name($stmt, ':username', $username);
            oci_execute($stmt);

            if (oci_fetch($stmt)) {
                $error = 'Username already taken';
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);

                // Get new userId
                $sql = "SELECT baseuser_seq.NEXTVAL AS USERID FROM dual";
                $stmt = oci_parse($db_conn, $sql);
                oci_execute($stmt);
                $row = oci_fetch_assoc($stmt);
                $userId = $row['USERID'];

                // Insert into BASE_USER
                $sql = "INSERT INTO BASE_USER (userId, email, name, username, password, role) 
                        VALUES (:userId, :email, :name, :username, :password, 'Users')";

                $stmt = oci_parse($db_conn, $sql);
                oci_bind_by_name($stmt, ':userId', $userId);
                oci_bind_by_name($stmt, ':email', $email);
                oci_bind_by_name($stmt, ':name', $name);
                oci_bind_by_name($stmt, ':username', $username);
                oci_bind_by_name($stmt, ':password', $hashed);

                if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
                    oci_rollback($db_conn);
                    $error = 'Failed to create account';
                } else {
                    // Insert into USERS table
                    $sql = "INSERT INTO USERS (userId) VALUES (:userId)";
                    $stmt = oci_parse($db_conn, $sql);
                    oci_bind_by_name($stmt, ':userId', $userId);

                    if (oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
                        oci_commit($db_conn);
                        // Redirect to login (Success message will be shown there via Session)
                        $_SESSION['register_success'] = 'Registration successful! Please login.';
                        header('Location: login.php');
                        exit();
                    } else {
                        oci_rollback($db_conn);
                        $error = 'Failed to assign user role';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Syair Digital Collection</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
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

        .container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            min-height: 700px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

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
            font-size: 48px;
            margin-right: 15px;
            color: #ffeb3b;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 700;
        }

        .tagline {
            font-size: 18px;
            line-height: 1.6;
            opacity: 0.9;
        }

        .right-panel {
            flex: 1.2;
            padding: 40px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: white;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h2 {
            color: #800000;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 15px;
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
            width: 20px;
            text-align: center;
        }

        .input-with-icon input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            outline: none;
        }

        .input-with-icon input:focus {
            border-color: #800000;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        .register-button {
            background-color: #800000;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
            margin-top: 15px;
        }

        .register-button:hover {
            background-color: #660000;
        }

        .links {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #666;
        }

        .links a {
            color: #800000;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 850px) {
            .container {
                flex-direction: column;
                max-width: 500px;
                height: auto;
            }

            .left-panel {
                padding: 40px 30px;
                text-align: center;
            }

            .logo {
                justify-content: center;
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
                "Nadi Warisan, Inovasi Masa Depan."
                <br><br>
                Join our exclusive syair community to preserve heritage while connecting through smart digital collection.
            </p>
        </div>

        <div class="right-panel">
            <div class="header">
                <h2>Register</h2>
                <p>Create your student account today</p>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-at"></i>
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="name" placeholder="Full Name" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Password" required minlength="6">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-check-double"></i>
                        <input type="password" name="confirm_password" placeholder="Confirm Password" required minlength="6">
                    </div>
                </div>

                <button type="submit" class="register-button">Create Account</button>
            </form>

            <div class="links">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: '<?php echo $error; ?>',
                confirmButtonColor: '#800000'
            });
        </script>
    <?php endif; ?>

</body>

</html>