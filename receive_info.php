<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require 'db.php';
$username = $_SESSION['username'];

// Mark message as read
if (isset($_GET['read'])) {
    $msg_id = intval($_GET['read']);
    $conn->query("UPDATE messages SET is_read = 1 WHERE id = $msg_id");
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $msg_id = intval($_POST['msg_id']);
    $reply = trim($_POST['reply']);
    $stmt = $conn->prepare("UPDATE messages SET reply=? WHERE id=?");
    $stmt->bind_param("si", $reply, $msg_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Reply sent successfully!'); window.location='receive_info.php';</script>";
    exit();
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $msg_id = intval($_POST['msg_id']);
    $stmt = $conn->prepare("DELETE FROM messages WHERE id=?");
    $stmt->bind_param("i", $msg_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Message deleted successfully!'); window.location='receive_info.php';</script>";
    exit();
}

// ✅ Unread count for blinking indicator (only messages from employees)
$unread_count = $conn->query("
    SELECT COUNT(*) as c 
    FROM messages 
    WHERE is_read=0 AND admin_id IS NULL
")->fetch_assoc()['c'];

// ✅ Fetch only messages sent by employees
$messages = $conn->query("
    SELECT m.id, m.message, m.reply, m.is_read, m.created_at, u.username, u.email, u.role
    FROM messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.admin_id IS NULL
    ORDER BY m.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Receive Info</title>
<style>
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
    color: white; text-decoration: none; padding: 10px;
    margin: 5px 0; border-radius: 6px; display: block;
    transition: background 0.3s ease;
}
.sidebar a:hover { background: rgba(255,255,255,0.2); }

/* Header */
.header {
    position: fixed; top: 0; left: 0; width: 100%; height: 60px;
    padding: 10px 20px; background: #2C3E50; color: white;
    display: flex; justify-content: space-between; align-items: center;
    z-index: 1000; box-sizing: border-box;
}
.header h1 { margin: 0; font-size: 20px; }
.menu-toggle { font-size: 20px; cursor: pointer; margin-right: 15px; }

/* Logout */
.logout-btn {
    padding: 8px 15px; background: #FF4B5C; color: white;
    text-decoration: none; border-radius: 6px; font-size: 14px;
    font-weight: bold; transition: background 0.3s ease;
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

/* Main */
.main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: margin-left 0.3s ease;
    margin-left: 220px;
    padding: 20px;
    margin-top: 80px;
}
.main.expanded { margin-left: 0; }

/* Messages container */
.messages-container { width: 100%; max-width: 700px; }

/* Card */
.message-card {
    background:#fff; padding:15px; margin-bottom:15px;
    border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.1);
}
.message-card.unread { border-left:5px solid #007BFF; }
.message-header { font-weight:bold; margin-bottom:8px; }
.message-content { margin:10px 0; }
.reply {
    margin-top:10px; padding:10px; background:#f1f1f1;
    border-radius:6px; color:#333;
}
.reply-form textarea {
    width:100%; padding:8px; border-radius:6px;
    border:1px solid #ccc; resize:vertical;
}
.reply-form button {
    margin-top:8px; padding:6px 12px; border:none;
    border-radius:6px; background:#28a745; color:white; cursor:pointer;
}
.reply-form button:hover { background:#218838; }

/* Delete button */
.delete-btn {
    margin-top:10px;
    padding:6px 12px;
    border:none;
    border-radius:6px;
    background:#dc3545;
    color:white;
    cursor:pointer;
}
.delete-btn:hover { background:#c82333; }

/* Empty state */
.no-messages {
    text-align: center;
    margin-top: 50px;
    font-size: 18px;
    color: #555;
}
.no-messages i {
    font-size: 40px;
    color: #999;
    margin-bottom: 10px;
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

<!-- Main -->
<div class="main" id="mainContent">
    <h2>Messages</h2>
    <div class="messages-container">
    <?php if ($messages->num_rows === 0): ?>
        <div class="no-messages">
            <i class="fas fa-envelope-open-text"></i><br>
             No new messages 
        </div>
    <?php else: ?>
        <?php while ($row = $messages->fetch_assoc()): ?>
            <div class="message-card <?php echo $row['is_read'] ? '' : 'unread'; ?>">
                <div class="message-header">
                    From: <?php echo htmlspecialchars($row['username']); ?> (<?php echo htmlspecialchars($row['role']); ?>)
                    <small style="color:#777; float:right;"><?php echo $row['created_at']; ?></small>
                </div>
                <div class="message-content"><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>

                <?php if ($row['reply']): ?>
                    <div class="reply"><b>Your Reply:</b><br><?php echo nl2br(htmlspecialchars($row['reply'])); ?></div>
                <?php else: ?>
                    <form method="POST" class="reply-form">
                        <textarea name="reply" rows="2" required></textarea>
                        <input type="hidden" name="msg_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="reply_message">Send Reply</button>
                    </form>
                <?php endif; ?>

                <?php if (!$row['is_read']): ?>
                    <div style="margin-top:5px;">
                        <a href="receive_info.php?read=<?php echo $row['id']; ?>" style="color:#007BFF;">Mark as Read</a>
                    </div>
                <?php endif; ?>

                <!-- Delete button -->
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this message?');">
                    <input type="hidden" name="msg_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="delete_message" class="delete-btn">Delete Message</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
    </div>
</div>

<script>
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.getElementById("mainContent").classList.toggle("expanded");
});
</script>

</body>
</html>
