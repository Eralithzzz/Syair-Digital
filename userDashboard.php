<?php
session_start();
require_once 'oracle.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- Fetch approved syair for main dashboard (Homepage tab) ---
$sql = "SELECT s.syairId, s.title, s.author, TO_CHAR(s.yearPublish,'YYYY') as yearPublish
        FROM SYAIR s
        JOIN STATUS st ON s.syairId = st.syairId
        WHERE st.currentStatus = 'Approved'
        ORDER BY s.syairId DESC";

$stmt = oci_parse($db_conn, $sql);
oci_execute($stmt);

$homepageSyair = [];
while ($row = oci_fetch_assoc($stmt)) {
    $homepageSyair[] = $row;
}

// --- Fetch user's pending syair ---
$sql = "SELECT s.syairId, s.title, s.author, TO_CHAR(s.yearPublish,'YYYY') as yearPublish
        FROM SYAIR s
        JOIN STATUS st ON s.syairId = st.syairId
        WHERE s.userId = :userId AND st.currentStatus = 'Pending'
        ORDER BY s.syairId DESC";

$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':userId', $userId);
oci_execute($stmt);

$pendingSyair = [];
while ($row = oci_fetch_assoc($stmt)) {
    $pendingSyair[] = $row;
}

// --- Fetch user's bookmarks ---
$sql = "SELECT s.syairId, s.title, s.author, TO_CHAR(s.yearPublish,'YYYY') as yearPublish
        FROM SYAIR s
        JOIN BOOKMARK b ON s.syairId = b.syairId
        WHERE b.userId = :userId
        ORDER BY b.dateBookmark DESC";

$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':userId', $userId);
oci_execute($stmt);

$bookmarkedSyair = [];
while ($row = oci_fetch_assoc($stmt)) {
    $bookmarkedSyair[] = $row;
}

// --- Fetch user's liked syair ---
$sql = "SELECT s.syairId, s.title, s.author, TO_CHAR(s.yearPublish,'YYYY') as yearPublish
        FROM SYAIR s
        JOIN LIKED l ON s.syairId = l.syairId
        WHERE l.userId = :userId
        ORDER BY l.dateLikes DESC";

$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':userId', $userId);
oci_execute($stmt);

$likedSyair = [];
while ($row = oci_fetch_assoc($stmt)) {
    $likedSyair[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard - Syair Digital</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; min-height:100vh; padding:0; }
header { background:#800000; color:white; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; }
header h1 { font-size:24px; }
header nav a { color:white; text-decoration:none; margin-left:20px; font-weight:600; transition:0.3s; }
header nav a:hover { color:#ffeb3b; }
.container { max-width:1000px; margin:20px auto; background:white; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); overflow:hidden; padding:30px; }
h2 { color:#800000; margin-bottom:15px; }
.btn-primary { background-color:#800000; color:white; border:none; padding:10px 20px; border-radius:8px; text-decoration:none; display:inline-block; margin-bottom:15px; }
.btn-primary:hover { background-color:#660000; }
.table { width:100%; border-collapse:collapse; margin-top:20px; }
.table th, .table td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
.status-pending { color: #ffc107; font-weight:600; }
.status-approved { color: #28a745; font-weight:600; }
.tab { display:none; }
.tab.active { display:block; }
nav.tabs a { margin-right:15px; cursor:pointer; color:#800000; font-weight:600; text-decoration:none; transition:0.3s; }
nav.tabs a.active { color:#660000; }
</style>
<script>
function showTab(tabName, el){
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(t => t.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');

    const links = document.querySelectorAll('nav.tabs a');
    links.forEach(l => l.classList.remove('active'));
    el.classList.add('active');
}
</script>
</head>
<body>
<header>
    <h1>Syair Digital</h1>
    <nav>
        <a href="#" onclick="showTab('homepage', this)" class="active">Homepage</a>
        <a href="addSyair.php">Add Syair</a>
        <a href="#" onclick="showTab('pending', this)">Pending List</a>
        <a href="#" onclick="showTab('bookmark', this)">Bookmark</a>
        <a href="#" onclick="showTab('liked', this)">Liked</a>
        <a href="editProfile.php">Edit Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <!-- Homepage -->
    <div id="homepage" class="tab active">
        <h2>Homepage</h2>
        <?php if(count($homepageSyair) === 0): ?>
            <p>No approved syair yet.</p>
        <?php else: ?>
            <table class="table">
                <tr><th>Title</th><th>Author</th><th>Year</th><th>Status</th></tr>
                <?php foreach($homepageSyair as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['TITLE']); ?></td>
                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                        <td><?php echo htmlspecialchars($s['YEARPUBLISH']); ?></td>
                        <td><span class="status-approved">Approved</span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pending -->
    <div id="pending" class="tab">
        <h2>Pending List</h2>
        <?php if(count($pendingSyair) === 0): ?>
            <p>No pending syair.</p>
        <?php else: ?>
            <table class="table">
                <tr><th>Title</th><th>Author</th><th>Year</th><th>Status</th></tr>
                <?php foreach($pendingSyair as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['TITLE']); ?></td>
                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                        <td><?php echo htmlspecialchars($s['YEARPUBLISH']); ?></td>
                        <td><span class="status-pending">Pending</span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Bookmark -->
    <div id="bookmark" class="tab">
        <h2>Bookmarked Syair</h2>
        <?php if(count($bookmarkedSyair) === 0): ?>
            <p>No bookmarked syair yet.</p>
        <?php else: ?>
            <table class="table">
                <tr><th>Title</th><th>Author</th><th>Year</th></tr>
                <?php foreach($bookmarkedSyair as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['TITLE']); ?></td>
                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                        <td><?php echo htmlspecialchars($s['YEARPUBLISH']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Liked -->
    <div id="liked" class="tab">
        <h2>Liked Syair</h2>
        <?php if(count($likedSyair) === 0): ?>
            <p>No liked syair yet.</p>
        <?php else: ?>
            <table class="table">
                <tr><th>Title</th><th>Author</th><th>Year</th></tr>
                <?php foreach($likedSyair as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['TITLE']); ?></td>
                        <td><?php echo htmlspecialchars($s['AUTHOR']); ?></td>
                        <td><?php echo htmlspecialchars($s['YEARPUBLISH']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
