<?php
session_start(); // Start the session

include('../includes/db.php'); // Include your database connection

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Update last login field with current timestamp
    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
}

// Unset all session variables
$_SESSION = array();

// If the session was using cookies, destroy the session cookie as well
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to the login page or homepage
header("Location: ../index.php");
exit();
