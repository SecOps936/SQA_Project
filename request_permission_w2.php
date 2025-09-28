<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W2') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason']);
    $category = trim($_POST['category']);

    if (!empty($reason) && !empty($category)) {
        // Assign request to W1
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'W1' LIMIT 1");
        $stmt->execute();
        $res = $stmt->get_result();
        $w1 = $res->fetch_assoc();
        $receiver_id = $w1 ? $w1['id'] : null;

        if ($receiver_id) {
            $stmt = $conn->prepare("INSERT INTO permissions (user_id, full_name, category, reason, status, receiver_id) 
                                    VALUES (?, ?, ?, ?, 'Pending', ?)");
            $stmt->bind_param("isssi", $user_id, $username, $category, $reason, $receiver_id);
            $stmt->execute() ? $success = "Permission request sent to W1 successfully." : $error = "Failed to send request.";
            $stmt->close();
        } else {
            $error = "W1 not found.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// Fetch W2's permission requests
$stmt = $conn->prepare("SELECT category, reason, status, created_at FROM permissions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$permissions = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ask Permission - W2</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    height: 100vh;
    display: flex;
    background: url('images/Tanzania.jpg') center/cover no-repeat;
    position: relative;
}
body::before {
    content:"";
    position:absolute;
    inset:0;
    background: rgba(255,255,255,0.85);
    z-index: -1;
}
body::after {
    content:"";
    position:absolute;
    top:50%;
    left:50%;
    width:120px;
    height:120px;
    background:url('images/emblem.png') no-repeat center/contain;
    opacity:0.2;
    transform:translate(-50%, -50%);
    z-index:-1;
}

/* Sidebar (W2 style) */
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

/* Main */
.main {
    flex:1;
    margin-left:220px;
    padding:100px 30px 30px;
    display:flex;
    flex-direction:column;
    align-items:center;
}

/* Form */
form {
    background:#fff;
    padding:20px;
    border-radius:10px;
    max-width:500px;
    width:100%;
    margin:0 auto 30px;
    box-shadow:0 6px 15px rgba(0,0,0,0.1);
}
form input, form select, form textarea {
    width:100%;
    padding:10px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:6px;
}
form button {
    background:#1581A1;
    color:white;
    cursor:pointer;
    border:none;
    font-size:14px;
    padding:8px 0;
    border-radius:6px;
    width:50%;
    margin:10px auto 0;
    display:block;
}
form button:hover { background:#0056b3; }

/* Status button */
#checkStatusBtn {
    background:#FF9800;
    color:white;
    border:none;
    padding:8px 0;
    font-size:14px;
    border-radius:6px;
    cursor:pointer;
    margin:10px auto 0;
    display:block;
}
#checkStatusBtn:hover { background:#e68900; }

/* Modal */
#statusModal {
    display:none;
    position:fixed;
    top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,0.5);
    justify-content:center;
    align-items:center;
    z-index:2000;
}
#statusModal .modal-content {
    background:#fff;
    padding:20px;
    border-radius:12px;
    width:90%;
    max-width:600px;
    max-height:80%;
    overflow-y:auto;
    position:relative;
}
#statusModal .close {
    position:absolute;
    top:10px;
    right:15px;
    font-size:22px;
    cursor:pointer;
    color:#555;
}
.status-Pending { color:orange; font-weight:bold; }
.status-approved { color:green; font-weight:bold; }
.status-rolled_back { color:red; font-weight:bold; }

table { width:100%; border-collapse:collapse; margin-top:15px; }
table th, table td { padding:12px; border:1px solid #ccc; text-align:left; }
table th { background:#34495E; color:white; }

/* Messages */
.message { padding:10px; border-radius:6px; margin-bottom:20px; width:100%; max-width:600px; }
.success { background:#d4edda; color:#155724; }
.error { background:#f8d7da; color:#721c24; }
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
            <a href="#" id="updateProfileBtn">Update Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main -->
<div class="main">
    <?php if (!empty($success)): ?>
        <div class="message success"><?= $success ?></div>
    <?php elseif (!empty($error)): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Full Name</label>
        <input type="text" value="<?php echo htmlspecialchars($username); ?>" readonly>

        <label>Category</label>
        <select name="category" required>
            <option value="Permission">Permission</option>
        </select>

        <label>Reason</label>
        <textarea name="reason" rows="4" placeholder="Enter your reason..." required></textarea>

        <button type="submit"><i class="fas fa-paper-plane"></i> Send Request</button>
    </form>

    <button id="checkStatusBtn"><i class="fas fa-info-circle"></i> View Status</button>
</div>

<!-- Modal -->
<div id="statusModal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>My Permission Requests</h2>
        <table>
            <tr>
                <th>Category</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php while ($row = $permissions->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['category']); ?></td>
                <td><?= htmlspecialchars($row['reason']); ?></td>
                <td class="status-<?= strtolower($row['status']); ?>"><?= $row['status']; ?></td>
                <td><?= $row['created_at']; ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script>
// Modal
const modal = document.getElementById('statusModal');
const btn = document.getElementById('checkStatusBtn');
const span = document.querySelector('#statusModal .close');
btn.onclick = () => modal.style.display = 'flex';
span.onclick = () => modal.style.display = 'none';
window.onclick = e => { if (e.target === modal) modal.style.display = 'none'; }
</script>
</body>
</html>
