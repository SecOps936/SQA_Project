<?php
session_start();
require 'db.php';

// ✅ Role check for W2
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W2') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['work_file'])) {
    $file = $_FILES['work_file'];

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
                // ✅ Save under stored_work table
                $stmt = $conn->prepare("INSERT INTO stored_work (user_id, file_name, file_path, uploaded_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iss", $user_id, $file['name'], $targetFile);
                $stmt->execute();
                $stmt->close();

                $_SESSION['message'] = "File uploaded successfully!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $_SESSION['message'] = "Failed to upload file.";
            }
        } else {
            $_SESSION['message'] = "File type not allowed. Only PDF, JPEG, PNG, DOC, DOCX.";
        }
    } else {
        $_SESSION['message'] = "Error uploading file. Code: " . $file['error'];
    }
}

// Show message once
$message = "";
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Fetch W2’s uploaded files
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
<title>W2 - My Work</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Use same sidebar/header style */
body { margin: 0; font-family: Arial, sans-serif; min-height: 100vh; display: flex;
    background-image: url('images/Tanzania.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; position: relative; overflow: hidden; }
body::before { content: ""; position: absolute; inset: 0; background: rgba(255,255,255,0.85); z-index: -1; }
body::after { content: ""; position: absolute; top: 50%; left: 50%; width: 120px; height: 120px; background-image: url('images/emblem.png'); background-size: contain; background-repeat: no-repeat; opacity: 0.2; transform: translate(-50%, -50%); z-index: -1; }


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

/* Main content */
.main {
    flex: 1;
    display: flex;
    flex-direction: column;
    margin-left: 220px;
    overflow: hidden;          /* no scroll inside main */
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
.header h1 { margin: 0; font-size: 20px; }
.menu-toggle { font-size: 20px; cursor: pointer; margin-right: 15px; display: inline-block; }

/* Profile Dropdown */
.profile-dropdown { position: relative; display: inline-block; }
.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50px;        /* fully circular */
    cursor: pointer;
    border: 2px solid #fff;
    object-fit: cover;         /* ensures image fills circle */
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
.container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
.message { background: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
.file-list { margin-top: 40px; text-align: left; }
.file-item { padding: 20px; border-bottom: 1px solid #ccc; display: flex; justify-content: space-between; align-items: center; }
.file-item a { background: #1581A1; color: white; padding: 10px 12px; border-radius: 6px; text-decoration: none; transition: background 0.3s ease; }
.file-item a:hover { background: #0056b3; }
@media (max-width: 768px) {
    /* Sidebar collapses by default */
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.hidden {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }

    .main {
        margin-left: 0;
    }

    .header h1 {
        font-size: 16px;
    }

    .card-row {
        justify-content: center;
        gap: 20px;
    }
    .card {
        max-width: 90%;
        height: auto;
    }

    .modal-content {
        width: 90%;
        margin: 20% auto;
    }
}

@media (max-width: 480px) {
    .header {
        flex-direction: column;
        height: auto;
        padding: 10px;
        text-align: center;
    }

    .header h1 {
        margin: 5px 0;
        font-size: 14px;
    }

    .menu-toggle {
        margin: 5px 0;
    }

    .card-row {
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }

    .card {
        width: 100%;
        max-width: 300px;
        height: auto;
    }

    .modal-content {
        width: 95%;
        margin: 30% auto;
    }
}
@media (max-width: 1024px) {
    .container { gap: 60px; }
    .card-row { gap: 40px; }
}

@media (max-width: 768px){
    .container { gap: 40px; padding: 20px; }
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
    <h1>Welcome, <?php echo htmlspecialchars($username); ?> (W2)</h1>
    <div class="profile-dropdown" id="profileDropdown">
        <img src="images/Wizara ya elimu.jpg" alt="Profile" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#" id="viewProfileBtn">View Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="main">
    <div class="container">
        <h2>My Work (W2)</h2>

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

</body>
</html>
