<?php
session_start();
require_once 'oracle.php';

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

$message = "";
$error = "";

// --- 2. BACKEND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. ADD POEM
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'add_syair') {
        try {
            $idRes = fetchAll("SELECT syair_seq.NEXTVAL AS NEXT_ID FROM DUAL");
            $new_sid = $idRes[0]['NEXT_ID'];

            $sqlSyair = "INSERT INTO SYAIR (syairId, title, content, author, yearPublish, userId) 
                         VALUES (:sid, :title, :content, :author, TO_DATE(:pdate, 'YYYY-MM-DD'), :uid)";

            executeQuery($sqlSyair, [
                ':sid'     => $new_sid,
                ':title'   => $_POST['title'],
                ':content' => $_POST['content'],
                ':author'  => $_POST['author'],
                ':pdate'   => $_POST['publish_date'],
                ':uid'     => $_SESSION['user_id']
            ]);

            $sqlStatus = "INSERT INTO STATUS (statusId, syairId, currentStatus, updated_by, status_date)
                          VALUES (status_seq.NEXTVAL, :sid, 'Approved', :uid, SYSTIMESTAMP)";
            executeQuery($sqlStatus, [':sid' => $new_sid, ':uid' => $_SESSION['user_id']]);

            $message = "New poem added successfully.";
        } catch (Exception $e) {
            $error = "Failed to add poem: " . $e->getMessage();
        }
    }

    // B. EDIT POEM
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'edit_syair') {
        try {
            $sqlEdit = "UPDATE SYAIR SET title = :title, content = :content, author = :author, yearPublish = TO_DATE(:pdate, 'YYYY-MM-DD') 
                        WHERE syairId = :sid";
            executeQuery($sqlEdit, [
                ':title'   => $_POST['edit_title'],
                ':content' => $_POST['edit_content'],
                ':author'  => $_POST['edit_author'],
                ':pdate'   => $_POST['edit_date'],
                ':sid'     => $_POST['edit_syair_id']
            ]);
            $message = "Poem updated successfully.";
        } catch (Exception $e) {
            $error = "Failed to update: " . $e->getMessage();
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
            $message = "Poem deleted successfully.";
        } catch (Exception $e) {
            $error = "Delete failed: " . $e->getMessage();
        }
    }

    // D. APPROVE/REJECT
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'update_status') {
        try {
            $sql = "UPDATE STATUS SET currentStatus = :status, updated_by = :adminId, status_date = SYSTIMESTAMP WHERE syairId = :syairId";
            executeQuery($sql, [':status' => $_POST['status'], ':adminId' => $_SESSION['user_id'], ':syairId' => $_POST['syair_id']]);
            $message = "Status updated.";
        } catch (Exception $e) {
            $error = $e->getMessage();
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
            $message = "User added.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // F. EDIT USER
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'edit_user') {
        try {
            executeQuery(
                "UPDATE BASE_USER SET name=:nm, email=:em, role=:rl WHERE userId=:edit_id",
                [':nm' => $_POST['edit_name'], ':em' => $_POST['edit_email'], ':rl' => $_POST['edit_role'], ':edit_id' => $_POST['edit_user_id']]
            );
            $message = "User updated.";
        } catch (Exception $e) {
            $error = $e->getMessage();
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
            $message = "User deleted.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// --- 3. FETCH DATA ---
try {
    $countUsers = fetchAll("SELECT COUNT(*) AS CNT FROM BASE_USER")[0]['CNT'];
    $countSyair = fetchAll("SELECT COUNT(*) AS CNT FROM SYAIR")[0]['CNT'];
    $countPending = fetchAll("SELECT COUNT(*) AS CNT FROM STATUS WHERE currentStatus = 'Pending'")[0]['CNT'];
    $countApproved = fetchAll("SELECT COUNT(*) AS CNT FROM STATUS WHERE currentStatus = 'Approved'")[0]['CNT'];
    $countRejected = fetchAll("SELECT COUNT(*) AS CNT FROM STATUS WHERE currentStatus = 'Rejected'")[0]['CNT'];

    $pendingList = fetchAll("SELECT s.syairId, s.title, s.content, b.name as author, TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as PUB_DATE 
                             FROM SYAIR s JOIN BASE_USER b ON s.userId = b.userId 
                             JOIN STATUS st ON s.syairId = st.syairId WHERE st.currentStatus = 'Pending'");

    $allPoemsList = fetchAll("SELECT s.syairId, s.title, s.content, b.name as author, st.currentStatus, TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as PUB_DATE 
                              FROM SYAIR s 
                              JOIN BASE_USER b ON s.userId = b.userId 
                              JOIN STATUS st ON s.syairId = st.syairId 
                              ORDER BY s.syairId DESC");

    $userList = fetchAll("SELECT userId, name, email, role, username FROM BASE_USER ORDER BY userId DESC");
} catch (Exception $e) {
    $error = "DB Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --sidebar-bg: #1e1e2d;
            --active: #3699ff;
            --bg: #f3f6f9;
            --text: #3f4254;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            margin: 0;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: #9899ac;
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
        }

        .sidebar a {
            color: #9899ac;
            text-decoration: none;
            padding: 12px 25px;
            display: flex;
            align-items: center;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            color: #fff;
            background: #1b1b28;
            border-left: 3px solid var(--active);
        }

        .sidebar i {
            width: 25px;
            margin-right: 10px;
            text-align: center;
        }

        .main {
            margin-left: 260px;
            width: calc(100% - 260px);
        }

        .topbar {
            height: 70px;
            background: #fff;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 0 30px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.02);
        }

        .avatar {
            width: 40px;
            height: 40px;
            background: #e1f0ff;
            color: var(--active);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 15px;
        }

        .content {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.02);
            margin-bottom: 30px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            float: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th {
            text-align: left;
            color: #b5b5c3;
            font-size: 0.8rem;
            text-transform: uppercase;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        td {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff4de;
            color: #ffa800;
        }

        .status-approved {
            background: #c9f7f5;
            color: #1bc5bd;
        }

        .status-rejected {
            background: #ffe2e5;
            color: #f64e60;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            margin-right: 5px;
            color: #fff;
        }

        .btn-green {
            background: #1bc5bd;
        }

        .btn-red {
            background: #f64e60;
        }

        .btn-blue {
            background: #3699ff;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: #fff;
            margin: 5% auto;
            padding: 25px;
            width: 500px;
            border-radius: 10px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            box-sizing: border-box;
            background: #f9f9f9;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #c9f7f5;
            color: #0bb7af;
        }

        .alert-error {
            background: #ffe2e5;
            color: #f64e60;
        }

        /* --- PRINT STYLES FOR REPORT --- */
        @media print {

            .sidebar,
            .topbar,
            .btn,
            .no-print,
            .stats-grid,
            #pending,
            #poems,
            #users,
            .alert {
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
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 10px;
            }

            body {
                background: white;
                font-size: 12pt;
            }
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <div style="padding:20px 25px; color:#fff; font-weight:bold; font-size:1.2rem;">SYAIR ADMIN</div>
        <a href="#dashboard" class="active"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
        <a href="#pending"><i class="fa-solid fa-inbox"></i> Approvals <?php if ($countPending > 0) echo "($countPending)"; ?></a>
        <a href="#poems"><i class="fa-solid fa-scroll"></i> Manage Poems</a>
        <a href="#users"><i class="fa-solid fa-users"></i> Users</a>
        <a href="#reports"><i class="fa-solid fa-file-alt"></i> Reports</a>
        <a href="?logout=1" style="margin-top:auto;"><i class="fa-solid fa-power-off"></i> Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <div style="text-align:right;">
                <strong><?php echo $_SESSION['username']; ?></strong><br>
                <small style="color:#b5b5c3;">Administrator</small>
            </div>
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
        </div>

        <div class="content">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

            <div id="dashboard" class="stats-grid">
                <div class="card">
                    <div class="stat-icon" style="background:#e1f0ff; color:#3699ff;"><i class="fa-solid fa-users"></i></div>
                    <h3><?php echo $countUsers; ?></h3>
                    <p>Users</p>
                </div>
                <div class="card">
                    <div class="stat-icon" style="background:#e1e9ff; color:#6993ff;"><i class="fa-solid fa-scroll"></i></div>
                    <h3><?php echo $countSyair; ?></h3>
                    <p>Poems</p>
                </div>
                <div class="card">
                    <div class="stat-icon" style="background:#fff4de; color:#ffa800;"><i class="fa-solid fa-clock"></i></div>
                    <h3><?php echo $countPending; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="card">
                    <div class="stat-icon" style="background:#c9f7f5; color:#1bc5bd;"><i class="fa-solid fa-check"></i></div>
                    <h3><?php echo $countApproved; ?></h3>
                    <p>Approved</p>
                </div>
            </div>

            <div class="card" id="pending">
                <h3>Pending Approvals</h3>
                <?php if (count($pendingList) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingList as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['TITLE']); ?></td>
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
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3>Manage Poems</h3>
                    <button onclick="document.getElementById('addPoemModal').style.display='block'" class="btn btn-blue">+ Add Poem</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allPoemsList as $row):
                            $content = is_object($row['CONTENT']) ? $row['CONTENT']->load() : $row['CONTENT'];
                        ?>
                            <tr>
                                <td>#<?php echo $row['SYAIRID']; ?></td>
                                <td>
                                    <a href="#" onclick="viewPoem('<?php echo htmlspecialchars($row['TITLE'], ENT_QUOTES); ?>', `<?php echo addslashes($content); ?>`)" style="color:#3699ff; font-weight:600;">
                                        <?php echo htmlspecialchars($row['TITLE']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($row['AUTHOR']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo match ($row['CURRENTSTATUS']) {
                                                                    'Approved' => 'status-approved',
                                                                    'Rejected' => 'status-rejected',
                                                                    default => 'status-pending'
                                                                }; ?>">
                                        <?php echo $row['CURRENTSTATUS']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-blue" onclick="openEditPoem('<?php echo $row['SYAIRID']; ?>','<?php echo htmlspecialchars($row['TITLE'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($row['AUTHOR'], ENT_QUOTES); ?>','<?php echo $row['PUB_DATE']; ?>',`<?php echo addslashes($content); ?>`)"><i class="fa fa-pen"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this poem?');">
                                        <input type="hidden" name="action_type" value="delete_syair">
                                        <input type="hidden" name="delete_syair_id" value="<?php echo $row['SYAIRID']; ?>">
                                        <button class="btn btn-red"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" id="users">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3>User Management</h3>
                    <button onclick="document.getElementById('addUserModal').style.display='block'" class="btn btn-blue">+ Add User</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userList as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['NAME']); ?></td>
                                <td><?php echo htmlspecialchars($u['EMAIL']); ?></td>
                                <td><?php echo $u['ROLE']; ?></td>
                                <td>
                                    <button class="btn btn-blue" onclick="openEditUser('<?php echo $u['USERID']; ?>','<?php echo $u['NAME']; ?>','<?php echo $u['EMAIL']; ?>','<?php echo $u['ROLE']; ?>')"><i class="fa fa-pen"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user?');">
                                        <input type="hidden" name="action_type" value="delete_user">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $u['USERID']; ?>">
                                        <button class="btn btn-red"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" id="reports">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
                    <div>
                        <h2 style="margin:0;">System Report: Syair Collection</h2>
                        <small>Generated on <?php echo date('Y-m-d H:i:s'); ?> by <?php echo $_SESSION['username']; ?></small>
                    </div>
                    <button onclick="window.print()" class="btn btn-blue no-print"><i class="fa fa-print"></i> Print Report</button>
                </div>

                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; margin-bottom:30px; text-align:center; background:#f9f9f9; padding:20px;">
                    <div>
                        <h1 style="margin:0; color:#3699ff;"><?php echo $countSyair; ?></h1><span>Total Poems</span>
                    </div>
                    <div>
                        <h1 style="margin:0; color:#1bc5bd;"><?php echo $countApproved; ?></h1><span>Published</span>
                    </div>
                    <div>
                        <h1 style="margin:0; color:#ffa800;"><?php echo $countPending; ?></h1><span>Pending Review</span>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr style="background:#eee;">
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Publish Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allPoemsList as $row): ?>
                            <tr>
                                <td>#<?php echo $row['SYAIRID']; ?></td>
                                <td><?php echo htmlspecialchars($row['TITLE']); ?></td>
                                <td><?php echo htmlspecialchars($row['AUTHOR']); ?></td>
                                <td><?php echo $row['PUB_DATE']; ?></td>
                                <td><?php echo $row['CURRENTSTATUS']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top:50px; text-align:center; display:none;" class="show-print">
                    <p>*** End of Report ***</p>
                </div>
            </div>

        </div>
    </div>

    <div id="addPoemModal" class="modal">
        <div class="modal-content"><span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>Add New Poem</h3>
            <form method="POST"><input type="hidden" name="action_type" value="add_syair">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Author</label><input type="text" name="author" required></div>
                <div class="form-group"><label>Date</label><input type="date" name="publish_date" required></div>
                <div class="form-group"><label>Content</label><textarea name="content" rows="6" required></textarea></div><button class="btn btn-green" style="width:100%">Post</button>
            </form>
        </div>
    </div>

    <div id="editPoemModal" class="modal">
        <div class="modal-content"><span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>Edit Poem</h3>
            <form method="POST"><input type="hidden" name="action_type" value="edit_syair"><input type="hidden" name="edit_syair_id" id="ep_id">
                <div class="form-group"><label>Title</label><input type="text" name="edit_title" id="ep_title" required></div>
                <div class="form-group"><label>Author</label><input type="text" name="edit_author" id="ep_author" required></div>
                <div class="form-group"><label>Date</label><input type="date" name="edit_date" id="ep_date" required></div>
                <div class="form-group"><label>Content</label><textarea name="edit_content" id="ep_content" rows="6" required></textarea></div><button class="btn btn-blue" style="width:100%">Update</button>
            </form>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content"><span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h2 id="v_title"></h2>
            <div id="v_content" style="white-space: pre-line;"></div>
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
                    </select></div><button class="btn btn-green" style="width:100%">Create</button>
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
                    </select></div><button class="btn btn-blue" style="width:100%">Update</button>
            </form>
        </div>
    </div>

    <script>
        function viewPoem(t, c) {
            document.getElementById('v_title').innerText = t;
            document.getElementById('v_content').innerText = c;
            document.getElementById('viewModal').style.display = 'block';
        }

        function openEditPoem(i, t, a, d, c) {
            document.getElementById('ep_id').value = i;
            document.getElementById('ep_title').value = t;
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
    </script>

</body>

</html>