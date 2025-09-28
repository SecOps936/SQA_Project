<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch messages
$stmt = $conn->prepare("
    SELECT m.id, m.subject, m.message, m.created_at, m.reply, m.reply_at, m.is_read,
           a.username AS admin_name
    FROM messages m
    LEFT JOIN users a ON m.admin_id = a.id
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count unread
$stmt_unread = $conn->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE user_id = ? AND is_read = 0 AND admin_id IS NOT NULL");
$stmt_unread->bind_param("i", $user_id);
$stmt_unread->execute();
$res_unread = $stmt_unread->get_result();
$row_unread = $res_unread->fetch_assoc();
$unread_count = isset($row_unread['unread_count']) ? (int)$row_unread['unread_count'] : 0;
$stmt_unread->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Inbox</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Base */
body { margin: 0; font-family: Arial, sans-serif; min-height: 100vh;
       background-image: url('images/Tanzania.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; position: relative; }
body::before { content: ""; position: absolute; inset: 0; background: rgba(255,255,255,0.85); z-index: -1; }
body::after { content: ""; position: absolute; top: 50%; left: 50%; width: 120px; height: 120px; background-image: url('images/emblem.png'); background-size: contain; background-repeat: no-repeat; opacity: 0.2; transform: translate(-50%, -50%); z-index: -1; }

/* Sidebar */
.sidebar { width: 220px; background: #1F5F60; color: white; display: flex; flex-direction: column; padding: 20px 15px; box-shadow: 2px 0 6px rgba(0,0,0,0.2); transition: transform 0.3s ease; position: fixed; top: 0; left: 0; height: 100%; z-index: 900; }
.sidebar.hidden { transform: translateX(-100%); }
.sidebar h2 { margin: 0 0 20px; font-size: 18px; }
.sidebar a { color: white; text-decoration: none; padding: 10px; margin: 5px 0; border-radius: 6px; display: block; transition: background 0.3s ease; }
.sidebar a:hover { background: rgba(255,255,255,0.2); }

/* Header */
.header { position: fixed; top: 0; left: 0; width: 100%; height: 60px; padding: 10px 20px; background: #2C3E50; color: white; display: flex; align-items: center; z-index: 1000; box-sizing: border-box; }
.menu-toggle { font-size: 20px; cursor: pointer; margin-right: 15px; display: inline-block; }
.header-title { flex: 1; text-align: center; font-size: 20px; margin: 0; }
.logout-btn { background: #E74C3C; color: white; padding: 6px 14px; text-decoration: none; border-radius: 6px; font-size: 14px; transition: background 0.3s ease; }
.logout-btn:hover { background: #c0392b; }

/* Main */
.main { flex: 1; margin-left: 220px; padding: 80px 20px 20px; display: flex; flex-direction: column; align-items: center; }
.main.expanded { margin-left: 0; }
.main h2 { text-align: center; margin-bottom: 20px; }

/* Messages */
.message-box { background: #fff; border-radius: 10px; padding: 15px; margin: 15px auto; box-shadow: 0 3px 8px rgba(0,0,0,0.1); max-width: 600px; width: 100%; text-align: left; }
.message-box h4 { text-align: center; margin: 0 0 8px; font-size: 16px; color: #2c3e50; }
.message-box p { font-size: 14px; color: #444; }
.reply { margin-top: 10px; padding: 8px; background: #f3f9ff; border-left: 3px solid #1581A1; border-radius: 5px; color:#333; }
.message-box .meta { text-align: center; font-size: 12px; color: #888; margin-top: 5px; }
.status-tag { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; margin-left: 5px; }
.status-update { background: #f39c12; color: #fff; }
.status-replied { background: #27ae60; color: #fff; }

/* Responsive */
@media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main { margin-left: 0; } }

/* New message indicator */
.new-message-indicator { display: inline-block; width: 12px; height: 12px; background: red; border-radius: 50%; margin-left: 5px; animation: blink 1s infinite; }
@keyframes blink { 50% { opacity: 0; } }

/* small badge style used in previous suggestions */
.msg-indicator {
    display: inline-block;
    background: red;
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 50%;
    margin-left: 5px;
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Employee Panel</h2>
    <a href="employee_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="view_tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
    <a href="employee_messages.php"><i class="fas fa-envelope"></i> Messages
    <?php if ($unread_count > 0): ?>
        <span class="msg-indicator" style="animation: blink 1s infinite;"><?php echo $unread_count; ?></span>
    <?php endif; ?>
</a>
    <a href="ask_permission.php"><i class="fas fa-hand-paper"></i> Permission</a>
</div>

<!-- Header -->
<div class="header">
    <span id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1 class="header-title">Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Main -->
<div class="main" id="mainContent">
    <h2>Your Messages</h2>

    <?php if (empty($messages)): ?>
        <div class="no-messages">No messages yet.</div>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message-box">
                <h4>
                    <a href="view_message.php?id=<?php echo $msg['id']; ?>" style="color:#2c3e50;text-decoration:none;">
                        <?php echo htmlspecialchars($msg['subject']); ?>
                    </a>
                    <?php if (!empty($msg['reply'])): ?>
                        <?php if ($msg['is_read'] == 0): ?>
                            <span class="status-tag status-update">New Reply</span>
                        <?php else: ?>
                            <span class="status-tag status-replied">Replied</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="status-tag status-update">Update</span>
                    <?php endif; ?>
                </h4>
                <p><strong>You:</strong> <?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                <div class="meta">
                    Sent: <?php echo date("M d, Y H:i", strtotime($msg['created_at'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.getElementById("mainContent").classList.toggle("expanded");
});
</script>
</body>
</html>
