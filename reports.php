<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'W1') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch pending permissions for indicator
$sql = "SELECT COUNT(*) AS pending FROM permissions WHERE status='pending'";
$res = $conn->query($sql);
$pending = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['pending'] : 0;

// Fetch profile info
$result = $conn->query("SELECT username, email, role FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();

// Fetch employees and task progress (only approved tasks count)
$sqa_result = $conn->query("
    SELECT u.id, u.username,
           COUNT(ct.task_id) AS total_tasks,
           SUM(CASE WHEN ct.status='approved' THEN 1 ELSE 0 END) AS approved_count
    FROM users u
    LEFT JOIN completed_tasks ct 
        ON u.id = ct.employee_id 
       AND ct.reviewed_by = $user_id   -- only tasks submitted to this W2
    WHERE u.role='employee'
    GROUP BY u.id
");
// Function to calculate percentage safely
function percentage($part, $total) {
    return $total > 0 ? round(($part / $total) * 100, 1) : 0;
}

// Function to get progress color based on percentage
function getProgressColor($percent) {
    if ($percent >= 71) return '#28a745'; // Green for Best (71-100%)
    if ($percent >= 50) return '#ffc107'; // Yellow for Good (50-70%)
    return '#dc3545'; // Red for Poor (0-49%)
}

// Function to get progress text based on percentage
function getProgressText($percent) {
    if ($percent >= 71) return 'Best';
    if ($percent >= 50) return 'Good';
    return 'Poor';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports - W1</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Keep the same styles as before */
body { margin:0; font-family:Arial,sans-serif; min-height:100vh; display:flex; flex-direction:column; background-image:url('images/Tanzania.jpg'); background-size:cover; background-position:center; background-repeat:no-repeat; position:relative;}
body::before { content:""; position:absolute; inset:0; background:rgba(255,255,255,0.85); z-index:-1;}
body::after { content:""; position:absolute; top:50%; left:50%; width:120px; height:150px; background-image:url('images/emblem.png'); background-size:contain; background-repeat:no-repeat; opacity:0.2; transform:translate(-50%, -50%); z-index:-1; }

.sidebar { width:220px; background:#1F5F60; color:white; display:flex; flex-direction:column; padding:20px 15px; box-shadow:2px 0 6px rgba(0,0,0,0.2); position:fixed; top:0; left:0; height:100%; z-index:900;}
.sidebar h2 { margin:0 0 20px; font-size:18px; }
.sidebar a { color:white; text-decoration:none; padding:10px; margin:5px 0; border-radius:6px; display:block; transition: background 0.3s ease; }
.sidebar a:hover { background: rgba(255,255,255,0.2); }

.main { flex:1; display:flex; flex-direction:column; margin-left:220px; padding-top:80px; }
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
.container { flex:1; display:flex; flex-direction:column; gap:30px; padding:30px; }
.report-card { background: linear-gradient(135deg,#ffffff,#f3f8ff); padding:20px; border-radius:12px; box-shadow:0 6px 15px rgba(0,0,0,0.08); text-align:center; width:100%; max-width:600px; margin:0 auto; }
.report-card h3 { margin-bottom:15px; color:#696A6B; }
.progress-bar { width:100%; background:#ddd; border-radius:10px; overflow:hidden; margin-top:10px; height:22px; }
.progress { height:100%; text-align:right; padding-right:5px; color:white; line-height:22px; border-radius:10px; background:#1581A1; }
.notification-indicator { display:inline-block; width:8px; height:8px; border-radius:50%; background:red; margin-left:6px; animation: blink 1s infinite; vertical-align:middle; }
@keyframes blink {0%,100%{opacity:1;}50%{opacity:0;}}

/* Circular Progress Styles */
.circular-progress-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 20px;
}

.progress-row {
    display: flex;
    justify-content: center;
    gap: 70px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.circular-progress {
    position: relative;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.circular-progress:hover {
    transform: scale(1.05);
}

.circular-progress canvas {
    position: absolute;
    top: 0;
    left: 0;
}

.circular-progress .progress-info {
    position: relative;
    z-index: 2;
    text-align: center;
}

.circular-progress .progress-value {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.circular-progress .progress-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.circular-progress .progress-status {
    font-size: 12px;
    font-weight: bold;
    padding: 3px 8px;
    border-radius: 12px;
    color: white;
}

/* Legend Styles */
.legend-container {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 50%;
}

/* Responsive */
@media (max-width: 768px) {
    .progress-row {
        gap: 15px;
    }
    
    .circular-progress {
        width: 140px;
        height: 140px;
    }
    
    .circular-progress .progress-value {
        font-size: 20px;
    }
    
    .legend-container {
        gap: 10px;
    }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>W1 Panel</h2>
    <a href="w1_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="approve_permission.php"><i class="fas fa-user-check"></i> Approve Permission 
        <?php if ($pending > 0) echo "<span class='notification-indicator'></span>"; ?>
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
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="main">
    <div class="container">
        <h2 style="text-align:center; color:#333;">SQA Officers Task Progress</h2>
        
        <!-- Legend -->
        <div class="legend-container">
            <div class="legend-item">
                <div class="legend-color" style="background-color: #28a745;"></div>
                <span>Best (71-100%)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ffc107;"></div>
                <span>Good (50-70%)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #dc3545;"></div>
                <span>Poor (0-49%)</span>
            </div>
        </div>

        <!-- Circular Progress Charts -->
        <div class="circular-progress-container">
            <?php if($sqa_result && $sqa_result->num_rows > 0): ?>
                <?php 
                // Reset result pointer
                $sqa_result->data_seek(0);
                $counter = 0;
                $maxPerRow = 4;
                ?>
                
                <?php while($row = $sqa_result->fetch_assoc()):
                    $approved = $row['approved_count'];
                    $total = $row['total_tasks'];
                    $percent = percentage($approved, $total);
                    $color = getProgressColor($percent);
                    $statusText = getProgressText($percent);
                    
                    // Start a new row every 4 items
                    if ($counter % $maxPerRow === 0) {
                        if ($counter > 0) {
                            echo '</div>'; // Close previous row
                        }
                        echo '<div class="progress-row">'; // Start new row
                    }
                ?>
                <div class="circular-progress">
                    <canvas id="progress-<?php echo $row['id']; ?>" width="180" height="180"></canvas>
                    <div class="progress-info">
                        <div class="progress-value"><?php echo $percent; ?>%</div>
                        <div class="progress-label"><?php echo htmlspecialchars($row['username']); ?></div>
                        <div class="progress-status" style="background-color: <?php echo $color; ?>;">
                            <?php echo $statusText; ?>
                        </div>
                    </div>
                </div>
                <?php 
                    $counter++;
                endwhile; 
                
                // Close the last row if it exists
                if ($counter > 0) {
                    echo '</div>';
                }
                ?>
            <?php else: ?>
                <p style="text-align:center; color:#555;">No SQA officers found or no tasks assigned yet.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
// Sidebar toggle
document.getElementById("menuToggle").addEventListener("click", function() {
    // This would toggle sidebar in a real implementation
});

// Profile dropdown
const profileIcon = document.getElementById("profileIcon");
const dropdownMenu = document.getElementById("dropdownMenu");
profileIcon.addEventListener("click", () => {
    dropdownMenu.style.display = dropdownMenu.style.display === "block" ? "none" : "block";
});
window.addEventListener("click", (e) => { if (!profileIcon.contains(e.target)) dropdownMenu.style.display = "none"; });

// Create circular progress charts
document.addEventListener('DOMContentLoaded', function() {
    <?php 
    // Reset result pointer
    if ($sqa_result) {
        $sqa_result->data_seek(0);
        while($row = $sqa_result->fetch_assoc()):
            $approved = $row['approved_count'];
            $total = $row['total_tasks'];
            $percent = percentage($approved, $total);
            $color = getProgressColor($percent);
    ?>
    
    // Create chart for each employee
    const ctx<?php echo $row['id']; ?> = document.getElementById('progress-<?php echo $row['id']; ?>').getContext('2d');
    new Chart(ctx<?php echo $row['id']; ?>, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?php echo $percent; ?>, 100 - <?php echo $percent; ?>],
                backgroundColor: [
                    '<?php echo $color; ?>',
                    '#e9ecef'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            },
            animation: {
                animateRotate: true,
                animateScale: true
            }
        }
    });
    
    <?php 
        endwhile;
    }
    ?>
});
</script>
</body>
</html>