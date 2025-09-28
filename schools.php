<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W1') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

/* --- Add New School --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_school'])) {
    $school_name = trim($_POST['school_name']);
    $head_name = trim($_POST['head_name']);
    $ward = trim($_POST['ward']);
    $head_phone = trim($_POST['head_phone']);
    $num_students = (int) $_POST['num_students'];

    if (!empty($school_name) && !empty($head_name) && !empty($ward) && !empty($head_phone) && $num_students > 0) {
        $stmt = $conn->prepare("INSERT INTO schools (school_name, head_name, ward, head_phone, num_students) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $school_name, $head_name, $ward, $head_phone, $num_students);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to prevent form resubmission on refresh
        header("Location: schools.php?success=1");
        exit();
    }
}

/* --- Edit School --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_school'])) {
    $id = (int) $_POST['id'];
    $school_name = trim($_POST['school_name']);
    $head_name = trim($_POST['head_name']);
    $ward = trim($_POST['ward']);
    $head_phone = trim($_POST['head_phone']);
    $num_students = (int) $_POST['num_students'];

    $stmt = $conn->prepare("UPDATE schools SET school_name=?, head_name=?, ward=?, head_phone=?, num_students=? WHERE id=?");
    $stmt->bind_param("ssssii", $school_name, $head_name, $ward, $head_phone, $num_students, $id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to prevent form resubmission on refresh
    header("Location: schools.php?updated=1");
    exit();
}

/* --- Delete School --- */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM schools WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: schools.php?deleted=1");
    exit();
}

/* --- Fetch all schools --- */
$sql = "SELECT id, school_name, head_name, ward, head_phone, num_students FROM schools";
$result = $conn->query($sql);
$schools = $result->fetch_all(MYSQLI_ASSOC);

// Permission request count
$sql = "SELECT COUNT(*) AS pending FROM permissions WHERE status='pending'";
$res = $conn->query($sql);
$pending = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['pending'] : 0;

