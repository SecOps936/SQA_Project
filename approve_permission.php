<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W1') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

/* ---------- Handle AJAX Approval / Rejection ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permission_id'], $_POST['action'])) {
    $perm_id = intval($_POST['permission_id']);
    $action = $_POST['action'];

    // Check if permission belongs to current user
    $check = $conn->prepare("SELECT id FROM permissions WHERE id = ? AND receiver_id = ?");
    $check->bind_param("ii", $perm_id, $user_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Permission not found or access denied']);
        exit;
    }
    $check->close();

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE permissions SET status='Approved' WHERE id=?");
        $stmt->bind_param("i", $perm_id);
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE permissions SET status='Rejected' WHERE id=?");
        $stmt->bind_param("i", $perm_id);
    }

    if ($stmt->execute()) {
        // Get updated pending count
        $sql = "SELECT COUNT(*) AS pending FROM permissions WHERE receiver_id=$user_id AND status='Pending'";
        $res = $conn->query($sql);
        $pending = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['pending'] : 0;
        
        echo json_encode(['success' => true, 'pending' => $pending]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* ---------- Fetch all permissions for W1 ---------- */
$stmt = $conn->prepare("
    SELECT p.id, u.username, p.category, p.reason, p.status, p.created_at 
    FROM permissions p 
    JOIN users u ON p.user_id = u.id
    WHERE p.receiver_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result();
$stmt->close();

/* ---------- Pending notification ---------- */
$sql = "SELECT COUNT(*) AS pending FROM permissions WHERE receiver_id=$user_id AND status='Pending'";
$res = $conn->query($sql);
$pending = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['pending'] : 0;

/* ---------- Profile info ---------- */
$result = $conn->query("SELECT username, email, role FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Approve Permission</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
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
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0;} }
/* Main Content */
.main {
    flex: 1;
    display: flex;
    justify-content: center;
    padding: 100px 20px 20px 20px;
    transition: margin-left 0.3s ease;
    margin-left: 220px;
}
.main.expanded { margin-left: 0; }
/* Table */
.table-responsive { display: flex; justify-content: center; margin-top: 20px; }
table { width: 100%; max-width: 1000px; border-collapse: collapse; background: #fff; box-shadow:0 2px 6px rgba(0,0,0,0.1);}
th, td { padding: 12px; border:1px solid #ddd; text-align:left; white-space: nowrap; }
th { background: #1F5F60; color:white; }
.status-Pending { color:orange; font-weight:bold; }
.status-Approved { color:green; font-weight:bold; }
.status-Rejected { color:red; font-weight:bold; }
button { padding:5px 10px; border:none; border-radius:5px; cursor:pointer; }
/* Responsive */
@media (max-width:768px){ .main{padding:80px 10px 10px 10px;} table{font-size:14px;} }
@media (max-width:500px){ table,thead,tbody,th,td,tr{display:block;} tr{margin-bottom:15px;border-bottom:2px solid #ddd;} td{text-align:right;padding-left:50%;position:relative;} td::before{content:attr(data-label);position:absolute;left:15px;width:45%;font-weight:bold;text-align:left;} th{display:none;} }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>W1 Panel</h2>
    <a href="w1_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="approve_permission.php" id="approvePermissionLink"><i class="fas fa-user-check"></i> Approve Permission
        <?php if ($pending > 0) { echo "<span id='notificationIndicator' class='notification-indicator'></span>"; } ?>
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
    <h1>Welcome, <?= htmlspecialchars($username); ?> (W1)</h1>
    <div class="profile-dropdown" id="profileDropdown">
        <img src="images/Wizara ya elimu.jpg" alt="Profile" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#">View Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main -->
<div class="main" id="mainContent">
    <div class="table-responsive">
        <table>
            <tr>
                <th>Employee</th>
                <th>Category</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
            <?php while ($row = $requests->fetch_assoc()): ?>
            <tr data-id="<?= $row['id']; ?>">
                <td data-label="Employee"><?= htmlspecialchars($row['username']); ?></td>
                <td data-label="Category"><?= htmlspecialchars($row['category']); ?></td>
                <td data-label="Reason"><?= htmlspecialchars($row['reason']); ?></td>
                <td data-label="Status" class="status-<?= $row['status']; ?>"><?= $row['status']; ?></td>
                <td data-label="Date"><?= $row['created_at']; ?></td>
                <td data-label="Action">
                    <?php if ($row['status'] === 'Pending'): ?>
                        <button class="approve-btn" data-id="<?= $row['id']; ?>" data-action="approve" style="background:green;color:white;">Approve</button>
                        <button class="reject-btn" data-id="<?= $row['id']; ?>" data-action="reject" style="background:red;color:white;">Reject</button>
                        <button class="delete-btn" style="background:#777;color:white;">Delete</button>
                    <?php else: ?>
                        <em>No action</em>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
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

// Function to update notification indicator
function updateNotificationIndicator(count) {
    const link = document.getElementById('approvePermissionLink');
    const existingIndicator = document.getElementById('notificationIndicator');
    
    // Remove existing indicator if any
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    // Add new indicator if count > 0
    if (count > 0) {
        const indicator = document.createElement('span');
        indicator.id = 'notificationIndicator';
        indicator.className = 'notification-indicator';
        link.appendChild(indicator);
    }
}

// Approve / Reject AJAX
document.querySelectorAll('.approve-btn, .reject-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const permId = btn.dataset.id;
        const action = btn.dataset.action;

        fetch('approve_permission.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `permission_id=${permId}&action=${action}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update the table row
                const row = btn.closest('tr');
                const statusTd = row.querySelector('td[data-label="Status"]');
                statusTd.textContent = action === 'approve' ? 'Approved' : 'Rejected';
                statusTd.className = 'status-' + statusTd.textContent;
                row.querySelector('td[data-label="Action"]').innerHTML = '<em>No action</em>';
                
                // Update the notification indicator
                updateNotificationIndicator(data.pending);
                
                // Show success notification
                showNotification(`Permission ${action}d successfully!`);
            } else {
                // Show error notification
                showNotification(`Error: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
    });
});

// Client-side Delete
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = btn.closest('tr');
        row.remove(); // does not affect database
        showNotification('Permission removed from view');
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
    
    // Style the notification
    notification.style.position = "fixed";
    notification.style.top = "80px";
    notification.style.right = "20px";
    notification.style.background = type === "error" ? "#f44336" : "#4CAF50";
    notification.style.color = "white";
    notification.style.padding = "12px 18px";
    notification.style.borderRadius = "6px";
    notification.style.boxShadow = "0 4px 10px rgba(0,0,0,0.1)";
    notification.style.opacity = "0";
    notification.style.transform = "translateY(-10px)";
    notification.style.transition = "opacity 0.5s ease, transform 0.5s ease";
    notification.style.zIndex = "3000";
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.style.opacity = "1";
        notification.style.transform = "translateY(0)";
    }, 10);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = "0";
        notification.style.transform = "translateY(-10px)";
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 3000);
}
</script>
</body>
</html>