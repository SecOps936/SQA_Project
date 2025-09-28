<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
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
            $stmt = $conn->prepare("INSERT INTO permissions (user_id, full_name, category, reason, status, receiver_id) VALUES (?, ?, ?, ?, 'Pending', ?)");
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

// Fetch permission requests for modal
$stmt = $conn->prepare("SELECT category, reason, status, created_at FROM permissions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$permissions = $stmt->get_result();
$stmt->close();

// ✅ Fix: Fetch unread messages count
$stmt = $conn->prepare("SELECT COUNT(*) AS unread FROM messages WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$unread_data = $res->fetch_assoc();
$unread_count = $unread_data ? $unread_data['unread'] : 0;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ask Permission</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin: 0;
    font-family: Arial,sans-serif;
    height: 100vh;
    display: flex;
    background-image: url('images/Tanzania.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
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
    background-image:url('images/emblem.png');
    background-size:contain;
    background-repeat:no-repeat;
    opacity:0.2;
    transform:translate(-50%, -50%);
    z-index:-1;
}

/* Sidebar */
.sidebar {
    width:220px;
    background:#1F5F60;
    color:white;
    display:flex;
    flex-direction:column;
    padding:20px 15px;
    box-shadow:2px 0 6px rgba(0,0,0,0.2);
    transition: transform 0.3s ease;
    position: fixed;
    top:0;
    left:0;
    height:100%;
    z-index:900;
}
.sidebar.hidden { transform: translateX(-100%); }
.sidebar h2 { margin:0 0 20px; font-size:18px; }
.sidebar a { color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; display:block; transition: background 0.3s ease; }
.sidebar a:hover { background: rgba(255,255,255,0.2); }

/* Header */
.header {
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:60px;
    padding:10px 20px;
    background:#2C3E50;
    color:white;
    display:flex;
    align-items:center;
    z-index:1000;
    box-sizing:border-box;
}
.menu-toggle { font-size:20px; cursor:pointer; margin-right:15px; display:inline-block; }
.header-title { flex:1; text-align:center; font-size:20px; margin:0; }
.logout-btn { background:#E74C3C; color:white; padding:6px 14px; text-decoration:none; border-radius:6px; font-size:14px; transition: background 0.3s ease; }
.logout-btn:hover { background:#c0392b; }

/* Main */
.main {
    flex:1;
    display:flex;
    flex-direction:column;
    transition: margin-left 0.3s ease;
    margin-left:220px;
    padding:100px 30px 30px;
    align-items:center;
}
.main.expanded { margin-left:0; }

/* Form styling adjustments */
form {
    background:#fff;
    padding:20px;
    border-radius:10px;
    max-width:500px;  /* reduced width for better centering */
    width:100%;
    margin:0 auto 30px; /* center horizontally */
    box-shadow:0 6px 15px rgba(0,0,0,0.1);
}

form input, form select, form textarea {
    width:100%; /* all fields same width */
    padding:10px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:6px;
    box-sizing:border-box;
}

form button {
    background:#1581A1;
    color:white;
    cursor:pointer;
    border:none;
    font-size:14px;   /* reduced font size */
    padding:8px 0;    /* smaller height */
    border-radius:6px;
    width:50%;        /* smaller width */
    margin:10px auto 0; /* center button */
    display:block;
}

form button:hover { background:#0056b3; }

/* Status Button adjustments */
#checkStatusBtn {
    background:#FF9800;
    color:white;
    border:none;
    padding:8px 0;   /* smaller height */
    font-size:14px;  /* reduced font size */
    border-radius:6px;
    cursor:pointer;
    transition:0.3s;
    margin:10px auto 0; /* center horizontally */
    display:block;
}

#checkStatusBtn:hover { background:#e68900; }


/* Modal */
#statusModal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:2000; }
#statusModal .modal-content { background:#fff; padding:20px; border-radius:12px; width:90%; max-width:600px; max-height:80%; overflow-y:auto; box-shadow:0 10px 25px rgba(0,0,0,0.2); position:relative; }
#statusModal .close { position:absolute; top:10px; right:15px; font-size:22px; cursor:pointer; color:#555; }

.status-Pending { color:orange; font-weight:bold; }
.status-Approved { color:green; font-weight:bold; }
.status-Rejected { color:red; font-weight:bold; }

table { width:100%; border-collapse:collapse; margin-top:15px; }
table th, table td { padding:12px; border:1px solid #ccc; text-align:left; }
table th { background:#1F5F60; color:white; }

/* Messages */
.message { padding:10px; border-radius:6px; margin-bottom:20px; width:100%; max-width:600px; }
.success { background:#d4edda; color:#155724; }
.error { background:#f8d7da; color:#721c24; }
</style>
</head>
<body>

<!-- Sidebar -->
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Employee Panel</h2>
   <a href="employee_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="view_tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
    <a href="ask_permission.php"><i class="fas fa-hand-paper"></i> Permission</a>
     <a href="upload_task.php"><i class="fas fa-upload"></i> Upload Task</a>
    <a href="task_status.php"><i class="fas fa-check-circle"></i> Task Status</a>
    <a href="edit_task.php"><i class="fas fa-edit"></i> Edit Task</a>
</div>


<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1 class="header-title">Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- Main -->
<div class="main" id="mainContent">
    <?php if (!empty($success)): ?>
        <div class="message success"><?php echo $success; ?></div>
    <?php elseif (!empty($error)): ?>
        <div class="message error"><?php echo $error; ?></div>
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

    <button id="checkStatusBtn"><i class="fas fa-info-circle"></i> view Status</button>
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
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                <td class="status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></td>
                <td><?php echo $row['created_at']; ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script>
// Modal functionality
const modal = document.getElementById('statusModal');
const btn = document.getElementById('checkStatusBtn');
const span = document.querySelector('#statusModal .close');
btn.onclick = () => modal.style.display = 'flex';
span.onclick = () => modal.style.display = 'none';
window.onclick = e => { if (e.target === modal) modal.style.display = 'none'; }

// Sidebar toggle
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.getElementById("mainContent").classList.toggle("expanded");
});
</script>

</body>
</html>
