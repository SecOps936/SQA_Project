<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $new_password = $_POST['password'];

    // ✅ Enforce strong password rules
    if (!preg_match("/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $new_password)) {
        echo "<script>alert('❌ Password must be at least 8 chars, include upper, lower, number, and special char.');</script>";
        exit();
    }

    // ✅ Check if user exists
    $stmt = $conn->prepare("SELECT password FROM users WHERE username=? AND email=?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // ✅ Prevent reusing the old password
        if (password_verify($new_password, $row['password'])) {
            echo "<script>alert('❌ New password cannot be the same as old password.');</script>";
            exit();
        }

        // ✅ Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // ✅ Update securely
        $update = $conn->prepare("UPDATE users SET password=? WHERE username=? AND email=?");
        $update->bind_param("sss", $hashed_password, $username, $email);

        if ($update->execute()) {
            echo "<script>alert('✅ Password reset successful! Please login.'); window.location='login.php';</script>";
        } else {
            echo "<script>alert('❌ Error updating password.');</script>";
        }
    } else {
        echo "<script>alert('❌ Records not found.');</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
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
    padding: 0 10px;
  }
  .header-container img {
    width: 80px; height: auto;
    margin-bottom: 10px;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  }
  .header-title {
    font-size: 18px; font-weight: bold;
    color: #222; margin-bottom: 6px;
  }
  .sub-header { font-size: 16px; color: #444; margin-bottom: 5px; }

  .portal-title {
    font-size: 20px; font-weight: bold;
    margin-bottom: 25px; color: #0D77A6;
    text-align: center;
    letter-spacing: 1px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
  }

  * { box-sizing: border-box; }

  .login-helper-container {
    width: 700px; max-width: 95%;
    border-top: 2px solid #056B34;
    border-bottom: 2px solid #0F0204;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.25);
  }

  .login-box {
    display: flex;
    background: #fff;
    min-height: 350px;
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

  h2 { margin-bottom: 15px; color: #0D77A6; }

  input {
    width: 100%;
    padding: 12px;
    margin: 8px 0;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 15px;
  }

  .btn {
    width: 50%; max-width: 220px;
    padding: 12px;
    background: #007BFF;
    border: none;
    color: white;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
    margin: 12px auto 0;
    display: block;
  }
  .btn:hover { background: #0056b3; }

  a {
    display: block;
    text-align: center;
    margin-top: 15px;
    text-decoration: none;
    color: #007BFF;
    font-size: 14px;
  }
  a:hover { text-decoration: underline; }

  .helper-box {
    border-top: 1px solid #ccc;
    width: 100%;
    background: #fff;
    padding: 20px 25px;
    text-align: center;
    color: #333;
  }
  .helper-box h3 { margin: 0 0 10px; color: #0D77A6; }
  .helper-box p { margin: 5px 0; font-size: 14px; }
  .helper-box a { color: #007BFF; text-decoration: none; }
  .helper-box a:hover { text-decoration: underline; }

  /* ✅ Responsiveness */
  @media (max-width: 900px) {
    .login-box { flex-direction: column; }
    .login-left { display: none; }
    .login-right { padding: 20px; }
    .btn { width: 100%; }
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
  <!-- Header -->
  <div class="header-container">
    <img src="images/Wizara ya elimu.jpg" alt="Nembo ya Taifa">
    <div class="header-title">United Republic of Tanzania</div>
    <div class="sub-header">Ministry of education, science and technology</div>
  </div>

  <!-- Portal Title -->
  <div class="portal-title">Kisarawe SQA Portal</div>

  <!-- Reset Password Container -->
  <div class="login-helper-container">
    <div class="login-box">
      <div class="login-left"></div>
      <div class="login-right">
        <h2 style="text-align:center;">Reset Password</h2>
        <form method="POST">
          <input type="text" name="username" placeholder="Enter Username" required>
          <input type="email" name="email" placeholder="Enter Registered Email" required>
          <input type="password" name="password" placeholder="Enter New Password" required>
          <button type="submit" class="btn">Reset</button>
        </form>
        <a href="login.php">Back to Login</a>
      </div>
    </div>

    <!-- Helper box -->
    <div class="helper-box">
      <h3>Need Help?</h3>
      <p>If you face any challenge on using the portal, contact us directly:</p>
      <p>Email: <a href="mailto:heriwambo27@gmail.com">support@sqa-portal.tz</a></p>
      <p>Phone: +255 624 523 106</p>
    </div>
  </div>
</body>
</html>
