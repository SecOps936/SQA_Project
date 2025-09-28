<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Function to generate strong random password with letters, numbers, symbols
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
    $password = '';
    $maxIndex = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $maxIndex)];
    }
    return $password;
}

// Flash password system
if (!isset($_SESSION['flash_password'])) {
    $_SESSION['flash_password'] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $target_user_id = intval($_POST['user_id']);

    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $stmt->bind_result($selected_username);
    $stmt->fetch();
    $stmt->close();

    if ($selected_username) {
        $newPasswordPlain = generateRandomPassword(12);
        $newPasswordHashed = password_hash($newPasswordPlain, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newPasswordHashed, $target_user_id);

        if ($stmt->execute()) {
            $_SESSION['flash_password'] = "Password for <strong>$selected_username</strong> has been reset successfully. New password: <strong>$newPasswordPlain</strong>";
        } else {
            $_SESSION['flash_password'] = "Error updating password.";
        }
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['flash_password'] = "User not found.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$reset_password_display = $_SESSION['flash_password'] ?? '';
unset($_SESSION['flash_password']);

// Get all users
$result = $conn->query("SELECT id, username, email FROM users ORDER BY created_at DESC");

// Fetch admin user info for profile modal
$profileRes = $conn->query("SELECT username, email, role FROM users WHERE id = $user_id");
$user = $profileRes->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset User Password</title>
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

/* Main content */
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
/* Notification */
.notification-indicator {
    display:inline-block;
    width:8px;
    height:8px;
    border-radius:50%;
    background:red;
    margin-left:6px;
    animation: blink 1s infinite;
    vertical-align: middle;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}

/* Reset form container */
.container {
    width: 900px;
    margin: 100px auto;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    text-align: center;
}
.container img {
    display: block;
    margin: 0 auto 15px auto;
    max-width: 120px;
}
h2 { color: #2C3E50; margin-bottom: 15px; }
select, button {
    padding: 8px;
    margin: 10px 0;
    width: 70%;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
}
button {
    padding: 6px 12px;
    font-size: 14px;
    width: auto;
    min-width: 100px;
    border-radius: 6px;
    cursor: pointer;
    background: #1581A1;
    color: white;
    border: none;
}
button:hover { background: #0056b3; transform: scale(1.05); }
.message {
    margin-top: 15px;
    padding: 10px;
    border-radius: 6px;
    background: #f3f8ff;
    color: #333;
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Admin Panel</h2>
    <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="approve_users.php"><i class="fas fa-user-check"></i> Approve Users</a>
    <a href="reset_user_password.php"><i class="fas fa-key"></i> Reset Password</a>
    <a href="send_info.php"><i class="fas fa-paper-plane"></i> Provide Information</a>
    <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="receive_info.php"><i class="fas fa-envelope-open-text"></i> Receive Info 
        <span id="msgIndicator"></span>
    </a>
    <a href="registered_user.php"><i class="fas fa-users"></i> Registered Users</a>
    <a href="user_logs.php"><i class="fas fa-file-alt"></i> Logs</a>
</div>

<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>

    <div class="profile-dropdown" id="profileDropdown">
        <img src="images/Wizara ya elimu.jpg" alt="Profile" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#" id="viewProfileBtn">View Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main -->
<div class="main" id="mainContent">
    <div class="container">
        <img src="images/Wizara ya elimu.jpg" alt="Emblem">
        <h2>Reset User Password</h2>
        <form method="post">
            <label for="user_id">Select User:</label><br>
            <select name="user_id" id="user_id" required>
                <option value="">-- Select User --</option>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>">
                        <?php echo htmlspecialchars($row['username']) . " (" . htmlspecialchars($row['email']) . ")"; ?>
                    </option>
                <?php endwhile; ?>
            </select><br>
            <button type="submit">Reset Password</button>
        </form>

        <?php if ($reset_password_display): ?>
            <div class="message"><?php echo $reset_password_display; ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Profile View Modal -->
<div id="viewProfileModal" class="modal" style="display:none;position:fixed;z-index:3000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5);">
    <div class="modal-content" style="background:#fff;margin:10% auto;padding:20px;border-radius:10px;width:400px;position:relative;">
        <span class="close-btn" id="closeViewProfile" style="position:absolute;top:10px;right:15px;font-size:22px;cursor:pointer;">&times;</span>
        <h2>Profile Information</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
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
    if (!profileDropdown.contains(e.target)) {
        dropdownMenu.style.display = "none";
    }
});

// Profile modal
const viewModal = document.getElementById("viewProfileModal");
document.getElementById("viewProfileBtn").onclick = function() { viewModal.style.display = "block"; };
document.getElementById("closeViewProfile").onclick = function() { viewModal.style.display = "none"; };
window.onclick = function(event) {
    if (event.target == viewModal) viewModal.style.display = "none";
};

// Notification check
function checkUnread() {
    fetch('check_unread.php')
        .then(response => response.json())
        .then(data => {
            const indicator = document.getElementById("msgIndicator");
            indicator.className = (data.count > 0) ? "notification-indicator" : "";
        }).catch(console.error);
}
checkUnread();
setInterval(checkUnread, 5000);
</script>
</body>
</html>
