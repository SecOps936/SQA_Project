<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle profile update form submission
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

// Fetch latest user info for display
$result = $conn->query("SELECT username, email, role FROM users WHERE id = $user_id");
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<style>
/* General body styling */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;   /* badilisha hii */
    display: flex;
    flex-direction: column; /* ongeza hii ili content ipangike */
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

/* Container layout */
.container { flex: 1; display: flex; flex-direction: column; gap: 100px; padding: 30px; margin-top: 80px; }
.card-row { display: flex; justify-content: center; gap: 350px; flex-wrap: wrap; }
.card {
    background: linear-gradient(135deg, #ffffff, #f3f8ff);
    padding: 25px 20px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    text-align: center;
    width: 100%;
    max-width: 350px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.card:hover { transform: translateY(-6px) scale(1.03); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
.card h3 { margin: 8px 0 10px; color: #696A6B; font-size: 16px; }
.card a { padding: 6px 12px; background: #1581A1; color: white; text-decoration: none; border-radius: 6px; display: inline-block; font-size: 13px; transition: background 0.3s ease; }
.card a:hover { background: #0056b3; }
.card .icon { font-size: 28px; color: #18181A; margin-bottom: 8px; }

/* Modal for profile view/update */
.modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border-radius: 10px;
    width: 400px;
    position: relative;
}
.modal-content h2 { margin-top:0; }
.close-btn { position: absolute; top: 10px; right: 15px; font-size: 22px; font-weight: bold; cursor: pointer; }
.modal input[type="text"], .modal input[type="email"], .modal input[type="password"] {
    width: 100%; padding: 8px; margin: 8px 0; box-sizing: border-box; border-radius: 6px; border: 1px solid #ccc;
}
.password-container {
    position: relative;
}
.password-container i {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #555;
}
.modal button { padding: 8px 15px; background:#007BFF; color:white; border:none; border-radius:6px; cursor:pointer; }
.modal button:hover { background:#0056b3; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Sidebar -->
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Admin Panel</h2>
   <a href="employee_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="approve_users.php"><i class="fas fa-user-check"></i> Approve Users</a>
    <a href="reset_user_password.php"><i class="fas fa-key"></i> Reset Password</a>
    <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
    <a href="registered_user.php"><i class="fas fa-users"></i> Registered Users</a>
    <a href="user_logs.php"><i class="fas fa-file-alt"></i> Logs</a>
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
    <div class="notification" id="notificationBox">You have a new alert from the system!</div>

    <div class="container">
    <!-- First Row -->
    <div class="card-row">
        <div class="card">
            <div class="icon"><i class="fas fa-user-check"></i></div>
            <h3>Approve Users</h3>
            <a href="approve_users.php">Go</a>
        </div>
        <div class="card">
            <div class="icon"><i class="fas fa-key"></i></div>
            <h3>Reset Password</h3>
            <a href="reset_user_password.php">Go</a>
        </div>
    </div>

    <!-- Second Row -->
    <div class="card-row">
        <div class="card">
            <div class="icon"><i class="fas fa-users-cog"></i></div>
            <h3>Manage Users</h3>
            <a href="manage_users.php">Go</a>
        </div>
        <div class="card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>Registered Users</h3>
            <a href="registered_user.php">View</a>
        </div>
    </div>

    <!-- Third Row -->
    <div class="card-row">
        <div class="card">
            <div class="icon"><i class="fas fa-file-alt"></i></div>
            <h3>User Login Logs</h3>
            <a href="user_logs.php">Check</a>
        </div>
    </div>
</div>
<!-- Profile View Modal -->
<div id="viewProfileModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeViewProfile">&times;</span>
        <h2>Profile Information</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
    </div>
</div>

<!-- Profile Update Modal -->
<div id="updateProfileModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeUpdateProfile">&times;</span>
        <h2>Update Profile</h2>
        <?php if ($update_message) echo "<p style='color:green'>$update_message</p>"; ?>
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

            <button type="submit" name="update_profile">Update</button>
        </form>
    </div>
</div>

<script>
// ✅ Sidebar toggle (only once)
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

// Modals
const viewModal = document.getElementById("viewProfileModal");
const updateModal = document.getElementById("updateProfileModal");

document.getElementById("viewProfileBtn").onclick = function() { 
    viewModal.style.display = "block"; 
};
document.getElementById("updateProfileBtn").onclick = function() { 
    updateModal.style.display = "block"; 
};
document.getElementById("closeViewProfile").onclick = function() { 
    viewModal.style.display = "none"; 
};
document.getElementById("closeUpdateProfile").onclick = function() { 
    updateModal.style.display = "none"; 
};

window.onclick = function(event) {
    if (event.target == viewModal) viewModal.style.display = "none";
    if (event.target == updateModal) updateModal.style.display = "none";
};

// Show/Hide Password
const password = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', function() {
    const type = password.type === 'password' ? 'text' : 'password';
    password.type = type;
    this.classList.toggle('fa-eye-slash');
});

const confirmPassword = document.getElementById('confirm_password');
const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

toggleConfirmPassword.addEventListener('click', function() {
    const type = confirmPassword.type === 'password' ? 'text' : 'password';
    confirmPassword.type = type;
    this.classList.toggle('fa-eye-slash');
});

// Client-side validation
document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
    const pwd = password.value.trim();
    const cpwd = confirmPassword.value.trim();
    if (pwd) {
        const strongPwd = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
        if (!strongPwd.test(pwd)) {
            alert("Password must be at least 8 characters long and include uppercase, lowercase, number, and symbol.");
            e.preventDefault();
            return;
        }
        if (pwd !== cpwd) {
            alert("Passwords do not match.");
            e.preventDefault();
            return;
        }
    }
});

function checkUnread() {
    fetch('check_unread.php')
        .then(response => response.json())
        .then(data => {
            const indicator = document.getElementById("msgIndicator");
            if (data.count > 0) {
                indicator.className = "notification-indicator"; 
            } else {
                indicator.className = ""; 
            }
        });
}

// Run immediately and then every 5 seconds
checkUnread();
setInterval(checkUnread, 5000);
</script>
</body>
</html>
