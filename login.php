<?php
include 'db.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = getDashboardRedirect($_SESSION['role']);
    header("Location: $redirect");
    exit();
}

// Brute force protection - check login attempts
$max_attempts = 5;
$lockout_time = 5 * 60; // 5 minutes

if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $max_attempts) {
    if (time() - $_SESSION['last_attempt_time'] < $lockout_time) {
        $error = "Too many failed attempts. Please try again later.";
    } else {
        // Reset attempts if lockout time has passed
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_attempt_time']);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($error)) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        // Input validation
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        // More flexible username validation - allow letters, numbers, spaces, and common symbols
        if (empty($username) || strlen($username) < 3) {
            $error = "Username must be at least 3 characters.";
        } else {
            // Use prepared statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT id, username, password, role, status, created_at FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                // Verify hashed password
                if (password_verify($password, $user['password'])) {
                    // Log successful login with shorter action string
                    logUserAction($conn, $user['id'], 'login');
                    
                    // Reset login attempts on successful login
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['last_attempt_time']);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['created_at'] = time();
                    
                    // Check if user is approved (except for admin)
                    if ($user['role'] !== 'admin' && $user['status'] !== 'approved') {
                        $error = "Your account is not approved yet. Please wait for admin approval.";
                    } else {
                        // Redirect based on role
                        $redirect = getDashboardRedirect($user['role']);
                        
                        // Verify dashboard file exists before redirecting
                        if (file_exists($redirect)) {
                            header("Location: $redirect");
                            exit();
                        } else {
                            // Dashboard file doesn't exist
                            $error = "Dashboard file not found. Please contact administrator.";
                        }
                    }
                } else {
                    // Log failed login attempt with shorter action string
                    logUserAction($conn, $user['id'], 'fail_pass');
                    
                    // Update login attempts
                    updateLoginAttempts();
                    $error = "Invalid username or password.";
                }
            } else {
                // Log failed login attempt for non-existent user with shorter action string
                logUserAction($conn, null, 'fail_user');
                
                // Update login attempts
                updateLoginAttempts();
                $error = "Invalid username or password.";
            }
            $stmt->close();
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

function logUserAction($conn, $userId, $action) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    // Use shorter action strings to avoid truncation error
    $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $userId, $action, $ip, $userAgent);
    
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Log the error but don't break the login process
        error_log("Logging error: " . $e->getMessage());
    }
    
    $stmt->close();
}

function updateLoginAttempts() {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 1;
    } else {
        $_SESSION['login_attempts']++;
    }
    $_SESSION['last_attempt_time'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';">
  <title>Login - Kisarawe SQA Portal</title>
  <style>
  body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-image: url('images/Tanzania.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
  }
  body::before {
    content: "";
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(255,255,255,0.85);
    z-index: -1;
  }
  body::after {
    content: "";
    position: absolute;
    top: 50%; left: 50%;
    width: 120px; height: 120px;
    background-image: url('images/emblem.png');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    opacity: 0.2;
    transform: translate(-50%, -50%);
    z-index: -1;
  }

  .header-container {
    text-align: center;
    margin-bottom: 15px;
  }
  .header-container img {
    width: 80px;
    height: auto;
    margin-bottom: 10px;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  }
  .header-title { font-size: 18px; font-weight: bold; color: #222; }
  .sub-header { font-size: 16px; color: #444; }
  .portal-title {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 25px;
    color: #0D77A6;
    text-align: center;
    letter-spacing: 1px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
  }

  * { box-sizing: border-box; }
  .login-helper-container {
    width: 700px;
    max-width: 95%;
    border-top: 2px solid #056B34;
    border-bottom: 2px solid #0F0204;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.25);
    transition: all 0.3s ease;
  }
  .login-box {
    display: flex;
    height: 350px;
    background: #fff;
  }
  .login-left {
    flex: 1;
    background: url('images/national flag.png') no-repeat center center;
    background-size: cover;
  }
  .login-right {
    flex: 1;
    padding: 30px 25px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  input {
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 15px;
  }
  .btn {
    width: 50%;
    max-width: 200px;
    padding: 12px;
    background: #007BFF;
    border: none;
    color: white;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
    margin: 10px auto 0;
    display: block;
  }
  .btn:hover { background: #0056b3; }
  .links {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    font-size: 14px;
  }
  .links a { text-decoration: none; color: #0073e6; }
  .links a:hover { text-decoration: underline; }
  .helper-box {
    border-top: 1px solid #ccc;
    width: 100%;
    background: #fff;
    padding: 20px 25px;
    text-align: center;
    font-family: Arial, sans-serif;
    color: #333;
  }
  .helper-box h3 { margin-top: 0; margin-bottom: 10px; color: #0D77A6; }
  .helper-box p { margin: 5px 0; font-size: 14px; }
  .helper-box a { color: #007BFF; text-decoration: none; }
  .helper-box a:hover { text-decoration: underline; }
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
  .info {
    background: #d1ecf1;
    color: #0c5460;
    padding: 10px;
    border: 1px solid #bee5eb;
    border-radius: 6px;
    text-align: center;
    margin: 10px 0;
    font-weight: bold;
  }

  /* ✅ RESPONSIVENESS */
  @media (max-width: 900px) {
    .login-box {
      flex-direction: column;
      height: auto;
    }
    .login-left {
      display: none; /* hide flag side on smaller devices */
    }
    .login-right {
      padding: 20px;
    }
    .btn { width: 100%; }
    .links { flex-direction: column; gap: 10px; align-items: center; }
  }

  @media (max-width: 480px) {
    .header-container img { width: 60px; }
    .header-title { font-size: 16px; }
    .sub-header { font-size: 14px; }
    .portal-title { font-size: 18px; }
    input { font-size: 14px; padding: 10px; }
    .btn { font-size: 14px; padding: 10px; }
    .helper-box p { font-size: 13px; }
  }
  </style>
</head>
<body>
  <div class="header-container">
    <img src="images/Wizara ya elimu.jpg" alt="Nembo ya Taifa">
    <div class="header-title">United Republic of Tanzania</div>
    <div class="sub-header">Ministry of education, science and technology</div>
  </div>

  <div class="portal-title">Kisarawe SQA Portal</div>

  <div class="login-helper-container">
    <div class="login-box">
      <div class="login-left"></div>
      <div class="login-right">
        <?php 
        if (!empty($error)) echo "<div class='error'>$error</div>";
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3 && $_SESSION['login_attempts'] < 5) {
            $remaining = 5 - $_SESSION['login_attempts'];
            echo "<div class='warning'>$remaining attempt(s) remaining before account lockout.</div>";
        }
        
        // Debug information - remove in production
        if (isset($_SESSION['user_id'])) {
            echo "<div class='info'>Debug: You are logged in as " . htmlspecialchars($_SESSION['username']) . " with role " . htmlspecialchars($_SESSION['role']) . "</div>";
        }
        ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="text" name="username" placeholder="Enter Username" required>
          <input type="password" name="password" placeholder="Enter Password" required>
          <button type="submit" class="btn">Login</button>
        </form>
        <div class="links">
          <a href="reset_password.php">Reset Password</a>
          <a href="register.php">Not Registered? Register</a>
        </div>
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