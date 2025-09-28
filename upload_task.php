<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch unread messages
$stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE user_id=? AND is_read=0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$unread_count = $row['unread_count'] ?? 0;
$stmt->close();

$message = "";

// Handle file upload
if (isset($_POST['upload'])) {
    $task_id = $_POST['task_id'];
    $allowed = ['pdf','doc','docx','jpg','jpeg','png'];
    $recipient_role = "W2";

    // Get W2 user_id
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = ? LIMIT 1");
    $stmt->bind_param("s", $recipient_role);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res->num_rows === 0) {
        $message = "❌ Recipient with role $recipient_role not found.";
    } else {
        $recipient_data = $res->fetch_assoc();
        $recipient_id = $recipient_data['id'];

        // Check for duplicate submission
        $stmt = $conn->prepare("SELECT id, status FROM completed_tasks 
                                WHERE task_id=? AND employee_id=? AND reviewed_by=? 
                                ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("iii", $task_id, $user_id, $recipient_id);
        $stmt->execute();
        $lastSubmission = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($lastSubmission && $lastSubmission['status'] != 'rolled_back') {
            $message = "⚠️ You already submitted this task to $recipient_role (Status: {$lastSubmission['status']}).";
        } else {
            if (!empty($_FILES['task_file']['name'])) {
                $fileName = $_FILES['task_file']['name'];
                $fileTmp = $_FILES['task_file']['tmp_name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (in_array($fileExt, $allowed)) {
                    $newName = uniqid() . "." . $fileExt;
                    $uploadDir = "uploads/completed_tasks/";
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                    $filePath = $uploadDir . $newName;
                    if (move_uploaded_file($fileTmp, $filePath)) {
                        $stmt = $conn->prepare("INSERT INTO completed_tasks (task_id, employee_id, file_path, reviewed_by) VALUES (?,?,?,?)");
                        $stmt->bind_param("iiss", $task_id, $user_id, $filePath, $recipient_id);
                        if ($stmt->execute()) {
                            $message = "✅ Task uploaded successfully to $recipient_role.";
                        } else {
                            $message = "❌ Database error.";
                        }
                        $stmt->close();
                    } else {
                        $message = "❌ Failed to upload file.";
                    }
                } else {
                    $message = "❌ Only PDF, DOC, DOCX, JPG, JPEG, PNG files are allowed.";
                }
            } else {
                $message = "❌ Please select a file to upload.";
            }
        }
    }
}

// Fetch tasks assigned to employee
$stmt = $conn->prepare("SELECT id, title FROM tasks WHERE assigned_to=?");
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
<title>Upload Task</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { margin:0; font-family:Arial,sans-serif; min-height:100vh; display:flex;
    background-image: url('images/Tanzania.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; position: relative; overflow: hidden; }
body::before { content: ""; position: absolute; inset: 0; background: rgba(255,255,255,0.85); z-index: -1; }
body::after { content: ""; position: absolute; top:50%; left:50%; width:120px; height:120px; background-image:url('images/emblem.png'); background-size:contain; background-repeat:no-repeat; opacity:0.2; transform:translate(-50%,-50%); z-index:-1; }

.sidebar { width:220px; background:#1F5F60; color:white; display:flex; flex-direction:column; padding:20px 15px; box-shadow:2px 0 6px rgba(0,0,0,0.2); position:fixed; top:0; left:0; height:100%; z-index:900; }
.sidebar.hidden { transform: translateX(-100%); }
.sidebar h2 { margin:0 0 20px; font-size:18px; }
.sidebar a { color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; display:block; transition:background 0.3s ease; }
.sidebar a:hover { background: rgba(255,255,255,0.2); }
.msg-indicator { background:red; color:white; padding:2px 6px; border-radius:50%; font-size:12px; margin-left:6px; }

.header { position:fixed; top:0; left:0; width:100%; height:60px; padding:10px 20px; background:#2C3E50; color:white; display:flex; align-items:center; z-index:1000; box-sizing:border-box; }
.menu-toggle { font-size:20px; cursor:pointer; margin-right:15px; display:inline-block; }
.header-title { flex:1; text-align:center; font-size:20px; margin:0; }
.logout-btn { background:#E74C3C; color:white; padding:6px 14px; text-decoration:none; border-radius:6px; font-size:14px; transition:background 0.3s ease; }
.logout-btn:hover { background:#c0392b; }

.main { flex:1; display:flex; justify-content:center; align-items:flex-start; margin-left:220px; padding:40px 20px 20px 20px; margin-top:80px; min-height:calc(100vh - 80px); }
.container { width:100%; max-width:600px; background:linear-gradient(135deg,#f0f4ff,#ffffff); padding:30px; border-radius:15px; box-shadow:0 10px 25px rgba(0,0,0,0.15); text-align:center; }
h2 { text-align:center; color:#2C3E50; margin-bottom:25px; font-size:26px; }

input, select, button { width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box; font-size:14px; }
button { background:#1F5F60; color:white; border:none; cursor:pointer; font-size:16px; transition:background 0.3s ease, transform 0.2s ease; }
button:hover { background:#1581A1; transform:scale(1.05); }

.message { margin-bottom:15px; padding:10px; border-radius:6px; font-weight:bold; }
.success { color:green; border-left:4px solid #28a745; background:#d4edda; }
.error { color:red; border-left:4px solid #dc3545; background:#f8d7da; }

@media (max-width:768px){
    .main { margin-left:0; padding:20px 10px; }
    .container { padding:20px; width:100%; }
    .header-title { font-size:18px; }
    .sidebar { width:180px; padding:15px; }
    .sidebar a { font-size:14px; padding:8px; }
    button { width:100%; padding:10px; }
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
        <h2>Upload Completed Task</h2>
        <?php if($message): ?>
            <div class="message <?php echo strpos($message,'✅')!==false?'success':'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label>Select Task</label>
            <select name="task_id" required>
                <option value="">-- Select Task --</option>
                <?php foreach($tasks as $task): ?>
                    <option value="<?php echo $task['id']; ?>"><?php echo htmlspecialchars($task['title']); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Upload File</label>
            <input type="file" name="task_file" required>

            <button type="submit" name="upload">Submit Task (to W2)</button>
        </form>
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
