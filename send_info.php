<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require 'db.php';

$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message_status = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $recipients = $_POST['recipients']; // array (can be "all" or user ids)

    if (!empty($message) && !empty($subject)) {
        $created_at = date("Y-m-d H:i:s");
        $status = "unread";
        $is_read = 0;

       if (in_array("all", $recipients)) {
    // Send to ALL employees except the admin
    $allUsers = $conn->query("SELECT id FROM users WHERE role='employee' AND id != $admin_id");
    $stmt = $conn->prepare("INSERT INTO messages 
        (user_id, subject, message, admin_id, is_read, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    while ($u = $allUsers->fetch_assoc()) {
        $uid = $u['id'];
        $stmt->bind_param("issisis", $uid, $subject, $message, $admin_id, $is_read, $status, $created_at);
        $stmt->execute();
    }
    $stmt->close();
    $message_status = "Message sent to ALL employees.";
} else {
    // Send to selected users
    $stmt = $conn->prepare("INSERT INTO messages 
        (user_id, subject, message, admin_id, is_read, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($recipients as $user_id) {
        $stmt->bind_param("issisis", $user_id, $subject, $message, $admin_id, $is_read, $status, $created_at);
        $stmt->execute();
    }
    $stmt->close();
    $message_status = "Message sent to selected users.";
}
    } else {
        $message_status = "Subject and message cannot be empty.";
    }
}

// Fetch users for dropdown
$users = $conn->query("SELECT id, username, email FROM users WHERE role='employee' ORDER BY username ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Send Information</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Background */
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
body::before {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.85);
    z-index: -1;
}
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
    opacity: 0.15;
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
    align-items: center;
    z-index: 1000;
    box-sizing: border-box;
}
.menu-toggle {
    font-size: 20px;
    cursor: pointer;
    margin-right: 15px;
    display: inline-block;
}
.header h2 {
    flex: 1;
    text-align: center;
    font-size: 20px;
    margin: 0;
}
.logout-btn {
    background: #E74C3C;
    color: white;
    padding: 6px 14px;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    transition: background 0.3s ease;
}
.logout-btn:hover { background: #c0392b; }

/* Main */
.main {
    flex: 1;
    margin-left: 220px;
    padding: 80px 20px 20px;
}
.main.expanded { margin-left: 0; }

/* Form container */
.container {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    max-width: 700px;
    margin: auto;
    box-shadow: 0 3px 8px rgba(0,0,0,0.15);
}
.container img {
    width: 120px;
    display: block;
    margin: 0 auto 15px;
}
.container h2 {
    text-align: center;
    color: #2C3E50;
}

/* Form */
textarea {
    width: 100%;
    height: 150px;
    padding: 10px;
    margin: 8px 0 15px;
    border-radius: 6px;
    border: 1px solid #ccc;
    resize: vertical;
}
select {
    width: 100%;
    padding: 10px;
    margin: 8px 0 15px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
button {
    background: #1F5F60;
    color: white;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.3s ease;
}
button:hover { background: #1581A1; }

/* Message status */
.message-status {
    margin-top: 15px;
    padding: 10px;
    background: #f3f9ff;
    border-left: 4px solid #1581A1;
    border-radius: 6px;
    font-size: 14px;
    color: #333;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main { margin-left: 0; }
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
    <h2>Welcome, <?php echo htmlspecialchars($username); ?></h2>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Main -->
<div class="main" id="mainContent">
    <div class="container">
        <img src="images/Wizara ya elimu.jpg" alt="Emblem">
        <h2 style="margin-bottom:20px;">Send Information to Users</h2>
        
        <form method="POST">
            <label for="subject">Subject:</label><br>
            <input type="text" name="subject" id="subject" required 
                   style="width:100%; padding:10px; margin-bottom:10px; border-radius:6px; border:1px solid #ccc;"><br>

            <label for="message">Message:</label><br>
            <textarea name="message" id="message" required></textarea><br>

            <label for="recipients">Select Recipients:</label><br>
            <select name="recipients[]" id="recipients" multiple required>
                <option value="all">All Employees</option>
                <?php while ($row = $users->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>">
                        <?php echo htmlspecialchars($row['username']) . " (" . htmlspecialchars($row['email']) . ")"; ?>
                    </option>
                <?php endwhile; ?>
            </select><br>

            <button type="submit">Send Message</button>
        </form>

        <?php if ($message_status): ?>
            <div class="message-status"><?php echo htmlspecialchars($message_status); ?></div>
        <?php endif; ?>
    </div>
</div>

<script>
function checkUnread() {
    fetch('check_unread.php')
        .then(r => r.json())
        .then(data => {
            const indicator = document.getElementById("msgIndicator");
            if (data.count > 0) { indicator.className = "notification-indicator"; }
            else { indicator.className = ""; }
        });
}
checkUnread();
setInterval(checkUnread, 5000);
document.getElementById("menuToggle").addEventListener("click", () => {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.getElementById("mainContent").classList.toggle("expanded");
});
</script>
</body>
</html>
