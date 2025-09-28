<?php
session_start();
require 'db.php';

// Check if user is logged in and has either W1 or W2 role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'W1' && $_SESSION['role'] !== 'W2')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get form data
$user_id = $_POST['user_id'];
$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];

// Validate inputs
if (empty($username) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Username and email are required']);
    exit();
}

// Check if email is valid
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Update the user
if (empty($password)) {
    // Update without changing password
    $sql = "UPDATE users SET username=?, email=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $username, $email, $user_id);
} else {
    // Update with new password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET username=?, email=?, password=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $username, $email, $hashed_password, $user_id);
}

if ($stmt->execute()) {
    // Update session variables
    $_SESSION['username'] = $username;
    
    echo json_encode([
        'success' => true, 
        'username' => $username, 
        'email' => $email
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Update failed: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>