<?php
session_start();
require 'db.php';

// Ensure user is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$success = '';
$error = '';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($subject) || empty($message)) {
        $error = "Subject and message cannot be empty.";
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (user_id, subject, message, status, is_read, created_at) VALUES (?, ?, ?, 'pending', 0, NOW())");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $subject, $message);
            if ($stmt->execute()) {
                $success = "Message sent successfully!";
            } else {
                $error = "Failed to send message.";
            }
            $stmt->close();
        } else {
            $error = "Database error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Help - Send Message to Admin</title>
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
    display: inline-block;
}
.header-title {
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

/* Main content */
.main {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    transition: margin-left 0.3s ease;
    margin-left: 220px;
    padding: 100px 20px 30px 20px; /* push down under header */
}
.main.expanded { margin-left: 0; }

/* Container */
.container {
    width: 100%;
    max-width: 600px;
    background: #fff;
    padding: 40px 30px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}
.container h2 { text-align: center; margin-bottom: 25px; color: #1F5F60; }
.container form { display: flex; flex-direction: column; gap: 15px; }
.container input, .container textarea {
    width: 100%;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    resize: vertical;
    font-size: 14px;
}
.container button {
    padding: 12px;
    background: #1581A1;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
}
.container button:hover { background: #0056b3; }
.success { color: green; text-align: center; margin-bottom: 10px; }
.error { color: red; text-align: center; margin-bottom: 10px; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Sidebar -->
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Employee Panel</h2>
    <a href="employee_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="view_tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
    <a href="employee_messages.php"><i class="fas fa-envelope"></i> Messages
        <?php if ($unread_count > 0): ?>
            <span class="msg-indicator"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="ask_permission.php"><i class="fas fa-hand-paper"></i> Permission</a>
</div>


<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1 class="header-title">Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Main content -->
<div class="main" id="mainContent">
    <div class="container">
        <h2>Send Message to Admin</h2>
        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <input type="text" name="subject" placeholder="Subject" required>
            <textarea name="message" rows="6" placeholder="Write your message here..." required></textarea>
            <button type="submit">Send Message</button>
        </form>
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
