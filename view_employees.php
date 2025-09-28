<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W1') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 🔹 Count pending permissions
$sql = "SELECT COUNT(*) AS pending FROM permissions WHERE status='pending'";
$res = $conn->query($sql);
$pending = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['pending'] : 0;

// 🔹 Fetch all users except admin
$stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE role != 'admin' ORDER BY username ASC");
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 🔹 Fetch W1 profile info
$result = $conn->query("SELECT username, email, role FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SQA Officers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Use the same style as dashboard */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
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

/* Main */
.main {
    flex: 1;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease;
    margin-left: 220px;
}
.main.expanded { margin-left: 0; }

/* Table */
.container { padding: 80px 30px 30px; }
h2 { text-align: center; margin-bottom: 20px; }

.table-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin: auto;
}
th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
th {
    background: #1F5F60;
    color: white;
}
tr:hover { background: #f9f9f9; }

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
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

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
        <h2>Registered SQA Officers</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $index => $emp): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($emp['username']) ?></td>
                                <td><?= htmlspecialchars($emp['email']) ?></td>
                                <td><?= htmlspecialchars($emp['role']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;">No officers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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

// Profile modal
const viewModal = document.getElementById("viewProfileModal");
document.getElementById("viewProfileBtn").onclick = () => viewModal.style.display = "block";
document.getElementById("closeViewProfile").onclick = () => viewModal.style.display = "none";
window.onclick = (event) => { if (event.target == viewModal) viewModal.style.display = "none"; };
</script>
</body>
</html>
