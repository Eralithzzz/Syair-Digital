<?php
// session_start();

// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit();
// }

// $host = 'localhost';
// $dbname = '';
// $username = 'root';
// $password = '';

// try {
//     $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
//     $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//     $stmt = $conn->prepare("SELECT * FROM syair ORDER BY created_at DESC LIMIT 10");
//     $stmt->execute();
//     $syairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
// } catch (PDOException $e) {
//     $error = 'Connection failed: ' . $e->getMessage();
// }

// if (isset($_GET['logout'])) {
//     session_destroy();
//     header('Location: login.php');
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Syair Digital Collection System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, #6b4ce6 0%, #9b59b6 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 600;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 16px;
        }

        .user-email {
            font-size: 13px;
            opacity: 0.9;
        }

        .btn-logout {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-logout:hover {
            background: white;
            color: #6b4ce6;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .welcome-section h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .welcome-section p {
            color: #718096;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-title {
            color: #718096;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #2d3748;
            font-size: 28px;
            font-weight: 700;
        }

        .syair-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-header h2 {
            color: #2d3748;
            font-size: 24px;
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, #6b4ce6 0%, #9b59b6 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(107, 76, 230, 0.3);
        }

        .syair-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .syair-card {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
            cursor: pointer;
        }

        .syair-card:hover {
            border-color: #6b4ce6;
            box-shadow: 0 5px 15px rgba(107, 76, 230, 0.2);
        }

        .syair-title {
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .syair-meta {
            color: #718096;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .syair-preview {
            color: #4a5568;
            font-size: 14px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 15px;
            }

            .navbar-user {
                flex-direction: column;
                width: 100%;
            }

            .user-info {
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <span>📜</span>
                <span>Syair Digital Collection</span>
            </div>
            <div class="navbar-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
                <a href="?logout=1" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>Explore and manage your syair digital collection</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-title">Total Syair</div>
                <div class="stat-value"><?php echo isset($syairs) ? count($syairs) : 0; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👁️</div>
                <div class="stat-title">Total Views</div>
                <div class="stat-value">1,234</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-title">Favorites</div>
                <div class="stat-value">28</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-title">Collections</div>
                <div class="stat-value">5</div>
            </div>
        </div>

        <div class="syair-section">
            <div class="section-header">
                <h2>Recent Syair</h2>
                <a href="#" class="btn-primary">+ Add New Syair</a>
            </div>

            <?php if (isset($syairs) && count($syairs) > 0): ?>
                <div class="syair-grid">
                    <?php foreach ($syairs as $syair): ?>
                        <div class="syair-card">
                            <div class="syair-title"><?php echo htmlspecialchars($syair['title']); ?></div>
                            <div class="syair-meta">
                                By <?php echo htmlspecialchars($syair['author']); ?> •
                                <?php echo date('M d, Y', strtotime($syair['created_at'])); ?>
                            </div>
                            <div class="syair-preview">
                                <?php echo htmlspecialchars(substr($syair['content'], 0, 150)) . '...'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📜</div>
                    <h3>No Syair Yet</h3>
                    <p>Start building your collection by adding your first syair</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>