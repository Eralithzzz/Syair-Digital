<?php
session_start();
require_once 'oracle.php';

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Logout Logic
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$syairs = [];
$error = '';

// --- 2. DATA FETCHING ---
try {
    // Fetch latest 10 poems
    // Note: Oracle keys are returned in UPPERCASE by default
    $sql = "SELECT syairId, title, author, content, TO_CHAR(created_at, 'DD MON YYYY') as DATE_CREATED
            FROM syair
            ORDER BY created_at DESC
            FETCH FIRST 10 ROWS ONLY";

    $stmt = oci_parse($db_conn, $sql);
    oci_execute($stmt);

    while ($row = oci_fetch_assoc($stmt)) {
        $syairs[] = $row;
    }
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Syair Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #800000;
            /* Maroon */
            --accent: #ffeb3b;
            /* Gold */
            --bg: #f8f9fa;
            /* Light Gray */
            --text: #333;
            --sidebar-width: 270px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            outline: none;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* --- SIDEBAR (Maroon Theme) --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary);
            color: white;
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        .logo {
            padding: 0 30px 30px;
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            color: var(--accent);
            font-size: 1.8rem;
        }

        .menu {
            padding: 20px 15px;
            flex-grow: 1;
        }

        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 14px 20px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .sidebar a i {
            width: 30px;
            font-size: 1.1rem;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--accent);
            padding-left: 25px;
            /* Slide effect */
        }

        /* Logout Button */
        .sidebar .logout {
            margin-top: auto;
            margin-bottom: 20px;
            color: #ffcccc;
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar .logout:hover {
            background: #b30000;
            color: white;
        }

        /* --- MAIN CONTENT --- */
        .main {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
        }

        /* Topbar */
        .topbar {
            height: 70px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.03);
        }

        .welcome-text h2 {
            font-size: 1.2rem;
            color: var(--primary);
            margin: 0;
        }

        .welcome-text p {
            font-size: 0.85rem;
            color: #777;
            margin: 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 45px;
            height: 45px;
            background: var(--primary);
            color: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            border: 2px solid var(--accent);
        }

        .content {
            padding: 40px;
        }

        /* --- STATS CARDS --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s;
            border-left: 4px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-info h3 {
            font-size: 2rem;
            margin: 0;
            color: var(--primary);
        }

        .stat-info p {
            margin: 0;
            color: #777;
            font-size: 0.9rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #eee;
        }

        /* --- POEM GRID --- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        .btn-add {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.3);
            transition: 0.3s;
        }

        .btn-add:hover {
            background: #600000;
            transform: translateY(-2px);
        }

        .syair-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .syair-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .syair-card:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(128, 0, 0, 0.1);
        }

        .card-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .syair-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .syair-meta {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }

        .syair-preview {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
            font-family: 'Georgia', serif;
            font-style: italic;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-read {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 8px 0;
            border-radius: 6px;
            width: 100%;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-read:hover {
            background: var(--primary);
            color: white;
        }

        /* --- MODAL --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 40px;
            border-radius: 15px;
            width: 600px;
            max-width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .close {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 1.8rem;
            cursor: pointer;
            color: #aaa;
            transition: 0.3s;
        }

        .close:hover {
            color: var(--primary);
        }

        .modal-title {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 5px;
            font-weight: 700;
            border-bottom: 2px solid var(--accent);
            display: inline-block;
            padding-bottom: 5px;
        }

        .modal-author {
            color: #666;
            font-size: 1rem;
            margin-bottom: 30px;
            font-style: italic;
        }

        .modal-body {
            font-family: 'Georgia', serif;
            font-size: 1.15rem;
            line-height: 2;
            color: #333;
            white-space: pre-wrap;
            background: #fafafa;
            padding: 30px;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-pen-nib"></i>
            <span>Syair Digital</span>
        </div>
        <div class="menu">
            <a href="#" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="#"><i class="fas fa-heart"></i> My Favorites</a>
            <a href="#"><i class="fas fa-layer-group"></i> Collections</a>
            <a href="#"><i class="fas fa-user"></i> My Profile</a>

            <a href="?logout=1" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="welcome-text">
                <h2>Welcome, <?php echo htmlspecialchars(ucfirst($_SESSION['username'])); ?></h2>
                <p>Explore the heritage of Malay poetry.</p>
            </div>
            <div class="user-profile">
                <div style="text-align: right;">
                    <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars(ucfirst($_SESSION['username'])); ?></div>
                    <div style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
            </div>
        </div>

        <div class="content">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo isset($syairs) ? count($syairs) : 0; ?></h3>
                        <p>Available Poems</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>12</h3>
                        <p>Favorites</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>5</h3>
                        <p>My Collections</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
                </div>
            </div>

            <div class="syair-section">
                <div class="section-header">
                    <div class="section-title">Latest Collection</div>
                    <a href="#" class="btn-add"><i class="fas fa-plus"></i> Submit Poem</a>
                </div>

                <?php if (isset($syairs) && count($syairs) > 0): ?>
                    <div class="syair-grid">
                        <?php foreach ($syairs as $syair):
                            // Handle CLOB if using Oracle
                            $content = is_object($syair['CONTENT']) ? $syair['CONTENT']->load() : $syair['CONTENT'];
                        ?>
                            <div class="syair-card">
                                <div class="card-icon"><i class="fas fa-feather-alt"></i></div>
                                <div class="syair-title"><?php echo htmlspecialchars($syair['TITLE']); ?></div>
                                <div class="syair-meta">
                                    <span><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($syair['AUTHOR']); ?></span>
                                    <span><?php echo $syair['DATE_CREATED']; ?></span>
                                </div>
                                <div class="syair-preview">
                                    <?php echo htmlspecialchars(substr($content, 0, 100)) . '...'; ?>
                                </div>

                                <textarea id="content_<?php echo $syair['SYAIRID']; ?>" style="display:none;"><?php echo htmlspecialchars($content); ?></textarea>

                                <button class="btn-read" onclick="viewPoem(
                                    '<?php echo htmlspecialchars($syair['TITLE'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($syair['AUTHOR'], ENT_QUOTES); ?>', 
                                    document.getElementById('content_<?php echo $syair['SYAIRID']; ?>').value
                                )">Read Full Poem</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:50px; color:#999;">
                        <i class="fas fa-scroll" style="font-size:4rem; margin-bottom:20px; color:#ddd;"></i>
                        <p>No poems found in the collection yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-title" id="m_title"></div>
            <div class="modal-author">Written by <span id="m_author"></span></div>
            <div class="modal-body" id="m_body"></div>
        </div>
    </div>

    <script>
        const modal = document.getElementById("viewModal");

        function viewPoem(title, author, content) {
            document.getElementById('m_title').innerText = title;
            document.getElementById('m_author').innerText = author;
            document.getElementById('m_body').innerText = content;
            modal.style.display = "block";
        }

        function closeModal() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>

</html>