<?php
session_start();
require_once 'oracle.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$swal = null;

// --- 2. BACKEND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. ADD POEM
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'add_syair') {
        try {
            $idRes = fetchAll("SELECT syair_seq.NEXTVAL AS NEXT_ID FROM DUAL");
            $new_sid = $idRes[0]['NEXT_ID'];

            $sqlSyair = "INSERT INTO SYAIR (syairId, title, theme, content, author, yearPublish, userId) 
                         VALUES (:sid, :title, :theme, :content, :author, TO_DATE(:pdate, 'YYYY-MM-DD'), :admin_id)";

            executeQuery($sqlSyair, [
                ':sid'      => $new_sid,
                ':title'    => $_POST['title'],
                ':theme'    => $_POST['theme'],
                ':content'  => $_POST['content'],
                ':author'   => $_POST['author'],
                ':pdate'    => $_POST['publish_date'],
                ':admin_id' => $_SESSION['user_id']
            ]);

            $sqlStatus = "INSERT INTO STATUS (statusId, syairId, currentStatus, updated_by, status_date)
                          VALUES (status_seq.NEXTVAL, :sid, 'Approved', :admin_id, SYSTIMESTAMP)";
            executeQuery($sqlStatus, [':sid' => $new_sid, ':admin_id' => $_SESSION['user_id']]);

            $swal = ['icon' => 'success', 'title' => 'Poem Added', 'text' => 'New poem has been published successfully.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
        }
    }

    // B. EDIT POEM
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'edit_syair') {
        try {
            $sqlEdit = "UPDATE SYAIR SET title = :title, content = :content, author = :author, yearPublish = TO_DATE(:pdate, 'YYYY-MM-DD') 
                        WHERE syairId = :sid";
            executeQuery($sqlEdit, [
                ':title'   => $_POST['edit_title'],
                ':theme'   => $_POST['edit_theme'],
                ':content' => $_POST['edit_content'],
                ':author'  => $_POST['edit_author'],
                ':pdate'   => $_POST['edit_date'],
                ':sid'     => $_POST['edit_syair_id']
            ]);
            $swal = ['icon' => 'success', 'title' => 'Poem Updated', 'text' => 'The poem details have been updated.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Update Failed', 'text' => $e->getMessage()];
        }
    }

    // C. DELETE POEM
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'delete_syair') {
        try {
            $sid = $_POST['delete_syair_id'];
            executeQuery("DELETE FROM BOOKMARK WHERE syairId = :sid", [':sid' => $sid]);
            executeQuery("DELETE FROM LIKED WHERE syairId = :sid", [':sid' => $sid]);
            executeQuery("DELETE FROM STATUS WHERE syairId = :sid", [':sid' => $sid]);
            executeQuery("DELETE FROM SYAIR WHERE syairId = :sid", [':sid' => $sid]);
            $swal = ['icon' => 'success', 'title' => 'Deleted', 'text' => 'The poem has been permanently deleted.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Delete Failed', 'text' => $e->getMessage()];
        }
    }

    // D. APPROVE/REJECT
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'update_status') {
        try {
            $sql = "UPDATE STATUS SET currentStatus = :status, updated_by = :adminId, status_date = SYSTIMESTAMP WHERE syairId = :syairId";
            executeQuery($sql, [':status' => $_POST['status'], ':adminId' => $_SESSION['user_id'], ':syairId' => $_POST['syair_id']]);
            $swal = ['icon' => 'success', 'title' => 'Status Updated', 'text' => 'Request successfully ' . strtolower($_POST['status']) . '.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
        }
    }

    // E. ADD USER
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'add_user') {
        try {
            $idRes = fetchAll("SELECT baseuser_seq.NEXTVAL AS NEXT_ID FROM DUAL");
            $new_id = $idRes[0]['NEXT_ID'];
            $hashed = password_hash($_POST['password'], PASSWORD_BCRYPT);

            executeQuery(
                "INSERT INTO BASE_USER (userId, email, name, username, password, role) VALUES (:b_id, :em, :nm, :un, :pw, :rl)",
                [':b_id' => $new_id, ':em' => $_POST['email'], ':nm' => $_POST['name'], ':un' => $_POST['username'], ':pw' => $hashed, ':rl' => $_POST['role']]
            );

            $tbl = ($_POST['role'] == 'Admin') ? 'ADMIN' : 'USERS';
            executeQuery("INSERT INTO $tbl (userId) VALUES (:b_id)", [':b_id' => $new_id]);
            $swal = ['icon' => 'success', 'title' => 'User Created', 'text' => 'New ' . $_POST['role'] . ' account created.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Creation Failed', 'text' => $e->getMessage()];
        }
    }

    // F. EDIT USER
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'edit_user') {
        try {
            executeQuery(
                "UPDATE BASE_USER SET name=:nm, email=:em, role=:rl WHERE userId=:edit_id",
                [':nm' => $_POST['edit_name'], ':em' => $_POST['edit_email'], ':rl' => $_POST['edit_role'], ':edit_id' => $_POST['edit_user_id']]
            );
            $swal = ['icon' => 'success', 'title' => 'User Updated', 'text' => 'User details have been saved.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Update Failed', 'text' => $e->getMessage()];
        }
    }

    // G. DELETE USER
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'delete_user') {
        try {
            $uid = $_POST['delete_user_id'];
            if ($uid == $_SESSION['user_id']) throw new Exception("Cannot delete self.");

            executeQuery("DELETE FROM LIKED WHERE userId=:del_id", [':del_id' => $uid]);
            executeQuery("DELETE FROM BOOKMARK WHERE userId=:del_id", [':del_id' => $uid]);
            executeQuery("DELETE FROM BOOKMARK WHERE syairId IN (SELECT syairId FROM SYAIR WHERE userId=:del_id)", [':del_id' => $uid]);
            executeQuery("DELETE FROM LIKED WHERE syairId IN (SELECT syairId FROM SYAIR WHERE userId=:del_id)", [':del_id' => $uid]);
            executeQuery("DELETE FROM STATUS WHERE syairId IN (SELECT syairId FROM SYAIR WHERE userId=:del_id)", [':del_id' => $uid]);
            executeQuery("DELETE FROM SYAIR WHERE userId=:del_id", [':del_id' => $uid]);
            executeQuery("UPDATE STATUS SET updated_by=NULL WHERE updated_by=:del_id", [':del_id' => $uid]);
            executeQuery("DELETE FROM ADMIN WHERE userId=:del_id", [':del_id' => $uid]);
            executeQuery("DELETE FROM USERS WHERE userId=:del_id", [':del_id' => $uid]);
            executeQuery("DELETE FROM BASE_USER WHERE userId=:del_id", [':del_id' => $uid]);
            $swal = ['icon' => 'success', 'title' => 'User Deleted', 'text' => 'User and all associated data deleted.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Delete Failed', 'text' => $e->getMessage()];
        }
    }
}

