<?php
session_start();
require_once 'oracle.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'];

// homepage (with like & bookmark status)
$sql = "SELECT s.syairId,
               s.title,
               s.author,
               TO_CHAR(s.yearPublish,'YYYY') AS yearPublish,

               CASE 
                   WHEN EXISTS (
                       SELECT 1 FROM LIKED l
                       WHERE l.syairId = s.syairId
                       AND l.userId = :userId
                   ) THEN 1 ELSE 0
               END AS isLiked,

               CASE 
                   WHEN EXISTS (
                       SELECT 1 FROM BOOKMARK b
                       WHERE b.syairId = s.syairId
                       AND b.userId = :userId
                   ) THEN 1 ELSE 0
               END AS isBookmarked

        FROM SYAIR s
        JOIN STATUS st ON s.syairId = st.syairId
        WHERE st.currentStatus = 'Approved'
        ORDER BY st.status_date DESC";


$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':userId', $userId);
oci_execute($stmt);


$homepageSyair = [];
while ($row = oci_fetch_assoc($stmt)) {
    $homepageSyair[] = $row;
}

//Added syair
$sql = "SELECT s.syairId, s.title, s.author,
               TO_CHAR(s.yearPublish,'YYYY') AS yearPublish,
               st.currentStatus
        FROM SYAIR s
        JOIN STATUS st ON s.syairId = st.syairId
        WHERE s.userId = :userId
        ORDER BY st.status_date DESC";

$stmt = oci_parse($db_conn, $sql);
oci_bind_by_name($stmt, ':userId', $userId);
oci_execute($stmt);

$mySyair = [];
while ($row = oci_fetch_assoc($stmt)) {
    $mySyair[] = $row;
}

//bookmark
$sql = "SELECT s.syairId, s.title, s.author,
               TO_CHAR(s.yearPublish,'YYYY') AS yearPublish
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

