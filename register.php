<?php
include 'db.php'; // make sure $conn is defined here

// Start session
session_start();

// Set security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src 'none'; object-src 'none'; form-action 'self'; base-uri 'self';");

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . getDashboardRedirect($_SESSION['role']));
    exit();
}

// Rate limiting - check registration attempts
$max_attempts = 3;
$lockout_time = 15 * 60; // 15 minutes

if (isset($_SESSION['register_attempts']) && $_SESSION['register_attempts'] >= $max_attempts) {
    if (time() - $_SESSION['last_register_attempt'] < $lockout_time) {
        $error = "Too many registration attempts. Please try again later.";
    } else {
        // Reset attempts if lockout time has passed
        unset($_SESSION['register_attempts']);
        unset($_SESSION['last_register_attempt']);
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
        logSecurityEvent($conn, null, 'csrf_fail', 'Registration attempt with invalid CSRF token');
    } else {
        // Honeypot field check (hidden field that should be empty)
        if (!empty($_POST['website'])) {
            // This is likely a bot
            $error = "Invalid submission.";
            logSecurityEvent($conn, null, 'honeypot_trigger', 'Registration attempt with filled honeypot field');
        } else {
            // Sanitize inputs
            $username   = trim($_POST['username'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $password   = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $secretCode = trim($_POST['secret_code'] ?? '');

            // Validate username - more flexible like login page
            if (empty($username) || strlen($username) < 3) {
                $error = "Username must be at least 3 characters.";
            } 
            // Validate email
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please follow a valid email format!";
            }
            // Validate password strength
            elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                $error = "Password must be at least 8 characters with uppercase, lowercase, number, and special character.";
            }
            // Confirm password
            elseif ($password !== $confirm_password) {
                $error = "Passwords do not match!";
            }
            // Validate secret code
            elseif (empty($secretCode)) {
                $error = "Secret code is required.";
            } else {
                // Check duplicates (username/email)
                $stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1");
                if (!$stmt) {
                    $error = "Database error. Please try again later.";
                    logSecurityEvent($conn, null, 'db_error', 'Duplicate check prepare failed: ' . $conn->error);
                } else {
                    $stmt->bind_param("ss", $username, $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $error = "Username or email already exists!";
                        logSecurityEvent($conn, null, 'duplicate_user', 'Registration attempt with existing username: ' . $username);
                    }
                    $stmt->close();

                    if (!isset($error)) {
                        // Lookup role from secret code
                        $stmt = $conn->prepare("SELECT `role` FROM role_codes WHERE `code` = ? AND is_active = 1 LIMIT 1");
                        if (!$stmt) {
                            $error = "Database error. Please try again later.";
                            logSecurityEvent($conn, null, 'db_error', 'Role lookup prepare failed: ' . $conn->error);
                        } else {
                            $stmt->bind_param("s", $secretCode);
                            $stmt->execute();
                            $roleResult = $stmt->get_result();
                            $role_name = null;
                            if ($roleResult && ($row = $roleResult->fetch_assoc())) {
                                $role_name = $row['role']; // e.g., 'admin', 'employee', 'W1', 'W2'
                            }
                            $stmt->close();

                            if (!$role_name) {
                                $error = "Invalid secret code!";
                                logSecurityEvent($conn, null, 'invalid_code', 'Registration attempt with invalid code: ' . $secretCode);
                            } else {
                                // Hash password
                                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                                // Insert user with 'pending' status
                                $stmt = $conn->prepare("INSERT INTO users (username, email, password, `role`, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                                if (!$stmt) {
                                    $error = "Database error. Please try again later.";
                                    logSecurityEvent($conn, null, 'db_error', 'Insert prepare failed: ' . $conn->error);
                                } else {
                                    $stmt->bind_param("ssss", $username, $email, $hashedPassword, $role_name);
                                    if (!$stmt->execute()) {
                                        $error = "Registration failed. Please try again.";
                                        logSecurityEvent($conn, null, 'db_error', 'Insert execute failed: ' . $stmt->error);
                                    } else {
                                        $user_id = $stmt->insert_id;
                                        $stmt->close();
                                        
                                        // Log successful registration
                                        logSecurityEvent($conn, $user_id, 'register_success', 'New user registered with role: ' . $role_name);
                                        
                                        // Reset registration attempts on successful registration
                                        unset($_SESSION['register_attempts']);
                                        unset($_SESSION['last_register_attempt']);
                                        
                                        // Regenerate CSRF token
                                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                        
                                        echo "<script>
                                        alert('Registration successful! Your account is pending approval.');
                                        window.location='login.php';
                                        </script>";
                                        exit;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Update registration attempts on failure
    if (isset($error)) {
        updateRegisterAttempts();
        logSecurityEvent($conn, null, 'register_fail', $error);
    }
}

// Helper functions
function getDashboardRedirect($role) {
    switch ($role) {
        case 'admin': return 'admin_dashboard.php';
        case 'W1': return 'w1_dashboard.php';
        case 'W2': return 'w2_dashboard.php';
        case 'employee': return 'employee_dashboard.php';
        default: return 'login.php';
    }
}

function logSecurityEvent($conn, $userId, $eventType, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, details, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("issss", $userId, $eventType, $details, $ip, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
}

function updateRegisterAttempts() {
    if (!isset($_SESSION['register_attempts'])) {
        $_SESSION['register_attempts'] = 1;
    } else {
        $_SESSION['register_attempts']++;
    }
    $_SESSION['last_register_attempt'] = time();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Kisarawe SQA Portal</title>
<style>
body {
    margin:0; padding:0; font-family:Arial,sans-serif; min-height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center;
    background-image:url('images/Tanzania.jpg'); background-size:cover; background-position:center; background-repeat:no-repeat; position:relative;
}
body::before { content:""; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.85); z-index:-1; }
body::after { content:""; position:absolute; top:50%; left:50%; width:120px; height:120px; background-image:url('images/emblem.png'); background-size:contain; background-repeat:no-repeat; background-position:center; opacity:0.2; transform:translate(-50%,-50%); z-index:-1; }
.header-container { text-align:center; margin-bottom:15px; }
.header-container img { width:80px; margin-bottom:10px; border-radius:50%; box-shadow:0 2px 6px rgba(0,0,0,0.2); }
.header-title { font-size:18px; font-weight:bold; color:#222; margin-bottom:6px; }
.sub-header { font-size:16px; color:#444; margin-bottom:5px; }
.portal-title { font-size:20px; font-weight:bold; margin-bottom:25px; color:#0D77A6; text-align:center; letter-spacing:1px; text-shadow:2px 2px 4px rgba(0,0,0,0.2); display:inline-block; padding-bottom:5px; }
* { box-sizing:border-box; }
.login-helper-container { width:700px; border-top:2px solid #056B34; border-bottom:2px solid #0F0204; border-radius:12px; overflow:hidden; box-shadow:0 8px 25px rgba(0,0,0,0.25); }
.login-box { display:flex; height:400px; background:#fff; }
.login-left { flex:1; background:url('images/national flag.png') no-repeat center center; background-size:cover; }
.login-right { flex:1; padding:30px 25px; display:flex; flex-direction:column; justify-content:center; }
h2 { text-align:center; margin-bottom:15px; color:#333; }
input { width:100%; padding:12px; margin:8px 0; border:1px solid #ccc; border-radius:6px; font-size:15px; }
.btn { width:30%; padding:12px; background:#007BFF; border:none; color:white; font-size:16px; border-radius:6px; cursor:pointer; margin:12px auto 0; display:block; }
.btn:hover { background:#0056b3; }
a { display:block; text-align:center; margin-top:15px; text-decoration:none; color:#007BFF; font-size:14px; }
a:hover { text-decoration:underline; }
.helper-box { border-top:1px solid #ccc; width:100%; background:#fff; padding:20px 25px; text-align:center; font-family:Arial,sans-serif; color:#333; }
.helper-box h3 { margin-top:0; margin-bottom:10px; color:#0D77A6; }
.helper-box p { margin:5px 0; font-size:14px; }
.helper-box a { color:#007BFF; text-decoration:none; }
.helper-box a:hover { text-decoration:underline; }
.error {
    background: #ffe6e6;
    color: #b30000;
    padding: 10px;
    border: 1px solid #ff4d4d;
    border-radius: 6px;
    text-align: center;
    margin: 10px 0;
    font-weight: bold;
}
.warning {
    background: #fff3cd;
    color: #856404;
    padding: 10px;
    border: 1px solid #ffeeba;
    border-radius: 6px;
    text-align: center;
    margin: 10px 0;
    font-weight: bold;
}
.honeypot {
    display: none;
}
.password-requirements {
    font-size: 12px;
    color: #666;
    margin-top: -5px;
    margin-bottom: 8px;
}
</style>
</head>
<body>
<div class="header-container">
    <img src="images/Wizara ya elimu.jpg" alt="Nembo ya Taifa">
    <div class="header-title">United Republic of Tanzania</div>
    <div class="sub-header">Ministry of education, science and technology</div>
</div>
<div class="portal-title">Kisarawe SQA Portal - Registration</div>
<div class="login-helper-container">
    <div class="login-box">
        <div class="login-left"></div>
        <div class="login-right">
            <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
            <?php if (isset($_SESSION['register_attempts']) && $_SESSION['register_attempts'] >= 2) { ?>
                <div class="warning"><?php echo (3 - $_SESSION['register_attempts']); ?> attempt(s) remaining before temporary lockout.</div>
            <?php } ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="text" name="username" placeholder="Enter Username" required>
                <input type="email" name="email" placeholder="Enter Email" required>
                <input type="password" name="password" id="password" placeholder="Enter Password" required>
                <div class="password-requirements">
                </div>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <input type="text" name="secret_code" placeholder="Enter your Code" required>
                <!-- Honeypot field for bots -->
                <input type="text" name="website" class="honeypot">
                <button type="submit" class="btn">Register</button>
            </form>
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>
    <div class="helper-box">
        <h3>Need Help?</h3>
        <p>If you face any challenge on using the portal, contact us directly:</p>
        <p>Email: <a href="mailto:heriwambo27@gmail.com">support@sqa-portal.tz</a></p>
        <p>Phone: +255 624 523 106</p>
    </div>
</div>
</body>
</html>