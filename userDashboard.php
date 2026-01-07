<?php
session_start();
require_once 'oracle.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email    = $_SESSION['email'];
$name     = $_SESSION['name'] ?? $username;

// ==========================
// FETCH USER DATA
// ==========================
$sql = "SELECT name, username, email FROM BASE_USER WHERE userId = :userId";
$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':userId', $userId);
oci_execute($stmt);
$user = oci_fetch_assoc($stmt);
if (!$user) $user = ['NAME' => $username, 'USERNAME' => $username, 'EMAIL' => $email];

// ==========================
// USER STATS
// ==========================
try {
    $stats = fetchAll("
        SELECT 
            (SELECT COUNT(*) FROM SYAIR s JOIN STATUS st ON s.syairId = st.syairId WHERE s.userId = :uid1) as my_poems,
            (SELECT COUNT(*) FROM BOOKMARK WHERE userId = :uid2) as bookmarks,
            (SELECT COUNT(*) FROM LIKED WHERE userId = :uid3) as likes,
            (SELECT COUNT(*) FROM SYAIR s JOIN STATUS st ON s.syairId = st.syairId WHERE s.userId = :uid4 AND st.currentStatus = 'Approved') as approved_poems
        FROM DUAL
    ", [
        ':uid1' => $userId,
        ':uid2' => $userId,
        ':uid3' => $userId,
        ':uid4' => $userId
    ])[0];
} catch (Exception $e) {
    $stats = ['MY_POEMS' => 0, 'BOOKMARKS' => 0, 'LIKES' => 0, 'APPROVED_POEMS' => 0];
}

