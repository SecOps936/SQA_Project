<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["count" => 0]);
    exit();
}

require 'db.php';

$result = $conn->query("SELECT COUNT(*) AS c FROM messages WHERE is_read = 0");
$row = $result->fetch_assoc();
echo json_encode(["count" => $row['c']]);
