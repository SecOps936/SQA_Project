<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require 'db.php';
$username = $_SESSION['username'];

// Fetch all users
$result = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$total_users = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Registered Users</title>
<style>
/* General body styling */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    height: 100vh;
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
.sidebar.hidden { transform: translateX(-100%); }
.sidebar h2 { margin: 0 0 20px; font-size: 18px; }
.sidebar a {
    color: white;
    text-decoration: none;
    padding: 10px;
    margin: 5px 0;
    border-radius: 6px;
    display: block;
    transition: background 0.3s ease;
}
.sidebar a:hover { background: rgba(255,255,255,0.2); }

/* Main content */
.main {
    flex: 1;
    margin-left: 220px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 140px 20px 20px 20px;
    transition: margin-left 0.3s ease;
}
.main.expanded { margin-left: 0; }

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
.header h1 { margin: 0; font-size: 20px; }

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

/* Table container */
.table-container {
    width: 900px;
    max-width: 95%;
    background: #fff;
    border-radius: 12px;
    padding: 20px 25px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
}
.table-container h2 {
    margin: 10px 0;
    color: black;
    text-align: center;
    font-size: 22px;
    font-weight: bold;
}
.table-container .header-image {
    text-align: center;
    margin-bottom: 10px;
}
.table-container .header-image img {
    width: 120px;
    max-width: 100%;
    height: auto;
}

/* Table */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
table th, table td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}
table th {
    background: #f1f1f1;
    color: #333;
}
table tr:hover { background: #f9f9f9; }

/* Responsive Fix */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main {
        margin-left: 0 !important;
        padding: 100px 10px 20px 10px;
    }
}
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
    <a href="send_info.php"><i class="fas fa-paper-plane"></i> Provide Information</a>
    <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="receive_info.php"><i class="fas fa-envelope-open-text"></i> Receive Info 
        <span id="msgIndicator"></span>
    </a>
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
<div class="main" id="mainContent">
    <div class="table-container">
        <div class="header-image">
            <img src="images/Wizara ya elimu.jpg" alt="Header Image">
        </div>
        <h2>Registered Users</h2>
        <p style="text-align:center; margin:5px 0; font-size:14px; color:#555;">
            Total: <?php echo $total_users; ?>
        </p>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Registered At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_users > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['role']); ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; color:#777;">No registered users found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Sidebar toggle
const sidebar = document.getElementById("sidebar");
const mainContent = document.getElementById("mainContent");
document.getElementById("menuToggle").addEventListener("click", function() {
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle("show"); // mobile view
    } else {
        sidebar.classList.toggle("hidden"); // desktop view
        mainContent.classList.toggle("expanded");
    }
});

// Notification check
function checkUnread() {
    fetch('check_unread.php')
        .then(response => response.json())
        .then(data => {
            const indicator = document.getElementById("msgIndicator");
            indicator.className = data.count > 0 ? "notification-indicator" : "";
        });
}
checkUnread();
setInterval(checkUnread, 5000);
</script>

</body>
</html>
