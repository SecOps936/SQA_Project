<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require 'db.php';
$username = $_SESSION['username'];

// ✅ Handle clear logs
if (isset($_POST['clear_logs'])) {
    $conn->query("DELETE FROM user_logs");
    header("Location: user_logs.php"); // refresh to see empty table
    exit();
}

// Fetch logs joined with users
$logs = $conn->query("
    SELECT l.id, u.username, u.email, l.action, l.timestamp
    FROM user_logs l
    JOIN users u ON l.user_id = u.id
    ORDER BY l.timestamp DESC
");
$total_logs = $logs->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Login Logs</title>
<style>
/* Body, overlay, watermark */
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
body::before {
    content:"";
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.85);
    z-index: -1;
}
body::after {
    content:"";
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
/* Sidebar */
.sidebar {
    width: 220px;
    background: #1F5F60;
    color: white;
    display: flex;
    flex-direction: column;
    padding: 20px 15px;
    box-shadow: 2px 0 6px rgba(0,0,0,0.2);
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    z-index: 900;
    transition: transform 0.3s ease;
    transform: translateX(0); /* default visible */
}
.sidebar.hidden {
    transform: translateX(-100%); /* hide */
}
.sidebar.show { transform: translateX(0); }
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

/* Responsive Sidebar */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main { margin-left: 0 !important; padding: 80px 15px 15px 15px; }
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
.header h1 { margin:0; font-size: 20px; }
.menu-toggle { font-size:20px; cursor:pointer; margin-right:15px; display:inline-block; }
.logout-btn {
    padding:8px 15px;
    background:#FF4B5C;
    color:white;
    text-decoration:none;
    border-radius:6px;
    font-size:14px;
    font-weight:bold;
    transition: background 0.3s ease;
}
.logout-btn:hover { background:#d93a47; }

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

/* Main content */
.main {
    flex: 1;
    margin-left: 220px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 100px 20px 20px 20px;
    box-sizing: border-box;
    transition: margin-left 0.3s ease;
}
.main.expanded { margin-left: 0; }

/* Table container */
.table-container {
    width: 900px;
    max-width: 95%;
    background: #fff;
    border-radius: 12px;
    padding: 20px 25px;
    box-sizing: border-box;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    margin: 0 auto;
}
.table-container .header-image {
    text-align: center;
    margin-bottom: 10px;
}
.table-container .header-image img {
    width:120px;
    height: auto;
}
.table-container h2 {
    margin: 10px 0 20px;
    color: black;
    text-align: center;
    font-size: 22px;
    font-weight: bold;
}
.clear-btn {
    display:inline-block;
    margin-top: 15px;
    padding:6px 12px;
    background:#FF4B5C;
    color:white;
    border:none;
    border-radius:5px;
    font-size:12px;
    font-weight:bold;
    cursor:pointer;
}
.clear-btn:hover { background:#d93a47; }

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
table th { background: #f1f1f1; color: #333; }
table tr:hover { background: #f9f9f9; }
.login { color:green; font-weight:bold; }
.logout { color:red; font-weight:bold; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>


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



<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>


<div class="main" id="mainContent">
    <div class="table-container">
        <div class="header-image">
            <img src="images/Wizara ya elimu.jpg" alt="Header Image">
        </div>
        <h2>User Login Logs</h2>
        <p style="text-align:center; margin:5px 0; font-size:14px; color:#555;">
            Total: <?php echo $total_logs; ?>
        </p>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Action</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if($total_logs > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td class="<?php echo $row['action']=='login'?'login':'logout'; ?>">
                                <?php echo ucfirst($row['action']); ?>
                            </td>
                            <td><?php echo $row['timestamp']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; color:#777;">No user logs found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- ✅ Clear Logs button moved BELOW table -->
        <form method="POST" onsubmit="return confirm('Are you sure you want to clear all logs? This action cannot be undone.');" style="text-align:center;">
            <button type="submit" name="clear_logs" class="clear-btn"><i class="fas fa-trash"></i> Clear Logs</button>
        </form>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById("menuToggle").addEventListener("click", function() {
    const sidebar = document.getElementById("sidebar");
    const mainContent = document.getElementById("mainContent");
    sidebar.classList.toggle("hidden");
    mainContent.classList.toggle("expanded");
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
