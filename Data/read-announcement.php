<?php
// Database connection
include('../includes/db.php');

// Set response type to JSON
header('Content-Type: application/json');

// Check if the user is logged in
session_start();
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && $user_id) {
    // Update all unread announcements to read for the logged-in user
    $sql = "
        UPDATE user_announcements
        SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
}

$conn->close();
?>
