<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
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

// 🔹 Fetch tasks submitted by this employee with their status and title
$stmt = $conn->prepare("
    SELECT t.title, ct.status, ct.submitted_at, ct.file_path
    FROM completed_tasks ct
    JOIN tasks t ON ct.task_id = t.id
    WHERE ct.employee_id = ?
    ORDER BY ct.submitted_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$submitted_tasks = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Task Status</title>
<style>
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
}
.header-title { flex: 1; text-align: center; font-size: 20px; margin: 0; }
.logout-btn {
    background: #E74C3C;
    color: white;
    padding: 6px 14px;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
}
.logout-btn:hover { background: #c0392b; }

/* Main Content */
.main {
    flex: 1;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease;
    margin-left: 220px;
}
.main.expanded { margin-left: 0; }

.container {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    padding: 30px;
    margin-top: 80px;
}
h2 { text-align: center; }

/* Task Cards */
.task-card {
    background: linear-gradient(135deg, #ffffff, #f3f8ff);
    padding: 15px 20px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    width: 100%;
    max-width: 450px;
    text-align: center;
}
.task-card h3 { margin: 0; font-size: 16px; color: #1F5F60; }
.task-card p { margin: 5px 0; font-size: 14px; color: #555; }
.status {
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 6px;
    display: inline-block;
    color: white;
}
.status.pending { background: #f39c12; }
.status.approved { background: #27ae60; }
.status.rolledback { background: #c0392b; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
    <a href="edit_task.php"><i class="fas fa-edit"></i> Edit Task</a>
</div>


<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1 class="header-title">Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Main Content -->
<div class="main" id="mainContent">
    <div class="container">
        <h2>Your Task Status</h2>
        <?php if (count($submitted_tasks) === 0): ?>
            <p>No tasks submitted yet.</p>
        <?php else: ?>
            <?php foreach ($submitted_tasks as $task): ?>
    <div class="task-card">
        <h3><?= htmlspecialchars($task['title']) ?></h3>
        <p>Submitted At: <?= date("d M Y, H:i", strtotime($task['submitted_at'])) ?></p>
        <?php 
            $status_class = '';
            $status_text = htmlspecialchars($task['status']);
            if(strtolower($task['status']) === 'pending') $status_class = 'pending';
            elseif(strtolower($task['status']) === 'approved') $status_class = 'approved';
            elseif(strtolower($task['status']) === 'rolledback') $status_class = 'rolledback';
        ?>
        <p class="status <?= $status_class ?>"><?= $status_text ?></p>
        <?php if (!empty($task['review_comment']) && strtolower($task['status']) === 'rolledback'): ?>
            <p><strong>Rollback Comment:</strong> <?= htmlspecialchars($task['review_comment']) ?></p>
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
