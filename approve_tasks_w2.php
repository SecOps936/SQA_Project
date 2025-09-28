<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W2') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle approve/rollback
$notification = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $task_id = intval($_POST['task_id']);
    $action = $_POST['action'];
    $comment = trim($_POST['comment'] ?? "");

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE completed_tasks 
                                SET status='approved', reviewed_by=?, reviewed_at=NOW() 
                                WHERE id=?");
        $stmt->bind_param("ii", $user_id, $task_id);
        $stmt->execute();
        $stmt->close();
        $notification = "✅ Task approved successfully.";
    } elseif ($action === 'rollback') {
        if (!empty($comment)) {
            $stmt = $conn->prepare("UPDATE completed_tasks 
                                    SET status='rolled_back', comment=?, reviewed_by=?, reviewed_at=NOW() 
                                    WHERE id=?");
            $stmt->bind_param("sii", $comment, $user_id, $task_id);
            $stmt->execute();
            $stmt->close();
            $notification = "⚠️ Task rolled back. Comment sent to employee.";
        } else {
            $notification = "❌ Please provide a comment to rollback.";
        }
    }
}

// Fetch tasks pending review
$sql = "SELECT ct.id, t.title, t.description, u.username AS employee_name, ct.file_path
        FROM completed_tasks ct
        JOIN tasks t ON ct.task_id = t.id
        JOIN users u ON ct.employee_id = u.id
        WHERE ct.status='pending'";
$tasks_result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Approve / Rollback - W2</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin:0;font-family:Arial,sans-serif;min-height:100vh;display:flex;flex-direction:column;
    background:url('images/Tanzania.jpg') center/cover no-repeat;position:relative;
}
body::before{content:"";position:absolute;inset:0;background:rgba(255,255,255,0.85);z-index:-1;}
body::after{content:"";position:absolute;top:50%;left:50%;width:120px;height:150px;
    background:url('images/emblem.png') no-repeat center/contain;opacity:0.2;
    transform:translate(-50%,-50%);z-index:-1;}

/* Sidebar */
.sidebar{width:220px;background:#1F5F60;color:white;display:flex;flex-direction:column;
    padding:20px 15px;box-shadow:2px 0 6px rgba(0,0,0,0.2);
    position:fixed;top:0;left:0;height:100%;z-index:900;}
.sidebar h2{margin-bottom:20px;font-size:18px;}
.sidebar a{color:white;text-decoration:none;padding:10px;margin:5px 0;border-radius:6px;display:block;}
.sidebar a:hover{background:rgba(255,255,255,0.2);}

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
/* Main */
.main{flex:1;margin-left:220px;padding:80px 20px;display:flex;flex-direction:column;
    align-items:center;text-align:center;}
.card{width:100%;max-width:600px;background:white;padding:15px;border-radius:12px;
    box-shadow:0 4px 10px rgba(0,0,0,0.1);margin-bottom:20px;text-align:left;}
.card h3{margin:0 0 10px;color:#333;}
.card p{margin:5px 0;color:#555;}
.card a.download{display:inline-block;margin:8px 0;padding:6px 12px;background:#1581A1;
    color:white;border-radius:6px;text-decoration:none;font-size:13px;}
.card a.download:hover{background:#0d6d85;}
button{padding:6px 12px;border:none;border-radius:6px;cursor:pointer;font-size:14px;}
button.approve{background:#28a745;color:white;}
button.approve:hover{background:#218838;}
button.rollback{background:#dc3545;color:white;margin-top:5px;}
button.rollback:hover{background:#c82333;}
textarea{width:100%;min-height:60px;margin-top:8px;border-radius:6px;padding:6px;
    border:1px solid #ccc;}
.notification{font-weight:bold;margin-bottom:15px;}
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
    <h1>Welcome, <?php echo htmlspecialchars($username); ?> (W2)</h1>
    <div class="profile-dropdown" id="profileDropdown">
        <img src="images/Wizara ya elimu.jpg" alt="Profile" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#" id="viewProfileBtn">View Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main -->
<div class="main">
    <h2>Pending Tasks for Review</h2>
    <?php if ($notification): ?><p class="notification"><?= $notification ?></p><?php endif; ?>

    <?php if ($tasks_result->num_rows > 0): ?>
        <?php while ($task = $tasks_result->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($task['title']) ?></h3>
                <p><?= htmlspecialchars($task['description']) ?></p>
                <p><strong>Employee:</strong> <?= htmlspecialchars($task['employee_name']) ?></p>
                <?php if (!empty($task['file_path'])): ?>
                    <a href="<?= htmlspecialchars($task['file_path']) ?>" class="download" download>📂 Download File</a>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <button type="submit" name="action" value="approve" class="approve">Approve</button>
                    <textarea name="comment" placeholder="Reason for rollback (required if rolling back)"></textarea>
                    <button type="submit" name="action" value="rollback" class="rollback">Rollback</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No tasks pending review.</p>
    <?php endif; ?>
</div>
</body>
</html>