//Liked
$sql = "SELECT s.syairId, s.title, s.author,
               TO_CHAR(s.yearPublish,'YYYY') AS yearPublish
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
$likeClass = (isset($s['ISLIKED']) && $s['ISLIKED']) ? 'active' : '';
$bookmarkClass = (isset($s['ISBOOKMARKED']) && $s['ISBOOKMARKED']) ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Syair Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin:0;padding:0;
            box-sizing:border-box;
            font-family:'Segoe UI',sans-serif;
        }
        body{background:#f8f9fa;}
        header{background:#800000;color:white;padding:15px 30px;display:flex;justify-content:space-between;}
        header nav a{color:white;text-decoration:none;margin-left:18px;font-weight:600;}
        header nav a:hover{color:#ffeb3b;}
        .container{max-width:1000px;margin:25px auto;background:white;padding:30px;border-radius:15px;}
        h2 {
            color:#800000;
            margin-bottom:15px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .table th {
            padding: 15px;
            text-align: left;
            background: #f3f3f3;
            color: #800000;
            font-weight: 700;
        }
        .table td {
            padding: 15px;
            text-align: left;
            vertical-align: top;
        }
        .table th:nth-child(1),
        .table td:nth-child(1) {
            width: 45%;   /* Title */
        }
        .table th:nth-child(2),
        .table td:nth-child(2) {
            width: 30%;   /* Author */
        }
        .table th:nth-child(3),
        .table td:nth-child(3) {
            width: 10%;   /* Year */
            text-align: center;
        }
        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 15%;   /* Status (if any) */
            text-align: center;
        }
        .btn-view,
        .btn-like,
        .btn-bookmark {
            margin-right: 12px;
            color: #999;
            font-size: 17px;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-view:hover { color: #007bff; }
        .btn-like:hover { color: #dc3545; }
        .btn-bookmark:hover { color: #ffc107; }
        .btn-like.active {
            color: #dc3545;
        }
        .btn-bookmark.active {
            color: #ffc107;
        }
        .tab{display:none;}
        .tab.active{display:block;}
        .status-Pending{color:#ffc107;font-weight:600;}
        .status-Approved{color:#28a745;font-weight:600;}
        .status-Rejected{color:#dc3545;font-weight:600;}
    </style>

<script>
function showTab(tab){
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.getElementById(tab).classList.add('active');
}
document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#','');
    if(hash) showTab(hash);
});
</script>
</head>

<body>
<header>
    <h1>Syair Digital</h1>
    <nav>
        <a href="#" onclick="showTab('home')">
            <i class="fa-solid fa-house"></i> Homepage
        </a>

        <a href="addSyair.php">
            <i class="fa-solid fa-pen-nib"></i> Add Syair
        </a>

        <a href="#" onclick="showTab('mySyair')">
            <i class="fa-solid fa-list"></i> My Added Syair
        </a>

        <a href="#" onclick="showTab('bookmark')">
            <i class="fa-solid fa-bookmark"></i> Bookmark
        </a>

        <a href="#" onclick="showTab('liked')">
            <i class="fa-solid fa-heart"></i> Liked
        </a>

        <a href="editProfile.php">
            <i class="fa-solid fa-user-gear"></i> Edit Profile
        </a>

        <a href="logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</header>

<div class="container">

<!-- HOMEPAGE -->
<div id="home" class="tab active">
    <h2>Homepage</h2>
    <?php if(!$homepageSyair): ?>
        <p>No approved syair yet.</p>
    <?php else: ?>
    <table class="table">
        <tr><th>Title</th><th>Author</th><th>Year</th><th>Actions</th></tr>
        <?php foreach($homepageSyair as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['TITLE']) ?></td>
            <td><?= htmlspecialchars($s['AUTHOR']) ?></td>
            <td><?= htmlspecialchars($s['YEARPUBLISH']) ?></td>
            <td>
                <!-- VIEW -->
                <a class="btn-view" href="viewSyair.php?id=<?= $s['SYAIRID'] ?>&from=home">
                    <i class="fa-solid fa-eye"></i>
                </a>

                <!-- LIKE -->
                <a class="btn-like <?= isset($s['ISLIKED']) && $s['ISLIKED'] ? 'active' : '' ?>"
                   href="liked.php?id=<?= $s['SYAIRID'] ?>"
                   title="Like">
                    <i class="fa-solid fa-heart"></i>
                </a>

                <!-- BOOKMARK -->
                <a class="btn-bookmark <?= isset($s['ISBOOKMARKED']) && $s['ISBOOKMARKED'] ? 'active' : '' ?>"
                   href="bookmark.php?id=<?= $s['SYAIRID'] ?>"
                   title="Bookmark">
                    <i class="fa-solid fa-bookmark"></i>
                </a>
            </td>
        </tr>
        <?php endforeach;?>
    </table>
    <?php endif;?>
</div>

    <!-- MY ADDED SYAIR -->
    <div id="mySyair" class="tab">
        <h2>My Added Syair</h2>
        <?php if(!$mySyair): ?>
            <p>You haven't added any syair.</p>
            <?php else: ?>
            <table class="table">
                <tr><th>Title</th><th>Author</th><th>Year</th><th>Status</th></tr>
                <?php foreach($mySyair as $s): ?>
                <tr>
                    <td><?=htmlspecialchars($s['TITLE'])?></td>
                    <td><?=htmlspecialchars($s['AUTHOR'])?></td>
                    <td><?=$s['YEARPUBLISH']?></td>
                    <td class="status-<?=$s['CURRENTSTATUS']?>"><?=$s['CURRENTSTATUS']?></td>
                </tr>
                <?php endforeach;?>
            </table>
        <?php endif;?>
    </div>

    <!-- BOOKMARK -->
<div id="bookmark" class="tab">
    <h2>Bookmarked Syair</h2>
    <?php if(!$bookmarkedSyair): ?>
        <p>No bookmarks yet.</p>
    <?php else: ?>
    <table class="table">
        <tr><th>Title</th><th>Author</th><th>Year</th><th>Actions</th></tr>
        <?php foreach($bookmarkedSyair as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['TITLE']) ?></td>
            <td><?= htmlspecialchars($s['AUTHOR']) ?></td>
            <td><?= htmlspecialchars($s['YEARPUBLISH']) ?></td>
            <td>
                <!-- VIEW -->
                <a class="btn-view" href="viewSyair.php?id=<?= $s['SYAIRID'] ?>&from=bookmark">
                    <i class="fa-solid fa-eye"></i>
                </a>

                <!-- BOOKMARK -->
                <a class="btn-bookmark <?= isset($s['ISBOOKMARKED']) && $s['ISBOOKMARKED'] ? 'active' : '' ?>"
                   href="bookmark.php?id=<?= $s['SYAIRID'] ?>"
                   title="Bookmark">
                    <i class="fa-solid fa-bookmark"></i>
                </a>
            </td>
        </tr>
        <?php endforeach;?>
    </table>
    <?php endif;?>
</div>

<!-- LIKED -->
<div id="liked" class="tab">
    <h2>Liked Syair</h2>
    <?php if(!$likedSyair): ?>
        <p>No liked syair yet.</p>
    <?php else: ?>
    <table class="table">
        <tr><th>Title</th><th>Author</th><th>Year</th><th>Actions</th></tr>
        <?php foreach($likedSyair as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['TITLE']) ?></td>
            <td><?= htmlspecialchars($s['AUTHOR']) ?></td>
            <td><?= htmlspecialchars($s['YEARPUBLISH']) ?></td>
            <td>
                <!-- VIEW -->
                <a class="btn-view" href="viewSyair.php?id=<?= $s['SYAIRID'] ?>&from=liked">
                    <i class="fa-solid fa-eye"></i>
                </a>

                <!-- LIKE -->
                <a class="btn-like <?= isset($s['ISLIKED']) && $s['ISLIKED'] ? 'active' : '' ?>"
                   href="liked.php?id=<?= $s['SYAIRID'] ?>"
                   title="Like">
                    <i class="fa-solid fa-heart"></i>
                </a>
            </td>
        </tr>
        <?php endforeach;?>
    </table>
    <?php endif;?>
</div>
</div>
</body>
</html>