// --- 3. DATA FETCHING ---
try {
    $countUsers = fetchAll("SELECT COUNT(*) AS CNT FROM BASE_USER")[0]['CNT'];
    $countSyair = fetchAll("SELECT COUNT(*) AS CNT FROM SYAIR")[0]['CNT'];
    $countPending = fetchAll("SELECT COUNT(*) AS CNT FROM STATUS WHERE currentStatus = 'Pending'")[0]['CNT'];
    $countApproved = fetchAll("SELECT COUNT(*) AS CNT FROM STATUS WHERE currentStatus = 'Approved'")[0]['CNT'];
    $countRejected = fetchAll("SELECT COUNT(*) AS CNT FROM STATUS WHERE currentStatus = 'Rejected'")[0]['CNT'];

    $pendingList = fetchAll("SELECT s.syairId, s.title, s.content, b.name as author, TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as PUB_DATE 
                             FROM SYAIR s JOIN BASE_USER b ON s.userId = b.userId 
                             JOIN STATUS st ON s.syairId = st.syairId WHERE st.currentStatus = 'Pending'");

    $managePoemsList = fetchAll("SELECT s.syairId, s.title, s.theme, s.content, s.author, st.currentStatus, TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as PUB_DATE 
                              FROM SYAIR s 
                              JOIN BASE_USER b ON s.userId = b.userId 
                              JOIN STATUS st ON s.syairId = st.syairId 
                              ORDER BY s.syairId DESC");

    // REPORT LOGIC
    $reportFilter = $_GET['filter'] ?? 'All';
    $reportType   = $_GET['report_type'] ?? 'poems';

    if ($reportFilter == 'All') {
        $sqlReport = "SELECT s.syairId, s.title, s.theme, s.content, s.author, st.currentStatus, TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as PUB_DATE 
                      FROM SYAIR s JOIN STATUS st ON s.syairId = st.syairId ORDER BY s.syairId DESC";
        $reportPoems = fetchAll($sqlReport);
    } else {
        $sqlReport = "SELECT s.syairId, s.title, s.content, s.author, st.currentStatus, TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as PUB_DATE 
                      FROM SYAIR s JOIN STATUS st ON s.syairId = st.syairId 
                      WHERE st.currentStatus = :status ORDER BY s.syairId DESC";
        $reportPoems = fetchAll($sqlReport, [':status' => $reportFilter]);
    }

    $userList = fetchAll("SELECT userId, name, email, role, username, TO_CHAR(created_at, 'DD-MM-YYYY') as JOINED_DATE 
                          FROM BASE_USER ORDER BY userId DESC");
} catch (Exception $e) {
    $swal = ['icon' => 'error', 'title' => 'Database Error', 'text' => $e->getMessage()];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Syair Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            overflow-y: auto;
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
            text-align: center;
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
            justify-content: flex-end;
            align-items: center;
            padding: 0 40px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.03);
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            font-weight: 500;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #eee;
        }

        /* --- CONTENT CARDS (Tables) --- */
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border: 1px solid #eee;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
        }

        /* --- TABLES --- */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            color: #888;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 15px 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        td {
            padding: 15px 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
            color: #444;
            vertical-align: middle;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: #fff8e1;
            color: #ffa000;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-rejected {
            background: #ffebee;
            color: #c62828;
        }

        /* --- BUTTONS --- */
        .btn {
            padding: 8px 15px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            margin-right: 5px;
            color: #fff;
            font-weight: 500;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.2);
        }

        .btn-green {
            background: #2e7d32;
        }

        .btn-red {
            background: #c62828;
        }

        .btn-blue {
            background: #1565c0;
        }

        /* --- FORMS & INPUTS --- */
        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-sizing: border-box;
            background: #f9f9f9;
            outline: none;
            transition: 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        input:focus,
        select:focus,
        textarea:focus {
            background: #fff;
            border-color: var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #555;
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
            width: 500px;
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

        /* View Poem Specifics */
        #v_title {
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0 0 10px;
            border-bottom: 2px solid var(--accent);
            display: inline-block;
        }

        #v_content {
            font-family: 'Georgia', serif;
            font-size: 1.1rem;
            line-height: 1.8;
            color: #444;
            background: #fafafa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            white-space: pre-wrap;
            margin-top: 20px;
        }

        .content-cell {
            max-width: 300px;
            max-height: 80px;
            overflow-y: auto;
            display: block;
            font-size: 0.85rem;
            color: #666;
            white-space: pre-wrap;
        }

        @media print {

            .sidebar,
            .topbar,
            .btn,
            .no-print,
            .stats-grid,
            #pending,
            #poems,
            #users,
            .alert,
            form.filter-form {
                display: none !important;
            }

            .main {
                margin: 0;
                width: 100%;
            }

            .content {
                padding: 0;
            }

            .card {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
            }

            #reports {
                display: block !important;
            }

            table {
                width: 100%;
                border: 1px solid #ddd;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 10px;
                color: black;
            }

            body {
                background: white;
                font-size: 12pt;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .content-cell {
                max-width: none;
                max-height: none;
                overflow: visible;
                display: block;
            }
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-user-shield"></i>
            <span>Admin Panel</span>
        </div>
        <div class="menu">
            <a href="#dashboard" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="#pending"><i class="fas fa-inbox"></i> Approvals <?php if ($countPending > 0) echo "<span style='background:var(--accent); color:var(--primary); padding:2px 6px; border-radius:4px; font-size:0.7rem; margin-left:auto;'>$countPending</span>"; ?></a>
            <a href="#poems"><i class="fas fa-scroll"></i> Manage Poems</a>
            <a href="#users"><i class="fas fa-users"></i> Users</a>
            <a href="#reports"><i class="fas fa-file-alt"></i> Reports</a>

            <a href="?logout=1" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="user-profile">
                <div style="text-align: right;">
                    <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars(ucfirst($_SESSION['username'])); ?></div>
                    <div style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
            </div>
        </div>

        <div class="content">

            <div id="dashboard" class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $countUsers; ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $countSyair; ?></h3>
                        <p>Total Poems</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-scroll"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $countPending; ?></h3>
                        <p>Pending</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $countApproved; ?></h3>
                        <p>Approved</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>

            <div class="card" id="pending">
                <h3>Pending Approvals</h3>
                <?php if (count($pendingList) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            foreach ($pendingList as $row):
                                $content = is_object($row['CONTENT']) ? $row['CONTENT']->load() : $row['CONTENT'];
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>#<?php echo $row['SYAIRID']; ?></td>
                                    <td>
                                        <input type="hidden" id="pending_content_<?php echo $row['SYAIRID']; ?>" value="<?php echo htmlspecialchars($content); ?>">

                                        <a href="javascript:void(0);" style="color:var(--primary); font-weight:600; text-decoration:none;"
                                            onclick="viewPoem('<?php echo htmlspecialchars($row['TITLE'], ENT_QUOTES); ?>', document.getElementById('pending_content_<?php echo $row['SYAIRID']; ?>').value)">
                                            <?php echo htmlspecialchars($row['TITLE']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['AUTHOR']); ?></td>
                                    <td><?php echo $row['PUB_DATE']; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action_type" value="update_status">
                                            <input type="hidden" name="syair_id" value="<?php echo $row['SYAIRID']; ?>">
                                            <button name="status" value="Approved" class="btn btn-green"><i class="fa fa-check"></i></button>
                                            <button name="status" value="Rejected" class="btn btn-red"><i class="fa fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?><p>No pending approvals.</p><?php endif; ?>
            </div>

            <div class="card" id="poems">
                <div class="section-header">
                    <span class="section-title">Manage Poems</span>
                    <button onclick="document.getElementById('addPoemModal').style.display='block'" class="btn btn-primary">+ Add Poem</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Theme</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1;
                        foreach ($managePoemsList as $row):
                            $content = is_object($row['CONTENT']) ? $row['CONTENT']->load() : $row['CONTENT'];
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>#<?php echo $row['SYAIRID']; ?></td>
                                <td>
                                    <input type="hidden" id="content_<?php echo $row['SYAIRID']; ?>" value="<?php echo htmlspecialchars($content); ?>">
                                    <a href="javascript:void(0);" style="color:var(--primary); font-weight:600; text-decoration:none;"
                                        onclick="viewPoem('<?php echo htmlspecialchars($row['TITLE'], ENT_QUOTES); ?>', `<?php echo addslashes($content); ?>`)">
                                        <?php echo htmlspecialchars($row['TITLE']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($row['THEME']); ?></td>
                                <td><?php echo htmlspecialchars($row['AUTHOR']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo match ($row['CURRENTSTATUS']) {
                                                                    'Approved' => 'status-approved',
                                                                    'Rejected' => 'status-rejected',
                                                                    'Pending'  => 'status-pending',
                                                                    default    => 'status-pending'
                                                                }; ?>">
                                        <?php echo $row['CURRENTSTATUS']; ?>
                                    </span>
                                </td>

                                <td style="white-space: nowrap;">
                                    <form method="POST" style="display:inline-block; margin-right: 5px;">
                                        <input type="hidden" name="action_type" value="update_status">
                                        <input type="hidden" name="syair_id" value="<?php echo $row['SYAIRID']; ?>">

                                        <select name="status" onchange="this.form.submit()"
                                            style="padding: 6px; border-radius: 6px; border: 1px solid #ddd; font-size: 0.85rem; cursor: pointer; background: #fff;">
                                            <option value="Pending" <?php if ($row['CURRENTSTATUS'] == 'Pending')  echo 'selected'; ?>>Pending</option>
                                            <option value="Approved" <?php if ($row['CURRENTSTATUS'] == 'Approved') echo 'selected'; ?>>Approved</option>
                                            <option value="Rejected" <?php if ($row['CURRENTSTATUS'] == 'Rejected') echo 'selected'; ?>>Rejected</option>
                                        </select>
                                    </form>

                                    <button class="btn btn-blue" style="padding: 7px 10px;" onclick="openEditPoem('<?php echo $row['SYAIRID']; ?>','<?php echo htmlspecialchars($row['TITLE'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($row['THEME'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($row['AUTHOR'], ENT_QUOTES); ?>','<?php echo $row['PUB_DATE']; ?>',`<?php echo addslashes($content); ?>`)">
                                        <i class="fa fa-pen"></i>
                                    </button>

                                    <form method="POST" style="display:inline;" id="del_poem_<?php echo $row['SYAIRID']; ?>">
                                        <input type="hidden" name="action_type" value="delete_syair">
                                        <input type="hidden" name="delete_syair_id" value="<?php echo $row['SYAIRID']; ?>">
                                        <button type="button" class="btn btn-red" style="padding: 7px 10px;" onclick="confirmDelete('del_poem_<?php echo $row['SYAIRID']; ?>')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" id="users">
                <div class="section-header">
                    <span class="section-title">User Management</span>
                    <button onclick="document.getElementById('addUserModal').style.display='block'" class="btn btn-primary">+ Add User</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1;
                        foreach ($userList as $u): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>#<?php echo $u['USERID']; ?></td>
                                <td><?php echo htmlspecialchars($u['NAME']); ?></td>
                                <td><?php echo htmlspecialchars($u['EMAIL']); ?></td>
                                <td><?php echo $u['ROLE']; ?></td>
                                <td><?php echo $u['JOINED_DATE']; ?></td>
                                <td>
                                    <button class="btn btn-blue" onclick="openEditUser('<?php echo $u['USERID']; ?>','<?php echo $u['NAME']; ?>','<?php echo $u['EMAIL']; ?>','<?php echo $u['ROLE']; ?>')"><i class="fa fa-pen"></i></button>
                                    <form method="POST" style="display:inline;" id="del_user_<?php echo $u['USERID']; ?>">
                                        <input type="hidden" name="action_type" value="delete_user">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $u['USERID']; ?>">
                                        <button type="button" class="btn btn-red" onclick="confirmDelete('del_user_<?php echo $u['USERID']; ?>')"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" id="reports">
                <div class="section-header">
                    <div>
                        <span class="section-title">System Report</span><br>
                        <small style="color:#888;">Generated on <?php echo date('d M Y, H:i'); ?> by <?php echo ucfirst($_SESSION['username']); ?></small>
                    </div>

                    <form method="GET" action="#reports" class="filter-form" style="display:flex; gap:10px; align-items:center;">
                        <select name="report_type" onchange="this.form.submit()" style="width:auto;">
                            <option value="poems" <?php if ($reportType == 'poems') echo 'selected'; ?>>Poem Collection</option>
                            <option value="users" <?php if ($reportType == 'users') echo 'selected'; ?>>User List</option>
                        </select>

                        <?php if ($reportType == 'poems'): ?>
                            <select name="filter" onchange="this.form.submit()" style="width:auto;">
                                <option value="All" <?php if ($reportFilter == 'All') echo 'selected'; ?>>All Status</option>
                                <option value="Approved" <?php if ($reportFilter == 'Approved') echo 'selected'; ?>>Approved Only</option>
                                <option value="Rejected" <?php if ($reportFilter == 'Rejected') echo 'selected'; ?>>Rejected Only</option>
                                <option value="Pending" <?php if ($reportFilter == 'Pending') echo 'selected'; ?>>Pending Only</option>
                            </select>
                        <?php endif; ?>

                        <button type="button" onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> Print</button>
                    </form>
                </div>

                <?php if ($reportType == 'poems'): ?>
                    <h4 class="no-print" style="margin-bottom:15px; color:#555;">Poem Collection Report (<?php echo $reportFilter; ?>)</h4>
                    <table>
                        <thead>
                            <tr style="background:#f9f9f9;">
                                <th>#</th>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Theme</th>
                                <th>Author</th>
                                <th>Content (Excerpt)</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1;
                            foreach ($reportPoems as $row): $content = is_object($row['CONTENT']) ? $row['CONTENT']->load() : $row['CONTENT']; ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>#<?php echo $row['SYAIRID']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['TITLE']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['THEME']); ?></td>
                                    <td><?php echo htmlspecialchars($row['AUTHOR']); ?></td>
                                    <td>
                                        <div class="content-cell"><?php echo htmlspecialchars($content); ?></div>
                                    </td>
                                    <td><?php echo $row['PUB_DATE']; ?></td>
                                    <td><?php echo $row['CURRENTSTATUS']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <h4 class="no-print" style="margin-bottom:15px; color:#555;">Registered Users Report</h4>
                    <table>
                        <thead>
                            <tr style="background:#f9f9f9;">
                                <th>#</th>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1;
                            foreach ($userList as $u): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>#<?php echo $u['USERID']; ?></td>
                                    <td><?php echo htmlspecialchars($u['NAME']); ?></td>
                                    <td><?php echo htmlspecialchars($u['USERNAME']); ?></td>
                                    <td><?php echo htmlspecialchars($u['EMAIL']); ?></td>
                                    <td><?php echo $u['ROLE']; ?></td>
                                    <td><?php echo $u['JOINED_DATE']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="margin-top:50px; text-align:center; display:none;" class="show-print">
                    <p>*** End of Report ***</p>
                </div>
            </div>

        </div>
    </div>

    <div id="addPoemModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>Add New Poem</h3>
            <form method="POST">
                <input type="hidden" name="action_type" value="add_syair">

                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Title</label>
                    <input type="text" name="title" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-palette"></i> Theme</label>
                    <select name="theme" id="theme_option" required style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; background: #f9f9f9;">
                        <option value="">-- Select a theme --</option>
                        <option value="Love">Love & Romance</option>
                        <option value="Religion">Religion & Spirituality</option>
                        <option value="Advice">Advice & Wisdom</option>
                        <option value="History">History & Heritage</option>
                        <option value="Nature">Nature & Environment</option>
                        <option value="Social">Social Issues</option>
                        <option value="Patriotism">Patriotism</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user-pen"></i> Author</label>
                    <input type="text" name="author" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Date</label>
                    <input type="date" name="publish_date" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-scroll"></i> Content</label>
                    <textarea name="content" rows="6" required></textarea>
                </div>

                <button class="btn btn-primary" style="width:100%"><i class="fas fa-paper-plane"></i> Post</button>
            </form>
        </div>
    </div>

    <div id="editPoemModal" class="modal">
        <div class="modal-content"><span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>Edit Poem</h3>
            <form method="POST"><input type="hidden" name="action_type" value="edit_syair"><input type="hidden" name="edit_syair_id" id="ep_id">
                <div class="form-group"><label>Title</label><input type="text" name="edit_title" id="ep_title" required></div>
                <div class="form-group"><label>Theme</label><input type="text" name="edit_theme" id="ep_theme" required></div>
                <div class="form-group"><label>Author</label><input type="text" name="edit_author" id="ep_author" required></div>
                <div class="form-group"><label>Date</label><input type="date" name="edit_date" id="ep_date" required></div>
                <div class="form-group"><label>Content</label><textarea name="edit_content" id="ep_content" rows="6" required></textarea></div><button class="btn btn-primary" style="width:100%">Update</button>
            </form>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content"><span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h2 id="v_title"></h2>
            <div id="v_content"></div>
        </div>
    </div>

    <div id="addUserModal" class="modal">
        <div class="modal-content"><span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>Add User</h3>
            <form method="POST"><input type="hidden" name="action_type" value="add_user">
                <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                <div class="form-group"><label>Role</label><select name="role">
                        <option>Users</option>
                        <option>Admin</option>
                    </select></div><button class="btn btn-primary" style="width:100%">Create</button>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal">
        <div class="modal-content"><span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>Edit User</h3>
            <form method="POST"><input type="hidden" name="action_type" value="edit_user"><input type="hidden" name="edit_user_id" id="eu_id">
                <div class="form-group"><label>Name</label><input type="text" name="edit_name" id="eu_name" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="edit_email" id="eu_email" required></div>
                <div class="form-group"><label>Role</label><select name="edit_role" id="eu_role">
                        <option>Users</option>
                        <option>Admin</option>
                    </select></div><button class="btn btn-primary" style="width:100%">Update</button>
            </form>
        </div>
    </div>

    <script>
        function viewPoem(t, c) {
            document.getElementById('v_title').innerText = t;
            document.getElementById('v_content').innerText = c;
            document.getElementById('viewModal').style.display = 'block';
        }

        function openEditPoem(i, t, th, a, d, c) {
            document.getElementById('ep_id').value = i;
            document.getElementById('ep_title').value = t;
            document.getElementById('ep_theme').value = th;
            document.getElementById('ep_author').value = a;
            document.getElementById('ep_date').value = d;
            document.getElementById('ep_content').value = c;
            document.getElementById('editPoemModal').style.display = 'block';
        }

        function openEditUser(i, n, e, r) {
            document.getElementById('eu_id').value = i;
            document.getElementById('eu_name').value = n;
            document.getElementById('eu_email').value = e;
            document.getElementById('eu_role').value = r;
            document.getElementById('editUserModal').style.display = 'block';
        }
        window.onclick = function(e) {
            if (e.target.className == 'modal') e.target.style.display = "none";
        }

        function confirmDelete(formId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f64e60',
                cancelButtonColor: '#3699ff',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            })
        }
    </script>

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