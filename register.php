<?php
session_start();

$host = 'localhost';
$dbname = '';
$username = 'root';
$password = '';

if (isset($_SESSION['user_id'])) {
    header('Location: userDashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $user = $_POST['username'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $pass = $_POST['password'];
        $confirm_pass = $_POST['confirm_password'];

        // Validation
        if ($pass !== $confirm_pass) {
            $error = 'Passwords do not match';
        } else if (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = 'Email already registered';
            } else {
                // Check if username already exists
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
                $stmt->bindParam(':username', $user);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $error = 'Username already taken';
                } else {
                    // Insert new user
                    $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, name, email, password, created_at) VALUES (:username, :name, :email, :password, NOW())");
                    $stmt->bindParam(':username', $user);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password', $hashed_pass);

                    if ($stmt->execute()) {
                        $success = 'Registration successful! You can now login.';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Connection failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Syair Digital Collection System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1f3a 0%, #2d1b4e 50%, #1a1f3a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }

        .header {
            background: linear-gradient(135deg, #6b4ce6 0%, #9b59b6 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-container {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f7fafc;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #6b4ce6;
            background: white;
            box-shadow: 0 0 0 3px rgba(107, 76, 230, 0.1);
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6b4ce6 0%, #9b59b6 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(107, 76, 230, 0.3);
        }

        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c53030;
        }

        .success {
            background: #c6f6d5;
            color: #22543d;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #22543d;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 14px;
        }

        .login-link a {
            color: #6b4ce6;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .login-link a:hover {
            color: #9b59b6;
        }

        .decorative-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="register-container">
        <div class="header">
            <div class="decorative-icon">📜</div>
            <h1>Syair Digital Collection</h1>
            <p>Create your account</p>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>

                <button type="submit" class="btn-register">Register</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</body>

</html>