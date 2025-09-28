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

// ✅ Handle profile update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_message = "Invalid email format.";
    } else {
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                $update_message = "Passwords do not match.";
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                $update_message = "Password must be at least 8 characters long and include uppercase, lowercase, number, and symbol.";
            } else {
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email=?, password=? WHERE id=?");
                $stmt->bind_param("ssi", $email, $password_hashed, $user_id);
                if ($stmt->execute()) {
                    $update_message = "Profile updated successfully!";
                } else {
                    $update_message = "Failed to update profile.";
                }
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET email=? WHERE id=?");
            $stmt->bind_param("si", $email, $user_id);
            if ($stmt->execute()) {
                $update_message = "Profile updated successfully!";
            } else {
                $update_message = "Failed to update profile.";
            }
            $stmt->close();
        }
    }
}

// Fetch user info
$result = $conn->query("SELECT username, email, role FROM users WHERE id = $user_id");
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* General body */
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
    padding: 12px 10px;
    margin: 5px 0;
    border-radius: 6px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}
.sidebar a i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}
.sidebar a:hover { 
    background: rgba(255,255,255,0.2);
    transform: translateX(5px);
}

/* Main */
.main {
    flex: 1;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease;
    margin-left: 220px;
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
    transition: background 0.2s ease;
}
.dropdown-menu a:hover { background: #f1f1f1; }

/* Dashboard Container */
.container { 
    flex: 1; 
    display: flex; 
    flex-direction: column; 
    padding: 100px 60px 40px; 
    margin-top: 20px;
    align-items: center;
}

/* Dashboard Title */
.dashboard-title {
    font-size: 32px;
    font-weight: 600;
    color: #2C3E50;
    margin-bottom: 50px;
    text-align: center;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Card Rows */
.card-row { 
    display: flex; 
    justify-content: center; 
    gap: 100px; 
    margin-bottom: 60px;
    width: 100%;
    max-width: 1400px;
    align-items: center;
}

/* Cards */
.card {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border-radius: 20px;
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    text-align: center;
    width: 300px;
    height: 240px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.05);
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(90deg, #1F5F60, #1581A1);
}

.card:hover { 
    transform: translateY(-12px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.18);
}

.card-icon-container {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1F5F60, #1581A1);
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
    box-shadow: 0 8px 20px rgba(31, 95, 96, 0.4);
}

.card .icon { 
    font-size: 36px; 
    color: white;
}

.card h3 { 
    margin: 0 0 20px;
    color: #2C3E50;
    font-size: 20px;
    font-weight: 600;
}

.card a { 
    padding: 12px 24px;
    background: linear-gradient(90deg, #1F5F60, #1581A1);
    color: white;
    text-decoration: none;
    border-radius: 30px;
    display: inline-block;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 6px 12px rgba(31, 95, 96, 0.25);
}

.card a:hover { 
    background: linear-gradient(90deg, #1581A1, #0d6d85);
    transform: scale(1.05);
    box-shadow: 0 8px 16px rgba(31, 95, 96, 0.35);
}

/* Msg indicator */
.msg-indicator {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #e74c3c;
    color: white;
    font-size: 12px;
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 20px;
    box-shadow: 0 3px 8px rgba(231, 76, 60, 0.5);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Modal */
.modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 35px;
    border-radius: 20px;
    width: 500px;
    position: relative;
    box-shadow: 0 15px 35px rgba(0,0,0,0.25);
}
.close-btn { 
    position: absolute; 
    top: 20px; 
    right: 25px; 
    font-size: 28px; 
    font-weight: bold; 
    cursor: pointer;
    color: #aaa;
    transition: color 0.2s;
}
.close-btn:hover { color: #333; }
.modal h2 {
    margin-top: 0;
    color: #2C3E50;
    font-size: 24px;
    margin-bottom: 25px;
}
.modal input[type="email"], .modal input[type="password"] {
    width: 100%; 
    padding: 14px; 
    margin: 10px 0 20px; 
    box-sizing: border-box; 
    border: 1px solid #ddd; 
    border-radius: 10px;
    font-size: 16px;
    transition: border 0.3s;
}
.modal input[type="email"]:focus, .modal input[type="password"]:focus {
    border-color: #1F5F60;
    outline: none;
    box-shadow: 0 0 0 3px rgba(31, 95, 96, 0.2);
}
.modal label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
    font-size: 16px;
}
.password-container { position: relative; }
.password-container i {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #777;
    font-size: 20px;
}
.modal button { 
    padding: 14px 28px; 
    background: linear-gradient(90deg, #1F5F60, #1581A1);
    color:white; 
    border:none; 
    border-radius: 35px; 
    cursor:pointer;
    font-size: 18px;
    font-weight: 500;
    margin-top: 15px;
    width: 100%;
    transition: all 0.3s ease;
    box-shadow: 0 6px 12px rgba(31, 95, 96, 0.25);
}
.modal button:hover { 
    background: linear-gradient(90deg, #1581A1, #0d6d85);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(31, 95, 96, 0.35);
}

/* Responsive */
@media (max-width: 1200px) {
    .card-row {
        gap: 60px;
    }
    .card {
        width: 260px;
        height: 220px;
    }
    .card-icon-container {
        width: 70px;
        height: 70px;
    }
    .card .icon {
        font-size: 32px;
    }
}

@media (max-width: 1024px) {
    .container {
        padding: 100px 40px 40px;
    }
    .card-row {
        gap: 40px;
    }
    .card {
        width: 240px;
        height: 200px;
    }
    .card-icon-container {
        width: 60px;
        height: 60px;
    }
    .card .icon {
        font-size: 28px;
    }
    .card h3 {
        font-size: 18px;
    }
    .card a {
        font-size: 14px;
        padding: 10px 20px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .main {
        margin-left: 0;
    }
    .container {
        padding: 80px 30px 30px;
    }
    .card-row {
        flex-direction: column;
        align-items: center;
        gap: 40px;
    }
    .card {
        width: 90%;
        max-width: 350px;
        height: 200px;
    }
    .modal-content {
        width: 90%;
        margin: 20% auto;
        padding: 25px;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 70px 20px 20px;
    }
    .dashboard-title {
        font-size: 24px;
        margin-bottom: 30px;
    }
    .card {
        height: 180px;
    }
    .card-icon-container {
        width: 50px;
        height: 50px;
    }
    .card .icon {
        font-size: 24px;
    }
    .card h3 {
        font-size: 16px;
    }
    .card a {
        font-size: 13px;
        padding: 8px 16px;
    }
    .modal input[type="email"], .modal input[type="password"] {
        padding: 12px;
    }
    .modal button {
        padding: 12px 24px;
        font-size: 16px;
    }
}
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
    <a href="edit_task.php"><i class="fas fa-edit"></i> Edit Task</a>
    <a href="store_work.php"><i class="fas fa-folder-open"></i> My Works</a>
</div>

<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h2>Welcome, <?php echo htmlspecialchars($username); ?></h2>
    <!-- Profile Dropdown -->
    <div class="profile-dropdown" id="profileDropdown">
        <img src="images/Wizara ya elimu.jpg" alt="Profile" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#" id="viewProfileBtn">View Profile</a>
            <a href="#" id="updateProfileBtn">Update Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="main" id="mainContent">
    <div class="container">
        
        <!-- First Row -->
        <div class="card-row">
            <div class="card">
                <div class="card-icon-container">
                    <div class="icon"><i class="fas fa-hand-paper"></i></div>
                </div>
                <h3>Ask Permission</h3>
                <a href="ask_permission.php">Request</a>
            </div>
            <div class="card">
                <div class="card-icon-container">
                    <div class="icon"><i class="fas fa-upload"></i></div>
                </div>
                <h3>Upload Task</h3>
                <a href="upload_task.php">Upload</a>
            </div>
            <div class="card">
                <div class="card-icon-container">
                    <div class="icon"><i class="fas fa-tasks"></i></div>
                </div>
                <h3>New Tasks</h3>
                <a href="view_tasks.php">View</a>
            </div>
        </div>

        <!-- Second Row -->
        <div class="card-row">
            <div class="card">
                <div class="card-icon-container">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <h3>Task Status</h3>
                <a href="task_status.php">Check</a>
            </div>
            <div class="card">
                <div class="card-icon-container">
                    <div class="icon"><i class="fas fa-edit"></i></div>
                </div>
                <h3>Edit Task</h3>
                <a href="edit_task.php">Edit</a>
            </div>
            <div class="card">
                <div class="card-icon-container">
                    <div class="icon"><i class="fas fa-folder-open"></i></div>
                </div>
                <h3>My Works</h3>
                <a href="store_work.php">Go</a>
            </div>
        </div>
    </div>
</div>

<!-- View Profile Modal -->
<div id="viewProfileModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeViewProfile">&times;</span>
        <h2>Profile Information</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
    </div>
</div>

<!-- Update Profile Modal -->
<div id="updateProfileModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeUpdateProfile">&times;</span>
        <h2>Update Profile</h2>
        <?php if ($update_message) echo "<p style='color:green; font-weight:500;'>$update_message</p>"; ?>
        <form method="POST" id="updateProfileForm">
            <label>Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            <label>New Password:</label>
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="New password">
                <i class="fas fa-eye" id="togglePassword"></i>
            </div>
            <label>Confirm Password:</label>
            <div class="password-container">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password">
                <i class="fas fa-eye" id="toggleConfirmPassword"></i>
            </div>
            <button type="submit" name="update_profile">Update Profile</button>
        </form>
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

profileIcon.addEventListener("click", function(e) {
    dropdownMenu.style.display = (dropdownMenu.style.display === "block") ? "none" : "block";
    e.stopPropagation();
});
window.addEventListener("click", function(e) {
    if (!profileDropdown.contains(e.target)) dropdownMenu.style.display = "none";
});

// Modals
const viewModal = document.getElementById("viewProfileModal");
const updateModal = document.getElementById("updateProfileModal");

document.getElementById("viewProfileBtn").onclick = () => viewModal.style.display = "block";
document.getElementById("updateProfileBtn").onclick = () => updateModal.style.display = "block";
document.getElementById("closeViewProfile").onclick = () => viewModal.style.display = "none";
document.getElementById("closeUpdateProfile").onclick = () => updateModal.style.display = "none";

window.onclick = function(event) {
    if (event.target == viewModal) viewModal.style.display = "none";
    if (event.target == updateModal) updateModal.style.display = "none";
};

// Show/Hide password
function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    icon.addEventListener('click', function() {
        const type = input.type === 'password' ? 'text' : 'password';
        input.type = type;
        this.classList.toggle('fa-eye-slash');
    });
}
togglePwd('password', 'togglePassword');
togglePwd('confirm_password', 'toggleConfirmPassword');
</script>
</body>
</html>