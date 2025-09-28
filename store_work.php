<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 🔹 Fetch unread messages count for sidebar
$stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($unread_count);
$stmt->fetch();
$stmt->close();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['work_file'])) {
    $file = $_FILES['work_file'];

    // Allowed file types
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if ($file['error'] === 0) {
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $filename = time() . "_" . basename($file['name']);
            $targetFile = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                $stmt = $conn->prepare("INSERT INTO stored_work (user_id, file_name, file_path, uploaded_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iss", $user_id, $file['name'], $targetFile);
                $stmt->execute();
                $stmt->close();

                $_SESSION['message'] = "File uploaded successfully!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $_SESSION['message'] = "Failed to upload file.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $_SESSION['message'] = "File type not allowed. Only PDF, JPEG, PNG, DOC, DOCX are allowed.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        $_SESSION['message'] = "Error uploading file. Code: " . $file['error'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Show message once
$message = "";
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Fetch user files
$stmt = $conn->prepare("SELECT id, file_name, file_path, uploaded_at FROM stored_work WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$files = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store Your Work</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Same style you already had */
body { margin: 0; font-family: Arial, sans-serif; min-height: 100vh; display: flex;
    background-image: url('images/Tanzania.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; position: relative; overflow: hidden; }
body::before { content: ""; position: absolute; inset: 0; background: rgba(255,255,255,0.85); z-index: -1; }
body::after { content: ""; position: absolute; top: 50%; left: 50%; width: 120px; height: 120px; background-image: url('images/emblem.png'); background-size: contain; background-repeat: no-repeat; opacity: 0.2; transform: translate(-50%, -50%); z-index: -1; }

.sidebar { width: 220px; background: #1F5F60; color: white; display: flex; flex-direction: column; padding: 20px 15px; box-shadow: 2px 0 6px rgba(0,0,0,0.2); transition: transform 0.3s ease; position: fixed; top: 0; left: 0; height: 100%; z-index: 900; }
.sidebar.hidden { transform: translateX(-100%); }
.sidebar h2 { margin: 0 0 20px; font-size: 18px; }
.sidebar a { color: white; text-decoration: none; padding: 10px; margin: 5px 0; border-radius: 6px; display: block; transition: background 0.3s ease; }
.sidebar a:hover { background: rgba(255,255,255,0.2); }
.msg-indicator { background: red; color: white; padding: 2px 6px; border-radius: 50%; font-size: 12px; margin-left: 6px; }

.header { position: fixed; top: 0; left: 0; width: 100%; height: 60px; padding: 10px 20px; background: #2C3E50; color: white; display: flex; align-items: center; z-index: 1000; box-sizing: border-box; }
.menu-toggle { font-size: 20px; cursor: pointer; margin-right: 15px; display: inline-block; }
.header-title { flex: 1; text-align: center; font-size: 20px; margin: 0; }
.logout-btn { background: #E74C3C; color: white; padding: 6px 14px; text-decoration: none; border-radius: 6px; font-size: 14px; transition: background 0.3s ease; }
.logout-btn:hover { background: #c0392b; }

.main { flex: 1; display: flex; justify-content: center; align-items: flex-start; margin-left: 220px; padding: 40px 20px 20px 20px; margin-top: 80px; min-height: calc(100vh - 80px); }
.container { width: 100%; max-width: 600px; background: linear-gradient(135deg, #f0f4ff, #ffffff); padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); text-align: center; }
h2 { text-align: center; color: #2C3E50; margin-bottom: 25px; font-size: 26px; }

button { background: #1F5F60; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; transition: background 0.3s ease, transform 0.2s ease; }
button:hover { background: #1581A1; transform: scale(1.05); }

.file-list { margin-top: 30px; text-align: left; }
.file-item { padding: 12px; border-bottom: 1px solid #ccc; display: flex; justify-content: space-between; align-items: center; }
.file-item a { background: #1581A1; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; transition: background 0.3s ease; }
.file-item a:hover { background: #0056b3; }

.message { margin-bottom: 15px; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 6px; color: #155724; }

@media (max-width: 768px) {
    .main { margin-left: 0; padding: 20px 10px; }
    .container { padding: 20px; width: 100%; }
    .header-title { font-size: 18px; }
    .sidebar { width: 180px; padding: 15px; }
    .sidebar a { font-size: 14px; padding: 8px; }
    button { width: 100%; padding: 10px; }
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="sidebar" id="sidebar">
    <h2>Employee Panel</h2>
   <a href="employee_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="view_tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
    <a href="ask_permission.php"><i class="fas fa-hand-paper"></i> Permission</a>
     <a href="upload_task.php"><i class="fas fa-upload"></i> Upload Task</a>
    <a href="task_status.php"><i class="fas fa-check-circle"></i> Task Status</a>
    <a href="edit_task.php"><i class="fas fa-edit"></i> Edit Task</a>
</div>

<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1 class="header-title">Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="main" id="mainContent">
    <div class="container">
        <h2>Store Your Work</h2>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="work_file" required>
            <button type="submit">Upload</button>
        </form>

        <div class="file-list">
            <h3>Your Files:</h3>
            <?php if ($files): ?>
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <span><?php echo htmlspecialchars($file['file_name']); ?></span>
                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download>Download</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No files uploaded yet.</p>
            <?php endif; ?>
        </div>
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
