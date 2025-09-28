<?php
session_start();
require 'db.php'; 

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

   
    $stmtLog = $conn->prepare("INSERT INTO user_logs (user_id, action, timestamp) VALUES (?, 'logout', NOW())");
    $stmtLog->bind_param("i", $user_id);
    $stmtLog->execute();
    $stmtLog->close();
}

$_SESSION = [];
session_unset();
session_destroy();

header("Location: login.php");
exit();
?>
