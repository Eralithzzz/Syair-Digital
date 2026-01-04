<?php
session_start();
require_once 'oracle.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$swal = null;
$error = '';
$syairs = [];       // Public Feed
$myCollection = []; // User's Own Poems

// --- 2. BACKEND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. SUBMIT POEM
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'submit_syair') {
        try {
            $idRes = fetchAll("SELECT syair_seq.NEXTVAL AS NEXT_ID FROM DUAL");
            $new_sid = $idRes[0]['NEXT_ID'];

            $sqlSyair = "INSERT INTO SYAIR (syairId, title, content, author, yearPublish, userId) 
                         VALUES (:sid, :title, :content, :author, TO_DATE(:pdate, 'YYYY-MM-DD'), :submitter_id)";

            executeQuery($sqlSyair, [
                ':sid'          => $new_sid,
                ':title'        => $_POST['title'],
                ':content'      => $_POST['content'],
                ':author'       => $_POST['author'],
                ':pdate'        => $_POST['publish_date'],
                ':submitter_id' => $_SESSION['user_id']
            ]);

            // Default status: Pending
            $sqlStatus = "INSERT INTO STATUS (statusId, syairId, currentStatus, updated_by, status_date)
                          VALUES (status_seq.NEXTVAL, :sid, 'Pending', NULL, SYSTIMESTAMP)";
            executeQuery($sqlStatus, [':sid' => $new_sid]);

            $swal = ['icon' => 'success', 'title' => 'Submitted!', 'text' => 'Your poem has been sent for admin approval.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
        }
    }

    // B. EDIT POEM (USER)
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'edit_user_syair') {
        try {
            $sqlEdit = "UPDATE SYAIR SET title = :title, content = :content, author = :author, yearPublish = TO_DATE(:pdate, 'YYYY-MM-DD') 
                        WHERE syairId = :sid AND userId = :current_user";

            executeQuery($sqlEdit, [
                ':title'        => $_POST['edit_title'],
                ':content'      => $_POST['edit_content'],
                ':author'       => $_POST['edit_author'],
                ':pdate'        => $_POST['edit_date'],
                ':sid'          => $_POST['edit_syair_id'],
                ':current_user' => $_SESSION['user_id']
            ]);

            // Reset Status to 'Pending'
            $sqlUpdateStatus = "UPDATE STATUS SET currentStatus = 'Pending', status_date = SYSTIMESTAMP WHERE syairId = :sid";
            executeQuery($sqlUpdateStatus, [':sid' => $_POST['edit_syair_id']]);

            $swal = ['icon' => 'success', 'title' => 'Updated!', 'text' => 'Your changes have been saved and sent for re-approval.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Update Failed', 'text' => $e->getMessage()];
        }
    }

    // C. DELETE POEM (USER)
    if (isset($_POST['action_type']) && $_POST['action_type'] == 'delete_user_syair') {
        try {
            $sid = $_POST['delete_syair_id'];
            $uid = $_SESSION['user_id'];

            // Clean up dependencies first
            executeQuery("DELETE FROM BOOKMARK WHERE syairId = :sid", [':sid' => $sid]);
            executeQuery("DELETE FROM LIKED WHERE syairId = :sid", [':sid' => $sid]);
            executeQuery("DELETE FROM STATUS WHERE syairId = :sid", [':sid' => $sid]);

            // Delete Poem
            executeQuery("DELETE FROM SYAIR WHERE syairId = :sid AND userId = :current_user", [':sid' => $sid, ':current_user' => $uid]);

            $swal = ['icon' => 'success', 'title' => 'Deleted', 'text' => 'Poem removed from your collection.'];
        } catch (Exception $e) {
            $swal = ['icon' => 'error', 'title' => 'Delete Failed', 'text' => $e->getMessage()];
        }
    }
}