// ==========================
// FETCH SYAIR DATA
// ==========================
$homepageSyair = fetchAll("
    SELECT s.syairId, s.title, s.author, s.theme, TO_CHAR(s.yearPublish,'YYYY') AS yearPublish,
        CASE WHEN EXISTS (SELECT 1 FROM LIKED l WHERE l.syairId = s.syairId AND l.userId = :userId) THEN 1 ELSE 0 END AS isLiked,
        CASE WHEN EXISTS (SELECT 1 FROM BOOKMARK b WHERE b.syairId = s.syairId AND b.userId = :userId) THEN 1 ELSE 0 END AS isBookmarked,
        (SELECT COUNT(*) FROM LIKED l2 WHERE l2.syairId = s.syairId) AS likeCount
    FROM SYAIR s
    JOIN STATUS st ON s.syairId = st.syairId
    WHERE st.currentStatus = 'Approved'
    ORDER BY st.status_date DESC
", [':userId' => $userId]);


$mySyair = fetchAll("
    SELECT s.syairId, s.title, s.author, s.theme, TO_CHAR(s.yearPublish,'YYYY') AS yearPublish, st.currentStatus
    FROM SYAIR s
    JOIN STATUS st ON s.syairId = st.syairId
    WHERE s.userId = :userId
    ORDER BY st.status_date DESC
", [':userId' => $userId]);

$bookmarkedSyair = fetchAll("
    SELECT s.syairId, s.title, s.author, s.theme, TO_CHAR(s.yearPublish,'YYYY') AS yearPublish
    FROM SYAIR s
    JOIN BOOKMARK b ON s.syairId = b.syairId
    WHERE b.userId = :userId
    ORDER BY b.dateBookmark DESC
", [':userId' => $userId]);

$likedSyair = fetchAll("
    SELECT s.syairId, s.title, s.author, s.theme, TO_CHAR(s.yearPublish,'YYYY') AS yearPublish
    FROM SYAIR s
    JOIN LIKED l ON s.syairId = l.syairId
    WHERE l.userId = :userId
    ORDER BY l.dateLikes DESC
", [':userId' => $userId]);

$recentPoems = fetchAll("
    SELECT s.syairId, s.title, s.author, b.name as added_by, TO_CHAR(s.yearPublish,'YYYY') AS yearPublish,
        CASE WHEN EXISTS (SELECT 1 FROM LIKED l WHERE l.syairId = s.syairId AND l.userId = :userId) THEN 1 ELSE 0 END AS ISLIKED,
        CASE WHEN EXISTS (SELECT 1 FROM BOOKMARK b WHERE b.syairId = s.syairId AND b.userId = :userId) THEN 1 ELSE 0 END AS ISBOOKMARKED,
        (SELECT COUNT(*) FROM LIKED l2 WHERE l2.syairId = s.syairId) AS likeCount
    FROM SYAIR s 
    JOIN BASE_USER b ON s.userId = b.userId
    JOIN STATUS st ON s.syairId = st.syairId
    WHERE st.currentStatus = 'Approved' AND s.userId != :userId
    ORDER BY s.yearPublish DESC
    FETCH FIRST 5 ROWS ONLY
", [':userId' => $userId]);



// ==========================
// HANDLE EDIT PROFILE (PRG)
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name     = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($password !== "") {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE BASE_USER
                SET name = :name, username = :username, email = :email, password = :password
                WHERE userId = :userId";
        $stmt = oci_parse($db_conn, $sql);
        oci_bind_by_name($stmt, ':password', $hashed);
    } else {
        $sql = "UPDATE BASE_USER
                SET name = :name, username = :username, email = :email
                WHERE userId = :userId";
        $stmt = oci_parse($db_conn, $sql);
    }

    oci_bind_by_name($stmt, ':name', $name);
    oci_bind_by_name($stmt, ':username', $username);
    oci_bind_by_name($stmt, ':email', $email);
    oci_bind_by_name($stmt, ':userId', $userId);

    if (oci_execute($stmt)) {
        $_SESSION['username'] = $username;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['success'] = "Profile updated successfully ✅";
    } else {
        $_SESSION['error'] = "Failed to update profile ❌";
    }
    header("Location: userDashboard.php#editProfile");
    exit();
}

// ==========================
// HANDLE ADD SYAIR (PRG)
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_syair'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $author = trim($_POST['author']);
    $yearPublish = $_POST['yearPublish'];
    $themeOption = $_POST['theme_option'];
    $customTheme = trim($_POST['custom_theme']);
    $theme = ($themeOption === 'other') ? $customTheme : $themeOption;

    if (!$title || !$content || !$author || !$theme || !$yearPublish) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: userDashboard.php#addSyair");
        exit();
    }

    // Get new syairId
    $sql = "SELECT syair_seq.NEXTVAL AS SYAIRID FROM dual";
    $stmt = oci_parse($db_conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    $syairId = $row['SYAIRID'];

    // Insert into SYAIR
    $sql = "INSERT INTO SYAIR 
            (syairId, title, content, author, theme, yearPublish, userId)
            VALUES (:syairId, :title, :content, :author, :theme, TO_DATE(:yearPublish,'YYYY-MM-DD'), :userId)";
    $stmt = oci_parse($db_conn, $sql);
    oci_bind_by_name($stmt, ':syairId', $syairId);
    oci_bind_by_name($stmt, ':title', $title);
    oci_bind_by_name($stmt, ':content', $content);
    oci_bind_by_name($stmt, ':author', $author);
    oci_bind_by_name($stmt, ':theme', $theme);
    oci_bind_by_name($stmt, ':yearPublish', $yearPublish);
    oci_bind_by_name($stmt, ':userId', $userId);

    if (oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $sqlStatus = "INSERT INTO STATUS 
                      (statusId, syairId, currentStatus, status_date)
                      VALUES (status_seq.NEXTVAL, :syairId, 'Pending', SYSTIMESTAMP)";
        $stmtStatus = oci_parse($db_conn, $sqlStatus);
        oci_bind_by_name($stmtStatus, ':syairId', $syairId);

        if (oci_execute($stmtStatus, OCI_NO_AUTO_COMMIT)) {
            oci_commit($db_conn);
            $_SESSION['success'] = "Syair added successfully and is pending approval!";
            header("Location: userDashboard.php#mySyair");
            exit();
        } else {
            oci_rollback($db_conn);
            $_SESSION['error'] = "Failed to set syair status.";
            header("Location: userDashboard.php#addSyair");
            exit();
        }
    } else {
        oci_rollback($db_conn);
        $_SESSION['error'] = "Failed to add syair.";
        header("Location: userDashboard.php#addSyair");
        exit();
    }
}
// ==========================
// HANDLE DELETE SYAIR
// ==========================
if (isset($_GET['delete_syair'])) {
    $syairIdToDelete = intval($_GET['delete_syair']);

    // Pastikan syair tu milik user
    $checkSql = "SELECT COUNT(*) AS CNT FROM SYAIR WHERE syairId = :syairId AND userId = :userId";
    $stmt = oci_parse($db_conn, $checkSql);
    oci_bind_by_name($stmt, ':syairId', $syairIdToDelete);
    oci_bind_by_name($stmt, ':userId', $userId);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);

    if ($row['CNT'] > 0) {
        // Start delete with commit control
        $success = true;

        // 1. Delete STATUS first
        $delStatusSql = "DELETE FROM STATUS WHERE syairId = :syairId";
        $stmtStatus = oci_parse($db_conn, $delStatusSql);
        oci_bind_by_name($stmtStatus, ':syairId', $syairIdToDelete);
        if (!oci_execute($stmtStatus, OCI_NO_AUTO_COMMIT)) $success = false;

        // 2. Delete BOOKMARK (optional, in case user bookmarked it)
        $delBookmarkSql = "DELETE FROM BOOKMARK WHERE syairId = :syairId";
        $stmtBookmark = oci_parse($db_conn, $delBookmarkSql);
        oci_bind_by_name($stmtBookmark, ':syairId', $syairIdToDelete);
        if (!oci_execute($stmtBookmark, OCI_NO_AUTO_COMMIT)) $success = false;

        // 3. Delete LIKED (optional, in case user liked it)
        $delLikedSql = "DELETE FROM LIKED WHERE syairId = :syairId";
        $stmtLiked = oci_parse($db_conn, $delLikedSql);
        oci_bind_by_name($stmtLiked, ':syairId', $syairIdToDelete);
        if (!oci_execute($stmtLiked, OCI_NO_AUTO_COMMIT)) $success = false;

        // 4. Delete SYAIR
        $delSql = "DELETE FROM SYAIR WHERE syairId = :syairId";
        $stmtDel = oci_parse($db_conn, $delSql);
        oci_bind_by_name($stmtDel, ':syairId', $syairIdToDelete);
        if (!oci_execute($stmtDel, OCI_NO_AUTO_COMMIT)) $success = false;

        // Commit or rollback
        if ($success) {
            oci_commit($db_conn);
            $_SESSION['success'] = "Syair deleted successfully ✅";
        } else {
            oci_rollback($db_conn);
            $_SESSION['error'] = "Failed to delete Syair ❌";
        }
    }


    header("Location: userDashboard.php#mySyair");
    exit();
}


// ==========================
// FETCH MESSAGES
// ==========================
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Syair Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cssUser.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


</head>

<body>

    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-user-circle"></i>
            <span>Syair Digital</span>
        </div>
        <div class="menu">
            <a href="#dashboard" data-tab="dashboard"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="#homepage" data-tab="homepage"><i class="fas fa-home"></i> Homepage</a>
            <a href="#addSyair" data-tab="addSyair"><i class="fas fa-pen-nib"></i> Add New Syair</a>
            <a href="#mySyair" data-tab="mySyair"><i class="fas fa-scroll"></i> My Syair</a>
            <a href="#bookmark" data-tab="bookmark"><i class="fas fa-bookmark"></i> Bookmarks</a>
            <a href="#liked" data-tab="liked"><i class="fas fa-heart"></i> Liked</a>
            <a href="#editProfile" data-tab="editProfile"><i class="fas fa-user-gear"></i> Edit Profile</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div>
                <h3 style="color: var(--primary);">Welcome, <?php echo ucfirst(htmlspecialchars($name)); ?>!</h3>
                <small style="color: #888;">Last login: <?php echo date('d M Y, H:i'); ?></small>
            </div>
            <div class="user-profile">
                <div style="text-align: right;">
                    <div style="font-weight: 600; font-size: 0.9rem;">@<?php echo ucfirst(htmlspecialchars($username)); ?></div>
                    <div style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($email); ?></div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
            </div>
        </div>

        <div class="content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h1>Discover & Share Beautiful Poetry</h1>
                    <p>Browse, create, and manage your collection of traditional syair</p>
                </div>
                <div class="quick-actions">
                    <a href="#addSyair" onclick="showTab ('addSyair')" class="btn btn-accent"><i class="fas fa-plus"></i> Add New Syair</a>
                    <a href="#homepage" onclick="showTab('homepage')" class="btn" style="background: white; color: var(--primary);"><i class="fas fa-search"></i> Browse Syair</a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div id="dashboard" class="tab active">
                <div class="stats-grid">
                    <div class="stat-card" onclick="showTab('mySyair')">
                        <div class="stat-info">
                            <h3><?php echo $stats['MY_POEMS']; ?></h3>
                            <p>My Syair</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-scroll"></i></div>
                    </div>
                    <div class="stat-card" onclick="showTab('bookmark')">
                        <div class="stat-info">
                            <h3><?php echo $stats['BOOKMARKS']; ?></h3>
                            <p>Bookmarks</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
                    </div>
                    <div class="stat-card" onclick="showTab('liked')">
                        <div class="stat-info">
                            <h3><?php echo $stats['LIKES']; ?></h3>
                            <p>Liked Syair</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-heart"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $stats['APPROVED_POEMS']; ?></h3>
                            <p>Approved</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>

                <!-- Recent Poems Section -->
                <div class="card">
                    <div class="section-header">
                        <span class="section-title">Recently Added Syair</span>
                        <a href="#homepage" onclick="showTab('homepage')" class="btn btn-primary">View All</a>
                    </div>
                    <?php if (count($recentPoems) > 0): ?>
                        <div class="poem-grid">
                            <?php foreach ($recentPoems as $poem): ?>
                                <div class="poem-card">
                                    <div class="poem-title"><?php echo htmlspecialchars($poem['TITLE']); ?></div>
                                    <div class="poem-meta">By: <?php echo htmlspecialchars($poem['AUTHOR']); ?></div>
                                    <div class="poem-meta">Year: <?php echo htmlspecialchars($poem['YEARPUBLISH']); ?></div>
                                    <div class="poem-meta">Added by: <?php echo htmlspecialchars($poem['ADDED_BY']); ?></div>
                                    <div style="margin-top: 15px;">
                                        <a href="viewSyair.php?id=<?php echo $poem['SYAIRID']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a class="action-btn btn-like <?php echo $poem['ISLIKED'] ? 'active' : ''; ?>"
                                            href="liked.php?id=<?php echo $poem['SYAIRID']; ?>" title="Like">
                                            <i class="fas fa-heart"></i> <span class="like-count"><?php echo $poem['LIKECOUNT']; ?></span>
                                        </a>

                                        <a class="action-btn btn-bookmark <?php echo $poem['ISBOOKMARKED'] ? 'active' : ''; ?>"
                                            href="bookmark.php?id=<?php echo $poem['SYAIRID']; ?>" title="Bookmark">
                                            <i class="fas fa-bookmark"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #888; padding: 30px;">No recent Syair available. Be the first to add one!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Homepage Tab -->
            <div id="homepage" class="tab">
                <div id="searchContainer" style="margin-bottom: 15px;">
                    <input type="text" id="searchInput" placeholder="Search Syair by title, author, or theme..."
                        style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
                </div>
                <div class="card">
                    <div class="section-header">
                        <span class="section-title">All Syair</span>
                        <a href="#addSyair" onclick="showTab ('addSyair')" class="btn btn-primary"><i class="fas fa-plus"></i> Add Your Own</a>
                    </div>
                    <?php if (count($homepageSyair) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Theme</th>
                                    <th>Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($homepageSyair as $s): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['TITLE']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                                        <td><?php echo htmlspecialchars($s['THEME']); ?></td>
                                        <td><?php echo htmlspecialchars($s['YEARPUBLISH']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a class="action-btn btn-view" href="viewSyair.php?id=<?php echo $s['SYAIRID']; ?>" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a class="action-btn btn-like <?php echo $s['ISLIKED'] ? 'active' : ''; ?>"
                                                    href="liked.php?id=<?php echo $s['SYAIRID']; ?>" title="Like">
                                                    <i class="fas fa-heart"></i> <span class="like-count"><?php echo $s['LIKECOUNT']; ?></span>
                                                </a>

                                                <a class="action-btn btn-bookmark <?php echo $s['ISBOOKMARKED'] ? 'active' : ''; ?>"
                                                    href="bookmark.php?id=<?php echo $s['SYAIRID']; ?>" title="Bookmark">
                                                    <i class="fas fa-bookmark"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #888; padding: 30px;">No Syair available yet. Be the first to add one!</p>
                    <?php endif; ?>
                </div>
            </div>
            <div id="addSyair" class="tab">
                <div class="content">
                    <div class="card">
                        <div class="page-header">
                            <h2><i class="fas fa-feather-alt"></i> Create Your Syair</h2>
                            <p>Share your poetic expression with the community</p>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <p><strong>Note:</strong> Your syair will be reviewed by administrators before being published. Please ensure your content follows community guidelines.</p>
                        </div>

                        <form method="POST" onsubmit="return validateForm(event)">
                            <!-- Hidden field to detect form -->
                            <input type="hidden" name="add_syair" value="1">

                            <!-- Title -->
                            <div class="form-group">
                                <label><i class="fas fa-heading"></i> Title</label>
                                <input type="text" name="title" placeholder="Enter a captivating title for your syair" required
                                    value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
                            </div>

                            <!-- Content -->
                            <div class="form-group">
                                <label><i class="fas fa-scroll"></i> Content</label>
                                <textarea name="content" placeholder="Write your syair here..." required oninput="updateCharCount(this)"><?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?></textarea>
                                <!-- <div class="char-count">0 characters</div> -->
                            </div>

                            <!-- Author -->
                            <div class="form-group">
                                <label><i class="fas fa-user-pen"></i> Author</label>
                                <input type="text" name="author" placeholder="Author name" required
                                    value="<?= isset($_POST['author']) ? htmlspecialchars($_POST['author']) : '' ?>">
                            </div>

                            <!-- Theme -->
                            <div class="form-group">
                                <label><i class="fas fa-palette"></i> Theme</label>
                                <select name="theme_option" id="theme_option" onchange="toggleCustomTheme()" required>
                                    <option value="">-- Select a theme --</option>
                                    <option value="Love">Love & Romance</option>
                                    <option value="Religion">Religion & Spirituality</option>
                                    <option value="Advice">Advice & Wisdom</option>
                                    <option value="History">History & Heritage</option>
                                    <option value="Nature">Nature & Environment</option>
                                    <option value="Social">Social Issues</option>
                                    <option value="Patriotism">Patriotism</option>
                                    <!-- <option value="other">Other (Custom)</option> -->
                                </select>
                            </div>

                            <!-- <div class="form-group" id="custom_theme_wrapper" style="display:none;">
                                <label><i class="fas fa-pen"></i> Custom Theme</label>
                                <input type="text" name="custom_theme" id="custom_theme" placeholder="Enter your custom theme">
                            </div> -->

                            <!-- Publication Date -->
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Publication Date</label>
                                <input type="date" name="yearPublish" required value="<?= isset($_POST['yearPublish']) ? htmlspecialchars($_POST['yearPublish']) : date('Y-m-d') ?>">
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Syair</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- My Syair Tab -->
            <div id="mySyair" class="tab">
                <div class="card">
                    <div class="section-header">
                        <span class="section-title">My Added Syair</span>
                        <a href="#addSyair" onclick="showTab ('addSyair')" class="btn btn-primary"><i class="fas fa-plus"></i> Add New</a>
                    </div>
                    <?php if (count($mySyair) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Theme</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mySyair as $s): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['TITLE']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                                        <td><?php echo htmlspecialchars($s['THEME']); ?></td>
                                        <td><?php echo $s['YEARPUBLISH']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($s['CURRENTSTATUS']); ?>">
                                                <?php echo $s['CURRENTSTATUS']; ?>
                                            </span>
                                            <a href="userDashboard.php?delete_syair=<?php echo $s['SYAIRID']; ?>"
                                                class="btn btn-danger btn-delete"
                                                onclick="return confirm('Are you sure you want to delete this Syair?');"
                                                title="Delete Syair">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #888; padding: 30px;">You haven't added any syair yet. <a href="#addSyair" onclick="showTab ('addSyair')" style="color: var(--primary);">Create your first one!</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bookmarks Tab -->
            <div id="bookmark" class="tab">
                <div class="card">
                    <div class="section-header">
                        <span class="section-title">Bookmarked Syair</span>
                    </div>
                    <?php if (count($bookmarkedSyair) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Theme</th>
                                    <th>Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookmarkedSyair as $s): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['TITLE']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                                        <td><?php echo htmlspecialchars($s['THEME']); ?></td>
                                        <td><?php echo htmlspecialchars($s['YEARPUBLISH']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a class="action-btn btn-view" href="viewSyair.php?id=<?php echo $s['SYAIRID']; ?>" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a class="action-btn btn-bookmark active"
                                                    href="bookmark.php?id=<?php echo $s['SYAIRID']; ?>" title="Remove Bookmark">
                                                    <i class="fas fa-bookmark"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #888; padding: 30px;">No bookmarks yet. Start browsing and bookmark your favorite Syair!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Liked Tab -->
            <div id="liked" class="tab">
                <div class="card">
                    <div class="section-header">
                        <span class="section-title">Liked Syair</span>
                    </div>
                    <?php if (count($likedSyair) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Theme</th>
                                    <th>Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($likedSyair as $s): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['TITLE']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                                        <td><?php echo htmlspecialchars($s['THEME']); ?></td>
                                        <td><?php echo htmlspecialchars($s['YEARPUBLISH']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a class="action-btn btn-view" href="viewSyair.php?id=<?php echo $s['SYAIRID']; ?>" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a class="action-btn btn-like active"
                                                    href="liked.php?id=<?php echo $s['SYAIRID']; ?>" title="Unlike">
                                                    <i class="fas fa-heart"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #888; padding: 30px;">No liked Syair yet. Start exploring and like the ones you enjoy!</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Edit Profile Tab -->
            <div id="editProfile" class="tab">
                <div class="card">
                    <h2><i class="fa-solid fa-user-gear"></i> Edit Profile</h2>

                    <form method="post">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name"
                                value="<?= htmlspecialchars($user['NAME']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username"
                                value="<?= htmlspecialchars($user['USERNAME']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email"
                                value="<?= htmlspecialchars($user['EMAIL']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>New Password (optional)</label>
                            <input type="password" name="password" placeholder="Leave blank to keep current password">
                        </div>

                        <button type="submit"><i class="fa-solid fa-save"></i> Save Changes</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Tab system
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            const selected = document.getElementById(tabName);
            if (selected) selected.classList.add('active');

            // Update active sidebar link
            document.querySelectorAll('.sidebar a[data-tab]').forEach(link => link.classList.remove('active'));
            const activeLink = document.querySelector(`.sidebar a[data-tab="${tabName}"]`);
            if (activeLink) activeLink.classList.add('active');

            // Show search input **only for Homepage tab**
            const searchContainer = document.getElementById('searchContainer');
            if (searchContainer) {
                searchContainer.style.display = (tabName === 'homepage') ? 'block' : 'none';
            }

            // Update URL hash
            window.location.hash = tabName;
        }


        // Sidebar click handler
        document.querySelectorAll('.sidebar a[data-tab]').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const tab = link.getAttribute('data-tab');
                showTab(tab);
            });
        });

        // Check URL hash on load
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.replace('#', '');
            if (hash && document.getElementById(hash)) showTab(hash);
            else showTab('dashboard'); // default tab
        });

        document.addEventListener('click', function(e) {
            const likeBtn = e.target.closest('.btn-like');
            const bookmarkBtn = e.target.closest('.btn-bookmark');

            function updateCount(selector, change) {
                const el = document.querySelector(selector);
                if (!el) return;
                let count = parseInt(el.textContent) || 0;
                el.textContent = count + change;
            }

            if (likeBtn) {
                e.preventDefault();
                fetch(likeBtn.href)
                    .then(res => res.json())
                    .then(data => {
                        const likeSpan = likeBtn.querySelector('.like-count');
                        let current = parseInt(likeSpan.textContent) || 0;

                        if (data.status === 'liked') {
                            likeBtn.classList.add('active');
                            likeSpan.textContent = current + 1;
                            updateCount('.stat-card:nth-child(3) .stat-info h3', 1);
                            Swal.fire({
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                title: 'Liked!'
                            });
                        } else if (data.status === 'unliked') {
                            likeBtn.classList.remove('active');
                            likeSpan.textContent = Math.max(current - 1, 0);
                            updateCount('.stat-card:nth-child(3) .stat-info h3', -1);
                            Swal.fire({
                                icon: 'info',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                title: 'Unliked!'
                            });
                        }
                    });
            }


            if (bookmarkBtn) {
                e.preventDefault();
                fetch(bookmarkBtn.href)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'bookmarked') {
                            bookmarkBtn.classList.add('active');
                            updateCount('.stat-card:nth-child(2) .stat-info h3', 1);
                            Swal.fire({
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                title: 'Bookmarked!'
                            });
                        } else if (data.status === 'removed') {
                            bookmarkBtn.classList.remove('active');
                            updateCount('.stat-card:nth-child(2) .stat-info h3', -1);
                            Swal.fire({
                                icon: 'info',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                title: 'Bookmark removed!'
                            });
                        }
                    });
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            searchInput?.addEventListener('keyup', function() {
                const filter = this.value.toLowerCase();
                const table = document.querySelector('#homepage table tbody');
                if (!table) return;

                Array.from(table.rows).forEach(row => {
                    const title = row.cells[0].textContent.toLowerCase();
                    const author = row.cells[1].textContent.toLowerCase();
                    const theme = row.cells[2].textContent.toLowerCase();
                    if (title.includes(filter) || author.includes(filter) || theme.includes(filter)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    </script>
    <?php if ($success): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?= addslashes($success) ?>',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>

    <?php if ($error): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Oops!',
                text: '<?= addslashes($error) ?>',
                confirmButtonColor: '#d33',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>


</body>

</html>