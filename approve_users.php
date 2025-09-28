<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];

// Database connection
$host = "localhost";
$db_user = "heri";
$db_pass = "1234";
$db_name = "school_quality";
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Approve or Reject user action
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);

    if ($_GET['action'] === 'approve') {
        if ($conn->query("UPDATE users SET status='approved' WHERE id=$id")) {
            echo "<script>alert('✅ User approved successfully!'); window.location='approve_users.php';</script>";
            exit;
        } else {
            echo "<script>alert('❌ Failed to approve user.'); window.location='approve_users.php';</script>";
            exit;
        }
    } elseif ($_GET['action'] === 'reject') {
        if ($conn->query("UPDATE users SET status='rejected' WHERE id=$id")) {
            echo "<script>alert('❌ User rejected.'); window.location='approve_users.php';</script>";
            exit;
        } else {
            echo "<script>alert('❌ Failed to reject user.'); window.location='approve_users.php';</script>";
            exit;
        }
    }
}

// Fetch pending users
$result = $conn->query("SELECT id, username, email, role, created_at FROM users WHERE status='pending'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Approve Users</title>
<style>
/* General body styling */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    display: flex;
    background-image: url('images/Tanzania.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
}

/* White overlay */
body::before {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.85);
    z-index: -1;
}

/* Watermark */
body::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 120px;
    height: 120px;
    background-image: url('images/emblem.png');
    background-size: contain;
    background-repeat: no-repeat;
    opacity: 0.2;
    transform: translate(-50%, -50%);
    z-index: -1;
}

/* Sidebar */
.sidebar {
    width: 220px;
    background: #1F5F60;
    color: white;
    display: flex;
    flex-direction: column;
    padding: 20px 15px;
    box-shadow: 2px 0 6px rgba(0,0,0,0.2);
    transition: transform 0.3s ease;
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    z-index: 900;
}
.sidebar.hidden {
    transform: translateX(-100%);
}
.sidebar h2 {
    margin: 0 0 20px;
    font-size: 18px;
}
.sidebar a {
    color: white;
    text-decoration: none;
    padding: 10px;
    margin: 5px 0;
    border-radius: 6px;
    display: block;
    transition: background 0.3s ease;
}
.sidebar a:hover {
    background: rgba(255,255,255,0.2);
}

/* Header */
.header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 60px;
    padding: 10px 20px;
    background: #2C3E50;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 1000;
    box-sizing: border-box;
}
.header h1 {
    margin: 0;
    font-size: 20px;
}

/* Hamburger menu */
.menu-toggle {
    font-size: 20px;
    cursor: pointer;
    margin-right: 15px;
    display: inline-block;
}

/* Logout button */
.logout-btn {
    padding: 8px 15px;
    background: #FF4B5C;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: bold;
    transition: background 0.3s ease;
}
.logout-btn:hover { background: #d93a47; }
/* Notification */
.notification-indicator {
    display:inline-block;
    width:8px;
    height:8px;
    border-radius:50%;
    background:red;
    margin-left:6px;
    animation: blink 1s infinite;
    vertical-align: middle;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}


.main {
    flex: 1;
    margin-left: 220px; /* space for sidebar */
    display: flex;
    justify-content: center; /* horizontal center */
    align-items: flex-start; /* align to top */
    padding: 100px 20px 20px 20px; /* top padding for header space */
    transition: margin-left 0.3s ease;
}
.main.expanded {
    margin-left: 0;
}
/* Table styling */
.container {
    width: 900px;
    max-width: 95%;   /* responsive on smaller screens */
    background: #fff;
    border-radius: 12px;
    padding: 20px 25px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
}
.container h2 {
    text-align: center;
    color: Black;
}
/* Centered image */
.container .top-image {
    text-align: center;
    margin-bottom: 15px;
}
.container .top-image img {
    max-width: 120px;
    width: 100%;
    height: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 12px 10px;
    border: 1px solid #ddd;
    text-align: center;
}
th {
    background: #007BFF;
    color: #fff;
}
a.button {
    padding: 6px 12px;
    margin: 2px;
    text-decoration: none;
    border-radius: 5px;
    color: #fff;
    font-weight: bold;
}
a.approve { background: #28a745; }
a.reject { background: #dc3545; }
a.approve:hover { background: #218838; }
a.reject:hover { background: #c82333; }

</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Sidebar -->
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Admin Panel</h2>
    <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="approve_users.php"><i class="fas fa-user-check"></i> Approve Users</a>
    <a href="reset_user_password.php"><i class="fas fa-key"></i> Reset Password</a>
    <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="registered_user.php"><i class="fas fa-users"></i> Registered Users</a>
    <a href="user_logs.php"><i class="fas fa-file-alt"></i> Logs</a>
</div>


<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Main content -->
<div class="main">
    <div class="container">
        <!-- Added centered image -->
        <div class="top-image">
            <img src="images/Wizara ya elimu.jpg" alt="Header Image">
        </div>

        <h2>Pending Users Approval</h2>
        <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>created At</th>
                <th>Action</th>
            </tr>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td><?= $row['created_at'] ?></td>
                <td>
                    <a href="?action=approve&id=<?= $row['id'] ?>" class="button approve">Approve</a>
                    <a href="?action=reject&id=<?= $row['id'] ?>" class="button reject">Reject</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p style="text-align:center; color:#555; margin-top:20px;">No pending users at the moment.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Sidebar toggle (single, correct listener)
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.querySelector(".main").classList.toggle("expanded");
});

function checkUnread() {
    fetch('check_unread.php')
        .then(response => response.json())
        .then(data => {
            const indicator = document.getElementById("msgIndicator");
            if (data.count > 0) {
                indicator.className = "notification-indicator"; // show blinking dot
            } else {
                indicator.className = ""; // hide if none
            }
        });
}

// Run immediately and then every 5 seconds
checkUnread();
setInterval(checkUnread, 5000);
</script>

</body>
</html>