// --- 3. DATA FETCHING ---
try {
    // 1. PUBLIC FEED (Approved Only)
    $sqlFeed = "SELECT s.syairId, s.title, s.author, s.content, TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as YEARPUBLISH, 
                   TO_CHAR(s.created_at, 'DD MON YYYY') as DATE_CREATED
                FROM SYAIR s
                JOIN STATUS st ON s.syairId = st.syairId
                WHERE st.currentStatus = 'Approved'
                ORDER BY s.created_at DESC
                FETCH FIRST 20 ROWS ONLY";
    $feedStmt = oci_parse($db_conn, $sqlFeed);
    oci_execute($feedStmt);
    while ($row = oci_fetch_assoc($feedStmt)) {
        $syairs[] = $row;
    }

    // 2. MY COLLECTION (ALL STATUSES)
    // Used LEFT JOIN so even if status is missing, the poem still shows up
    $sqlMy = "SELECT s.syairId, s.title, s.author, s.content, st.currentStatus, 
                     TO_CHAR(s.yearPublish, 'YYYY-MM-DD') as YEARPUBLISH, 
                     TO_CHAR(s.created_at, 'DD MON YYYY') as DATE_CREATED
              FROM SYAIR s
              LEFT JOIN STATUS st ON s.syairId = st.syairId
              WHERE s.userId = :current_user
              ORDER BY s.created_at DESC";

    $myStmt = oci_parse($db_conn, $sqlMy);
    oci_bind_by_name($myStmt, ':current_user', $_SESSION['user_id']);
    oci_execute($myStmt);
    while ($row = oci_fetch_assoc($myStmt)) {
        $myCollection[] = $row;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #800000;
            --accent: #ffeb3b;
            --bg: #f8f9fa;
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

        /* Sidebar */
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
        }

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

        /* Main */
        .main {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
        }

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

        /* Stats */
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

        /* Cards */
        .syair-section {
            margin-bottom: 50px;
        }

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
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }

        .btn-add:hover {
            background: #600000;
            transform: translateY(-2px);
        }

        .syair-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            position: relative;
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

        /* Badges & Actions */
        .badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-pending {
            background: #fff8e1;
            color: #ffa000;
        }

        .badge-rejected {
            background: #ffebee;
            color: #c62828;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
        }

        .btn-icon {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .btn-edit {
            background: #e3f2fd;
            color: #1565c0;
        }

        .btn-edit:hover {
            background: #1565c0;
            color: white;
        }

        .btn-delete {
            background: #ffebee;
            color: #c62828;
        }

        .btn-delete:hover {
            background: #c62828;
            color: white;
        }

        /* Modals */
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            background: #f9f9f9;
        }

        input:focus,
        textarea:focus {
            border-color: var(--primary);
            background: #fff;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.3s;
        }

        .btn-submit:hover {
            background: #600000;
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="logo"><i class="fas fa-scroll"></i><span>Syair Digital</span></div>
        <div class="menu">
            <a href="#" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="#my-collection"><i class="fas fa-layer-group"></i> My Collection</a>
            <a href="?logout=1" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="welcome-text">
                <h2>Welcome, <?php echo htmlspecialchars(ucfirst($_SESSION['username'])); ?></h2>
                <p>Explore and contribute to the Malay poetry heritage.</p>
            </div>
            <div class="user-profile">
                <div style="text-align: right;">
                    <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars(ucfirst($_SESSION['username'])); ?></div>
                    <div style="font-size: 0.75rem; color: #888;">Student</div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
            </div>
        </div>

        <div class="content">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo count($syairs); ?></h3>
                        <p>Public Poems</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo count($myCollection); ?></h3>
                        <p>My Submissions</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-feather-alt"></i></div>
                </div>
            </div>

            <div class="syair-section">
                <div class="section-header">
                    <div class="section-title">Latest Collection</div>
                    <button onclick="openModal('submitModal')" class="btn-add"><i class="fas fa-plus"></i> Submit Poem</button>
                </div>

                <?php if (count($syairs) > 0): ?>
                    <div class="syair-grid">
                        <?php foreach ($syairs as $syair):
                            $content = is_object($syair['CONTENT']) ? $syair['CONTENT']->load() : $syair['CONTENT'];
                        ?>
                            <div class="syair-card">
                                <div class="card-icon"><i class="fas fa-book"></i></div>
                                <div class="syair-title"><?php echo htmlspecialchars($syair['TITLE']); ?></div>
                                <div class="syair-meta">
                                    <span><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($syair['AUTHOR']); ?></span>
                                    <span><?php echo $syair['YEARPUBLISH']; ?></span>
                                </div>
                                <div class="syair-preview"><?php echo htmlspecialchars(substr($content, 0, 100)) . '...'; ?></div>

                                <textarea id="content_<?php echo $syair['SYAIRID']; ?>" style="display:none;"><?php echo htmlspecialchars($content); ?></textarea>
                                <button class="btn-read" onclick="viewPoem('<?php echo htmlspecialchars($syair['TITLE'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($syair['AUTHOR'], ENT_QUOTES); ?>', document.getElementById('content_<?php echo $syair['SYAIRID']; ?>').value)">Read Full Poem</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:50px; color:#999;">
                        <p>No poems available yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="syair-section" id="my-collection">
                <div class="section-header">
                    <div class="section-title">My Collection <small style="font-size:0.9rem; font-weight:400; color:#777;">(All Statuses)</small></div>
                </div>

                <?php if (count($myCollection) > 0): ?>
                    <div class="syair-grid">
                        <?php foreach ($myCollection as $myRow):
                            $myContent = is_object($myRow['CONTENT']) ? $myRow['CONTENT']->load() : $myRow['CONTENT'];
                            // Status handling with Fallback
                            $status = $myRow['CURRENTSTATUS'] ?? 'Pending';
                            $statusClass = match ($status) {
                                'Approved' => 'badge-approved',
                                'Rejected' => 'badge-rejected',
                                default => 'badge-pending'
                            };
                        ?>
                            <div class="syair-card" style="border-left: 4px solid var(--primary);">
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                <div class="syair-title" style="margin-top:20px;"><?php echo htmlspecialchars($myRow['TITLE']); ?></div>
                                <div class="syair-meta">
                                    <span><?php echo $myRow['DATE_CREATED']; ?></span>
                                </div>
                                <div class="syair-preview"><?php echo htmlspecialchars(substr($myContent, 0, 80)) . '...'; ?></div>

                                <textarea id="my_content_<?php echo $myRow['SYAIRID']; ?>" style="display:none;"><?php echo htmlspecialchars($myContent); ?></textarea>

                                <button class="btn-read" onclick="viewPoem('<?php echo htmlspecialchars($myRow['TITLE'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($myRow['AUTHOR'], ENT_QUOTES); ?>', document.getElementById('my_content_<?php echo $myRow['SYAIRID']; ?>').value)" style="margin-bottom:10px;">View</button>

                                <div class="card-actions">
                                    <button class="btn-icon btn-edit" onclick="openEditModal(
                                        '<?php echo $myRow['SYAIRID']; ?>',
                                        '<?php echo htmlspecialchars($myRow['TITLE'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($myRow['AUTHOR'], ENT_QUOTES); ?>',
                                        '<?php echo $myRow['YEARPUBLISH']; ?>',
                                        document.getElementById('my_content_<?php echo $myRow['SYAIRID']; ?>').value
                                    )"><i class="fas fa-pen"></i> Edit</button>

                                    <form method="POST" style="flex:1;" onsubmit="return confirm('Delete this poem?');">
                                        <input type="hidden" name="action_type" value="delete_user_syair">
                                        <input type="hidden" name="delete_syair_id" value="<?php echo $myRow['SYAIRID']; ?>">
                                        <button class="btn-icon btn-delete" style="width:100%"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:30px; color:#999; border: 2px dashed #ddd; border-radius:10px;">
                        <p>You haven't submitted any poems yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content"><span class="close" onclick="closeModal('viewModal')">&times;</span>
            <div class="modal-title" id="m_title"></div>
            <div class="modal-author">Written by <span id="m_author"></span></div>
            <div class="modal-body" id="m_body"></div>
        </div>
    </div>

    <div id="submitModal" class="modal">
        <div class="modal-content"><span class="close" onclick="closeModal('submitModal')">&times;</span>
            <h2 style="color:var(--primary); margin-bottom:20px;">Submit Poem</h2>
            <form method="POST"><input type="hidden" name="action_type" value="submit_syair">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Author / Pen Name</label><input type="text" name="author" required value="<?php echo htmlspecialchars($_SESSION['username']); ?>"></div>
                <div class="form-group"><label>Date Written</label><input type="date" name="publish_date" required></div>
                <div class="form-group"><label>Content</label><textarea name="content" rows="8" required></textarea></div>
                <button type="submit" class="btn-submit">Submit for Review</button>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal">
        <div class="modal-content"><span class="close" onclick="closeModal('editUserModal')">&times;</span>
            <h2 style="color:var(--primary); margin-bottom:20px;">Edit Poem</h2>
            <p style="color:#c62828; font-size:0.85rem; margin-bottom:15px;">Note: Editing will reset status to <b>Pending</b> for re-approval.</p>
            <form method="POST"><input type="hidden" name="action_type" value="edit_user_syair"><input type="hidden" name="edit_syair_id" id="eu_id">
                <div class="form-group"><label>Title</label><input type="text" name="edit_title" id="eu_title" required></div>
                <div class="form-group"><label>Author</label><input type="text" name="edit_author" id="eu_author" required></div>
                <div class="form-group"><label>Date</label><input type="date" name="edit_date" id="eu_date" required></div>
                <div class="form-group"><label>Content</label><textarea name="edit_content" id="eu_content" rows="8" required></textarea></div>
                <button type="submit" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = "block";
        }

        function closeModal(id) {
            document.getElementById(id).style.display = "none";
        }

        function viewPoem(t, a, c) {
            document.getElementById('m_title').innerText = t;
            document.getElementById('m_author').innerText = a;
            document.getElementById('m_body').innerText = c;
            openModal('viewModal');
        }

        function openEditModal(id, title, author, date, content) {
            document.getElementById('eu_id').value = id;
            document.getElementById('eu_title').value = title;
            document.getElementById('eu_author').value = author;
            document.getElementById('eu_date').value = date;
            document.getElementById('eu_content').value = content;
            openModal('editUserModal');
        }

        window.onclick = function(e) {
            if (e.target.className === 'modal') e.target.style.display = "none";
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