// Profile info
$result = $conn->query("SELECT username, email, role FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();

// Success messages
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'School added successfully!';
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $success_message = 'School updated successfully!';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $success_message = 'School deleted successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Schools Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* === BODY & BACKGROUND === */
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
body::before { content:""; position:absolute; inset:0; background:rgba(255,255,255,0.85); z-index:-1; }
body::after { content:""; position:absolute; top:50%; left:50%; width:120px; height:150px; background-image:url('images/emblem.png'); background-size:contain; background-repeat:no-repeat; opacity:0.2; transform:translate(-50%,-50%); z-index:-1; }

/* === SIDEBAR === */
.sidebar {
    width: 220px;
    background: #1F5F60;
    color: white;
    display: flex;
    flex-direction: column;
    padding: 20px 15px;
    box-shadow: 2px 0 6px rgba(0,0,0,0.2);
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    z-index: 900;
}
.sidebar.hidden { transform: translateX(-100%); }
.sidebar h2 { margin:0 0 20px; font-size:18px; }
.sidebar a { color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; display:block; transition: background 0.3s ease; }
.sidebar a:hover { background: rgba(255,255,255,0.2); }

/* === HEADER === */
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
.header h1 { margin:0; font-size:20px; }
.menu-toggle { font-size:20px; cursor:pointer; margin-right:15px; }

/* Profile Dropdown */
.profile-dropdown { position:relative; display:inline-block; }
.profile-icon { width:40px; height:40px; border-radius:50%; cursor:pointer; border:2px solid #fff; object-fit:cover; }
.dropdown-menu {
    display:none; position:absolute; right:0; top:50px;
    background:#fff; border:1px solid #ddd; border-radius:8px;
    box-shadow:0 4px 12px rgba(0,0,0,0.15); min-width:160px; z-index:2000;
}
.dropdown-menu a { display:block; padding:10px 15px; color:#333; text-decoration:none; transition:background 0.2s; }
.dropdown-menu a:hover { background:#f1f1f1; }

/* Notification indicator */
.notification-indicator { display:inline-block; width:8px; height:8px; border-radius:50%; background:red; margin-left:6px; animation:blink 1s infinite; vertical-align:middle; }
@keyframes blink {0%,100%{opacity:1;}50%{opacity:0;}}

/* === MAIN === */
.main { flex:1; margin-left:220px; padding:90px 30px 30px; display:flex; flex-direction:column; align-items:center; gap:30px; }
.main.expanded { margin-left:0; }

/* Success Message */
.success-message {
    background: #d4edda;
    color: #155724;
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 15px;
    text-align: center;
    width: 90%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* Table */
.table-box { width:90%; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.1); }
table { width:100%; border-collapse:collapse; margin-top:15px; }
table th, table td { border:1px solid #ddd; padding:8px; text-align:center; }
table th { background:#1F5F60; color:white; }
.actions a { margin:0 5px; text-decoration:none; }
.edit-btn { color:blue; }
.delete-btn { color:red; }

/* Button */
#showAddSchoolBtn {
    margin-top:20px;
    background:#1F5F60;
    color:white;
    padding:10px 20px;
    border-radius:8px;
    border:none;
    cursor:pointer;
}
#showAddSchoolBtn:hover { background:#1581A1; }

/* Modal Add School Form */
#addSchoolFormContainer {
    display: none;
    position: fixed;
    top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 3000;
}
#addSchoolFormContainer .form-box {
    width:450px;
    padding:25px;
    background:#fff;
    border-radius:12px;
    box-shadow:0 6px 20px rgba(0,0,0,0.25);
    animation: slideDown 0.3s ease;
    position: relative;
}
@keyframes slideDown { from { transform: translateY(-50px); opacity:0; } to { transform: translateY(0); opacity:1; } }
#addSchoolFormContainer .close-btn {
    background:#e74c3c; color:white; border:none; border-radius:6px; padding:5px 10px; float:right; cursor:pointer;
}
#addSchoolFormContainer .close-btn:hover { background:#c0392b; }

/* Form Layout */
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}
.form-row {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}
.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

/* Inputs */
input[type="text"], input[type="number"] { 
    width:100%; 
    padding:10px; 
    margin:5px 0; 
    border:1px solid #ccc; 
    border-radius:6px;
    font-size: 14px;
    box-sizing: border-box;
}
input[type="text"]:focus, input[type="number"]:focus {
    border-color: #1F5F60;
    outline: none;
    box-shadow: 0 0 5px rgba(31, 95, 96, 0.3);
}
button { 
    padding:10px 15px; 
    border:none; 
    border-radius:6px; 
    background:#1F5F60; 
    color:white; 
    cursor:pointer;
    font-size: 14px;
    width: 15%;
    margin-top: 10px;
}
button:hover { background:#1581A1; }

/* Table Edit Form */
.edit-form {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    justify-content: center;
}
.edit-form input {
    width: 80px;
    padding: 5px;
    font-size: 12px;
}

/* Responsive */
@media (max-width:768px){
    .sidebar { transform:translateX(-100%);}
    .main { margin-left:0;}
    .table-box { width:95%;}
    #addSchoolFormContainer .form-box {
        width: 90%;
        margin: 20px;
    }
    .form-row {
        flex-direction: column;
    }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>W1 Panel</h2>
    <a href="w1_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="approve_permission.php"><i class="fas fa-user-check"></i> Approve Permission <?php if($pending>0) echo "<span class='notification-indicator'></span>"; ?></a>
    <a href="assign_task.php"><i class="fas fa-tasks"></i> Assign Task</a>
    <a href="view_employees.php"><i class="fas fa-users"></i> SQA Officers</a>
    <a href="schools.php"><i class="fas fa-school"></i> Schools</a>
    <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
    <a href="review_tasks.php"><i class="fas fa-check-double"></i> Approve / Rollback</a>
</div>

<!-- Header -->
<div class="header">
    <span class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></span>
    <h1>Schools Management - <?= htmlspecialchars($username) ?></h1>
    <div class="profile-dropdown">
        <img src="images/Wizara ya elimu.jpg" class="profile-icon" id="profileIcon">
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#">View Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Main -->
<div class="main">
    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Schools Table -->
    <div class="table-box">
        <h2>All Schools</h2>
        <table>
            <tr>
                <th>School</th>
                <th>Head</th>
                <th>Ward</th>
                <th>Head Phone</th>
                <th>Students</th>
                <th>Actions</th>
            </tr>
            <?php foreach($schools as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['school_name']) ?></td>
                <td><?= htmlspecialchars($s['head_name']) ?></td>
                <td><?= htmlspecialchars($s['ward']) ?></td>
                <td><?= htmlspecialchars($s['head_phone']) ?></td>
                <td><?= htmlspecialchars($s['num_students']) ?></td>
                <td class="actions">
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <div class="edit-form">
                            <input type="text" name="school_name" value="<?= htmlspecialchars($s['school_name']) ?>" required placeholder="School">
                            <input type="text" name="head_name" value="<?= htmlspecialchars($s['head_name']) ?>" required placeholder="Head">
                            <input type="text" name="ward" value="<?= htmlspecialchars($s['ward']) ?>" required placeholder="Ward">
                            <input type="text" name="head_phone" value="<?= htmlspecialchars($s['head_phone']) ?>" required placeholder="Phone">
                            <input type="number" name="num_students" value="<?= htmlspecialchars($s['num_students']) ?>" min="1" required placeholder="Students">
                            <button type="submit" name="edit_school">Save</button>
                        </div>
                    </form>
                    <a href="?delete=<?= $s['id'] ?>" class="delete-btn" onclick="return confirm('Delete this school?');">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <button id="showAddSchoolBtn">Add New School</button>
    </div>
</div>

<!-- Modal Add School Form -->
<div id="addSchoolFormContainer">
    <div class="form-box">
        <button class="close-btn" id="closeAddForm">X</button>
        <h2>Add New School</h2>
        <form method="post" id="addSchoolForm">
            <div class="form-group">
                <label for="school_name">School Name</label>
                <input type="text" id="school_name" name="school_name" placeholder="Enter school name" required>
            </div>
            
            <div class="form-group">
                <label for="head_name">Head of School</label>
                <input type="text" id="head_name" name="head_name" placeholder="Enter headmaster name" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="ward">Ward</label>
                    <input type="text" id="ward" name="ward" placeholder="Enter ward" required>
                </div>
                
                <div class="form-group">
                    <label for="head_phone">Phone Number</label>
                    <input type="text" id="head_phone" name="head_phone" placeholder="Enter phone number" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="num_students">Number of Students</label>
                <input type="number" id="num_students" name="num_students" placeholder="Enter number of students" min="1" required>
            </div>
            
            <button type="submit" name="add_school">Add School</button>
        </form>
    </div>
</div>

<script>
// Sidebar toggle
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("hidden");
    document.querySelector(".main").classList.toggle("expanded");
});

// Profile dropdown
const profileIcon = document.getElementById("profileIcon");
const dropdownMenu = document.getElementById("dropdownMenu");
profileIcon.addEventListener("click", () => {
    dropdownMenu.style.display = dropdownMenu.style.display === "block" ? "none" : "block";
});
window.addEventListener("click", (e) => { if (!profileIcon.contains(e.target)) dropdownMenu.style.display = "none"; });

// Show Add School Form
const showFormBtn = document.getElementById("showAddSchoolBtn");
const addFormContainer = document.getElementById("addSchoolFormContainer");
const closeFormBtn = document.getElementById("closeAddForm");
showFormBtn.addEventListener("click", () => addFormContainer.style.display = "flex");
closeFormBtn.addEventListener("click", () => addFormContainer.style.display = "none");
addFormContainer.addEventListener("click", (e) => { if(e.target===addFormContainer) addFormContainer.style.display="none"; });

// Hide success message after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.querySelector('.success-message');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            successMessage.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 500);
        }, 3000);
    }
});
</script>
</body>
</html>