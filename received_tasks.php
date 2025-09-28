<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W2') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 🔹 Fetch unread messages count
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

// Fetch tasks including file_path
$stmt = $conn->prepare("SELECT title, description, deadline, file_path 
                        FROM tasks 
                        WHERE assigned_to = ? 
                        ORDER BY deadline ASC");
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$tasks = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee - New Tasks</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Body with background like W2 */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
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
    height: 150px;
    background-image: url('images/emblem.png');
    background-size: contain;
    background-repeat: no-repeat;
    opacity: 0.2;
    transform: translate(-50%, -50%);
    z-index: -1;
}

/* Sidebar same as W2 */
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

/* Header same as W2 */
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
.menu-toggle { font-size: 20px; cursor: pointer; }

/* Main content */
.main {
    flex: 1;
    display: flex;
    flex-direction: column;
    margin-left: 220px;
    padding: 30px;
    margin-top: 80px;
}
.main.expanded { margin-left: 0; }

/* Container */
.container {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 20px;
    align-items: center;
}
.container h2 {
    text-align: center;
    margin-bottom: 20px;
}

/* Task Card */
.task-card {
    background: linear-gradient(135deg, #ffffff, #f3f8ff);
    padding: 15px 20px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    max-width: 400px;
    text-align: center;
}
.task-card h3 { margin: 0; font-size: 16px; color: #1F5F60; }
.task-card p { margin: 0; font-size: 14px; color: #555; }
.task-card .deadline { font-weight: bold; color: #E74C3C; }

/* Responsive */
@media(max-width: 768px){
    .main { margin-left: 0; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.show { transform: translateX(0); }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>W2 Panel</h2>
    <a href="w2_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="received_tasks.php"><i class="fas fa-inbox"></i> Received Tasks</a>
    <a href="my_work.php"><i class="fas fa-folder-open"></i> My Work</a>
    <a href="w2_reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="assign_task_w2.php"><i class="fas fa-tasks"></i> Assign Task</a>
    <a href="approve_tasks_w2.php"><i class="fas fa-check-double"></i> Approve / Rollback</a>
    <a href="request_permission_w2.php"><i class="fas fa-user-check"></i> Request Permission</a>
</div>
<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Welcome, <?= htmlspecialchars($username) ?> (W2)</h1>
    <a href="logout.php" style="color:#fff;text-decoration:none;">Logout</a>
</div>

<!-- Main Content -->
<div class="main" id="mainContent">
    <div class="container">
        <h2>New Tasks Assigned</h2>
        <?php if (count($tasks) === 0): ?>
            <p>No new tasks available.</p>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
            <div class="task-card">
                <h3><?= htmlspecialchars($task['title']) ?></h3>
                
                <?php if (!empty($task['description'])): ?>
                    <p><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                <?php endif; ?>

                <?php if (!empty($task['deadline'])): ?>
                    <p class="deadline">Deadline: <?= date("d M Y, H:i", strtotime($task['deadline'])) ?></p>
                <?php endif; ?>

                <?php if (!empty($task['file_path'])): ?>
                    <p>
                        📂 <a href="<?= htmlspecialchars($task['file_path']) ?>" download style="color:#1581A1; font-weight:bold;">Download File</a>
                    </p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.getElementById("mainContent").classList.toggle("expanded");
});
</script>
</body>
</html>
