<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W1') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Permission request count (for indicator)
$sql = "SELECT COUNT(*) AS pending FROM permissions WHERE status='pending'";
$res = $conn->query($sql);
$pending = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['pending'] : 0;

// Fetch profile info
$result = $conn->query("SELECT username, email, role FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>W1 Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
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
/* Card row layout */
.card-row {
    display: flex;
    justify-content: center;
    gap: 250px;         /* match W1 spacing */
    flex-wrap: wrap;    /* allows wrapping on smaller screens */
}

/* Card styling */
.card {
    background: linear-gradient(135deg, #ffffff, #f3f8ff);
    padding: 15px 10px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    text-align: center;
    width: 100%;
    max-width: 220px;   /* same as W1 */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.card:hover {
    transform: translateY(-6px) scale(1.03);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}
.card h3 {
    margin: 8px 0 10px;
    color: #696A6B;
    font-size: 16px;
}
.card a {
    padding: 6px 12px;
    background: #1581A1;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    display: inline-block;
    font-size: 13px;
    transition: background 0.3s ease;
}
.card a:hover { background: #0056b3; }
.card .icon { font-size: 28px; color: #18181A; margin-bottom: 8px; }

/* Notification popup */
.notification { position: fixed; top: 80px; right: 20px; background: #FFC107; color: #000; padding: 12px 18px; border-radius: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); opacity: 0; transform: translateY(-10px); transition: opacity 0.5s ease, transform 0.5s ease; }
.notification.show { opacity: 1; transform: translateY(0); }

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
/* ✅ Responsive Design */
@media (max-width: 1024px) {
    .card-row {
        gap: 50px;
    }
    .card {
        max-width: 200px;
    }
}

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

@media (max-width: 768px) {
    .container { gap: 40px; padding: 20px; }
    .card-row { flex-direction: column; align-items: center; gap: 20px; }
    .card { width: 90%; max-width: 320px; }
}

@media (max-width: 480px) {
    .container { gap: 30px; padding: 15px; }
    .header h1 { font-size: 14px; }
    .card { width: 100%; max-width: 300px; }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>W1 Panel</h2>
    <a href="w1_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="approve_permission.php"><i class="fas fa-user-check"></i> Approve Permission 
        <?php if ($pending > 0) { echo "<span class='notification-indicator'></span>"; } ?>
    </a>
    <a href="assign_task.php"><i class="fas fa-tasks"></i> Assign Task</a>
    <a href="view_employees.php"><i class="fas fa-users"></i> SQA Officers</a>
    <a href="schools.php"><i class="fas fa-school"></i> Schools</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="review_tasks.php"><i class="fas fa-check-double"></i> Approve / Rollback</a>
</div>

<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?> (W1)</h1>
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

<!-- Main -->
<div class="main" id="mainContent">
    <div class="container">
        <!-- First Row -->
        <div class="card-row">
            <div class="card"><div class="icon"><i class="fas fa-user-check"></i></div><h3>Approve Permission</h3><a href="approve_permission.php">Go</a></div>
            <div class="card"><div class="icon"><i class="fas fa-tasks"></i></div><h3>Assign Task</h3><a href="assign_task.php">Go</a></div>
            <div class="card"><div class="icon"><i class="fas fa-users"></i></div><h3>SQA Officers</h3><a href="view_employees.php">View</a></div>
        </div>

        <!-- Second Row -->
        <div class="card-row">
            <div class="card"><div class="icon"><i class="fas fa-school"></i></div><h3>Schools</h3><a href="schools.php">Manage</a></div>
            <div class="card"><div class="icon"><i class="fas fa-chart-line"></i></div><h3>Reports</h3><a href="reports.php">Check</a></div>
            <div class="card"><div class="icon"><i class="fas fa-check-double"></i></div><h3>Approve / Rollback</h3><a href="review_tasks.php">Go</a></div>
        </div>
    </div>
</div>

<!-- Profile View Modal -->
<div id="viewProfileModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeViewProfile">&times;</span>
        <h2>Profile Information</h2>
        <p><strong>Name:</strong> <span id="profileName"><?php echo htmlspecialchars($user['username']); ?></span></p>
        <p><strong>Email:</strong> <span id="profileEmail"><?php echo htmlspecialchars($user['email']); ?></span></p>
        <p><strong>Role:</strong> <span id="profileRole"><?php echo htmlspecialchars($user['role']); ?></span></p>
    </div>
</div>

<!-- Profile Update Modal -->
<div id="updateProfileModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeUpdateProfile">&times;</span>
        <h2>Update Profile</h2>
        <form id="updateProfileForm">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <div>
                <label for="updateUsername">Username:</label>
                <input type="text" id="updateUsername" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div>
                <label for="updateEmail">Email:</label>
                <input type="email" id="updateEmail" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="password-container">
                <label for="updatePassword">New Password (leave blank to keep current):</label>
                <input type="password" id="updatePassword" name="password">
                <i class="fas fa-eye" id="togglePassword"></i>
            </div>
            <button type="submit">Update</button>
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
const profileIcon = document.getElementById("profileIcon");
const dropdownMenu = document.getElementById("dropdownMenu");
profileIcon.addEventListener("click", () => {
    dropdownMenu.style.display = dropdownMenu.style.display === "block" ? "none" : "block";
});
window.addEventListener("click", (e) => {
    if (!profileIcon.contains(e.target)) dropdownMenu.style.display = "none";
});

// Profile view modal
const viewModal = document.getElementById("viewProfileModal");
document.getElementById("viewProfileBtn").onclick = () => viewModal.style.display = "block";
document.getElementById("closeViewProfile").onclick = () => viewModal.style.display = "none";

// Profile update modal
const updateModal = document.getElementById("updateProfileModal");
document.getElementById("updateProfileBtn").onclick = () => updateModal.style.display = "block";
document.getElementById("closeUpdateProfile").onclick = () => updateModal.style.display = "none";

// Close modals when clicking outside
window.onclick = (event) => { 
    if (event.target == viewModal) viewModal.style.display = "none";
    if (event.target == updateModal) updateModal.style.display = "none";
};

// Toggle password visibility
document.getElementById("togglePassword").addEventListener("click", function() {
    const passwordInput = document.getElementById("updatePassword");
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        this.classList.remove("fa-eye");
        this.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        this.classList.remove("fa-eye-slash");
        this.classList.add("fa-eye");
    }
});

// Handle profile update form submission
document.getElementById("updateProfileForm").addEventListener("submit", function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the session and the page
            document.querySelector('.header h1').innerHTML = `Welcome, ${data.username} (W1)`;
            
            // Update the view profile modal content
            document.getElementById('profileName').textContent = data.username;
            document.getElementById('profileEmail').textContent = data.email;
            
            // Show success notification
            showNotification("Profile updated successfully!");
            
            // Close the update modal
            updateModal.style.display = "none";
        } else {
            // Show error notification
            showNotification("Error: " + data.message, "error");
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification("An error occurred. Please try again.", "error");
    });
});

// Show notification function
function showNotification(message, type = "success") {
    const notification = document.createElement("div");
    notification.className = "notification";
    notification.textContent = message;
    
    if (type === "error") {
        notification.style.background = "#f44336";
        notification.style.color = "white";
    }
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.classList.add("show");
    }, 10);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove("show");
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 3000);
}
</script>
</body>
</html>