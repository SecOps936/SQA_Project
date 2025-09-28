<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch unread messages count
$stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE user_id = ? AND is_read = 0");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unread_count = $row['unread_count'] ?? 0;
    $stmt->close();
} else {
    $unread_count = 0;
}

// Check if we need to mark tasks as read
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 1) {
    $updateStmt = $conn->prepare("UPDATE completed_tasks SET is_read = 1 WHERE employee_id = ? AND status = 'rolled_back' AND is_read = 0");
    $updateStmt->bind_param("i", $user_id);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Redirect to remove the mark_read parameter
    header("Location: edit_task.php");
    exit();
}

// Fetch rolled back tasks that haven't been read yet
$stmt = $conn->prepare("SELECT id AS completed_id, task_id, submitted_at, file_path, comment 
                        FROM completed_tasks 
                        WHERE employee_id=? AND status='rolled_back' AND is_read = 0
                        ORDER BY submitted_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rolledback_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count unread rolled back tasks
$unread_rolledback = count($rolledback_tasks);
$hasRolledBack = $unread_rolledback > 0;

// Fetch user info for profile dropdown
$result = $conn->query("SELECT username, email, role FROM users WHERE id = $user_id");
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Your Tasks</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Body & Background */
body {
    margin: 0;
    font-family: Arial,sans-serif;
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
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    z-index: 900;
    box-shadow: 2px 0 6px rgba(0,0,0,0.2);
    transition: transform 0.3s ease;
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

/* Main */
.main {
    flex: 1;
    margin-left: 220px;
    transition: margin-left 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
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
.menu-toggle { font-size: 20px; cursor: pointer; margin-right: 15px; }

/* Profile Dropdown */
.profile-dropdown { position: relative; display: inline-block; }
.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid #fff;
    object-fit: cover;
}
.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 50px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 160px;
    z-index: 2000;
}
.dropdown-menu a {
    display: block;
    padding: 10px 15px;
    color: #333;
    text-decoration: none;
}
.dropdown-menu a:hover { background: #f1f1f1; }

/* Container & Task Cards */
.container { flex:1; padding:30px; margin-top:80px; display:flex; flex-direction:column; gap:20px; }
.task-card {
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 6px 15px rgba(0,0,0,0.08);
    width:100%;
    max-width:600px;
    margin:0 auto;
}
.task-card h3 { margin:0; font-size:16px; color:#1F5F60; }
.task-card .comment { background:#f7f7f7; padding:8px 12px; border-radius:6px; color:#c0392b; margin:8px 0; }
.task-card a.download { display:inline-block; margin:8px 0; padding:6px 12px; background:#1581A1; color:white; border-radius:6px; text-decoration:none; font-size:13px; }
.task-card a.download:hover { background:#0d6d85; }
.task-card form { display:flex; flex-direction:column; gap:8px; margin-top:8px; }
.task-card button { padding:8px 14px; background:#1581A1; color:white; border:none; border-radius:6px; cursor:pointer; }
.task-card button:hover { background:#0056b3; }

/* Blink Indicator */
.blink { display:inline-block; width:12px; height:12px; border-radius:50%; background:red; animation: blink 1s infinite; margin-left:5px; vertical-align:middle;}
@keyframes blink{0%,50%,100%{opacity:1;}25%,75%{opacity:0;}}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Employee Panel</h2>
    <a href="employee_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="view_tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
    <a href="ask_permission.php"><i class="fas fa-hand-paper"></i> Permission</a>
    <a href="upload_task.php"><i class="fas fa-upload"></i> Upload Task</a>
    <a href="task_status.php"><i class="fas fa-check-circle"></i> Task Status</a>
    <a href="edit_task.php" id="editTaskLink"><i class="fas fa-edit"></i> Edit Task<?php if($unread_rolledback > 0): ?><span id="notificationIndicator" class="blink"></span><?php endif; ?></a>
</div>

<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h2>Welcome, <?= htmlspecialchars($username) ?></h2>
    <div class="profile-dropdown" id="profileDropdown">
        <img src="images/Wizara ya elimu.jpg" alt="Profile" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#" id="viewProfileBtn">View Profile</a>
            <a href="#" id="updateProfileBtn">Update Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main" id="mainContent">
    <div class="container">
        <h2>Edit Your Tasks</h2>

        <?php if (!$hasRolledBack): ?>
            <p>No tasks need editing.</p>
        <?php else: ?>
            <?php foreach ($rolledback_tasks as $task): ?>
                <div class="task-card" data-id="<?= $task['completed_id'] ?>">
                    <h3>Task ID: <?= htmlspecialchars($task['task_id']) ?></h3>
                    <p>Submitted At: <?= date("d M Y, H:i", strtotime($task['submitted_at'])) ?></p>
                    <?php if (!empty($task['comment'])): ?>
                        <p class="comment"><strong>Comment from W1:</strong> <?= nl2br(htmlspecialchars($task['comment'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($task['file_path'])): ?>
                        <a href="<?= htmlspecialchars($task['file_path']) ?>" class="download" download>📂 Download Previous File</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button id="markAsReadBtn" style="margin-top: 20px; padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer;">
                Mark All as Read
            </button>
        <?php endif; ?>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.getElementById("mainContent").classList.toggle("expanded");
});

// Profile dropdown
const profileDropdown = document.getElementById("profileDropdown");
const profileIcon = document.getElementById("profileIcon");
const dropdownMenu = document.getElementById("dropdownMenu");
profileIcon.addEventListener("click", function(e){
    dropdownMenu.style.display = (dropdownMenu.style.display==='block')?'none':'block';
    e.stopPropagation();
});
window.addEventListener("click", function(e){ if(!profileDropdown.contains(e.target)) dropdownMenu.style.display="none"; });

// Mark tasks as read
document.getElementById("markAsReadBtn")?.addEventListener("click", function() {
    // Redirect with mark_read parameter
    window.location.href = "edit_task.php?mark_read=1";
});

// Show notification function
function showNotification(message, type = "success") {
    const notification = document.createElement("div");
    notification.className = "notification";
    notification.textContent = message;
    
    if (type === "error") {
        notification.style.background = "#f44336";
        notification.style.color = "white";
    } else {
        notification.style.background = "#4CAF50";
        notification.style.color = "white";
    }
    
    // Style the notification
    notification.style.position = "fixed";
    notification.style.top = "80px";
    notification.style.right = "20px";
    notification.style.padding = "12px 18px";
    notification.style.borderRadius = "6px";
    notification.style.boxShadow = "0 4px 10px rgba(0,0,0,0.1)";
    notification.style.opacity = "0";
    notification.style.transform = "translateY(-10px)";
    notification.style.transition = "opacity 0.5s ease, transform 0.5s ease";
    notification.style.zIndex = "3000";
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.style.opacity = "1";
        notification.style.transform = "translateY(0)";
    }, 10);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = "0";
        notification.style.transform = "translateY(-10px)";
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 3000);
}
</script>

</body>
</html>