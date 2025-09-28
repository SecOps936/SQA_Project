<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require 'db.php';
$username = $_SESSION['username'];

// ================== REMOVE USER ==================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id=$delete_id");
    echo "<script>alert('User removed successfully!'); window.location='manage_users.php';</script>";
    exit;
}

// ================== ADD USER ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT); // secure hash
    $new_role = trim($_POST['role']);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at, status) VALUES (?, ?, ?, ?, NOW(), 'approved')");
    $stmt->bind_param("ssss", $new_username, $new_email, $new_password, $new_role);

    if ($stmt->execute()) {
        echo "<script>alert('User added successfully!'); window.location='manage_users.php';</script>";
    } else {
        echo "<script>alert('Error adding user.');</script>";
    }
    $stmt->close();
}

// ================== FETCH USERS ==================
$users_result = $conn->query("SELECT id, username, email, role FROM users ORDER BY username ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
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

.main { 
    flex: 1; 
    display: flex; 
    flex-direction: column; 
    transition: margin-left 0.3s ease; 
    margin-left: 220px; 
}
.main.expanded { margin-left: 0; }

.header {
    position: fixed;
    top: 0; left: 0; width: 100%;
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
.logout-btn {
    padding: 8px 15px;
    background: #FF4B5C;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: bold;
    transition: background 0.3s ease;
}
.logout-btn:hover { background: #d93a47; }

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

.container {
    width: 900px;
    max-width: 95%;
    margin: 100px auto 20px;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    text-align: center;
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 12px 15px;
    border: 1px solid #ccc;
    text-align: left;
    font-size: 14px;
}
th {
    background: #CCC5C4;
    color: white;
}
tr:nth-child(even) { background: #f3f8ff; }
tr:hover { background: #e1f0ff; cursor: default; }
.action-btn {
    padding: 6px 12px;
    border-radius: 6px;
    color: white;
    font-size: 13px;
    text-decoration: none;
    margin: 2px;
}
.delete-btn { background: #dc3545; }
.delete-btn:hover { background: #a71d2a; }

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main { margin-left: 0; }
    .menu-toggle { display: inline-block; }
    table, th, td { font-size: 12px; }
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


<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="main" id="mainContent">
    <div class="container">

        <!-- IMAGE ABOVE HEADER -->
        <div style="margin-bottom:10px;">
            <img src="images/Wizara ya elimu.jpg" alt="Logo" style="width:120px; height:auto;">
        </div>

        <h2 style="margin:10px 0;">Users</h2>

        <div style="text-align:right; margin-bottom:15px;">
            <button type="button" id="showAddUserForm" 
                    style="padding:8px 15px; background:#28a745; color:white; border:none; border-radius:6px; cursor:pointer;">
                + Add User
            </button>
        </div>

        <form method="POST" id="addUserForm" style="margin-bottom:20px; display:none; background:#f9f9f9; padding:15px; border-radius:8px; text-align:left;">
            <h3>Add New User</h3>
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="employee">Employee</option>
                <option value="W1">W1</option>
                <option value="W2">W2</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit" name="add_user" 
                    style="padding:8px 15px; background:#1581A1; color:white; border:none; border-radius:6px; cursor:pointer;">
                Save User
            </button>
            <button type="button" id="cancelAddUser" 
                    style="padding:8px 15px; background:#6c757d; color:white; border:none; border-radius:6px; cursor:pointer; margin-left:10px;">
                Cancel
            </button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                while ($row = $users_result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($row['role'])); ?></td>
                    <td>
                        <a href="manage_users.php?delete=<?php echo $row['id']; ?>" 
                           class="action-btn delete-btn" 
                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                           Remove
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.getElementById("mainContent").classList.toggle("expanded");
});

// Show/hide add user form
const showBtn = document.getElementById("showAddUserForm");
const form = document.getElementById("addUserForm");
const cancelBtn = document.getElementById("cancelAddUser");

showBtn.addEventListener("click", () => {
    form.style.display = "block";
    showBtn.style.display = "none";
});

cancelBtn.addEventListener("click", () => {
    form.style.display = "none";
    showBtn.style.display = "inline-block";
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
checkUnread();
setInterval(checkUnread, 5000);
</script>

</body>
</html>
