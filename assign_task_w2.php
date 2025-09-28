<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W2') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch all employees except W1 and Admin
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role='employee'");
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assigned_to = intval($_POST['assigned_to']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $deadline = $_POST['deadline'];

    // File upload
    $file_path = null;
    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === 0) {
        $upload_dir = 'uploads/tasks/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['task_file']['name']);
        $file_path = $upload_dir . $file_name;
        move_uploaded_file($_FILES['task_file']['tmp_name'], $file_path);
    }

    $stmt = $conn->prepare("INSERT INTO tasks (uploaded_by, assigned_to, title, description, deadline, file_path) 
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $user_id, $assigned_to, $title, $description, $deadline, $file_path);
    if ($stmt->execute()) {
        $message = "Task assigned successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Task - W2</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    display: flex;
    background-image: url('images/Tanzania.jpg');
    background-size: cover;
    background-position: center;
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
    justify-content: space-between;
    align-items: center;
    z-index: 1000;
    box-sizing: border-box;
}
.header h1 { margin: 0; font-size: 20px; }
.menu-toggle { font-size: 20px; cursor: pointer; margin-right: 15px; display: inline-block; }

/* Profile Dropdown */
.profile-dropdown { position: relative; display: inline-block; }
.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50px;
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
    transition: background 0.2s ease;
}
.dropdown-menu a:hover { background: #f1f1f1; }

/* Main content */
.main {
    flex: 1;
    margin-left: 220px;
    padding: 100px 30px 30px 30px;
    transition: margin-left 0.3s ease;
}
.main.expanded { margin-left: 0; }

/* Form box */
.assign-form {
    max-width: 650px;
    margin: auto;
    background: rgba(255,255,255,0.95);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.assign-form h2 { text-align: center; margin: 0 0 15px 0; font-size: 24px; color: #1F5F60; }
.assign-form label { font-weight: bold; }
.assign-form input, .assign-form select, .assign-form textarea {
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    width: 100%;
    box-sizing: border-box;
}
.assign-form button {
    padding: 12px;
    background:#1581A1;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size: 16px;
}
.assign-form button:hover { background:#0056b3; }
.message { text-align: center; font-weight: bold; color: green; }

/* Responsive */
@media (max-width: 1024px) {
    .main { padding: 100px 20px 20px 20px; }
    .assign-form { max-width: 90%; padding: 20px; }
}
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
    .main { margin-left: 0; padding: 90px 15px 15px 15px; }
}
@media (max-width: 480px) {
    .header h1 { font-size: 16px; text-align: center; }
    .assign-form h2 { font-size: 20px; }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>W2 Panel</h2>
    <a href="w2_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="assign_task_w2.php" class="active"><i class="fas fa-tasks"></i> Assign Task</a>
    <a href="approve_tasks_w2.php"><i class="fas fa-check-double"></i> Approve / Rollback</a>
    <a href="request_permission_w2.php"><i class="fas fa-user-check"></i> Request Permission</a>
</div>

<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Welcome, <?= htmlspecialchars($username) ?> (W2)</h1>
    <div class="profile-dropdown">
        <img src="images/Wizara ya elimu.jpg" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#">View Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main -->
<div class="main">
    <form class="assign-form" method="POST" enctype="multipart/form-data">
        <h2>Assign New Task</h2>
        <?php if ($message): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <label for="assigned_to">Assign To:</label>
        <select name="assigned_to" id="assigned_to" required>
            <option value="">--Select Employee--</option>
            <?php foreach($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['username']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="title">Task Title:</label>
        <input type="text" name="title" id="title" required>

        <label for="description">Task Description:</label>
        <textarea name="description" id="description" rows="5"></textarea>

        <label for="deadline">Deadline:</label>
        <input type="datetime-local" name="deadline" id="deadline" required>

        <label for="task_file">Attach File (optional):</label>
        <input type="file" name="task_file" id="task_file">

        <button type="submit">Assign Task</button>
    </form>
</div>

<script>
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.querySelector(".main").classList.toggle("expanded");
});
const profileIcon = document.getElementById("profileIcon");
const dropdownMenu = document.getElementById("dropdownMenu");
profileIcon.addEventListener("click", () => {
    dropdownMenu.style.display = dropdownMenu.style.display === "block" ? "none" : "block";
});
window.addEventListener("click", (e) => {
    if (!profileIcon.contains(e.target)) dropdownMenu.style.display = "none";
});
</script>
</body>
</html>
