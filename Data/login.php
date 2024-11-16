<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "localhost";
    $dbusername = "root";
    $dbpassword = "";
    $dbname = "calbayog_go";

    // Create connection
    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            // Record login event
            $session_id = session_id();
            $login_sql = "INSERT INTO login_history (user_id, session_id) VALUES (?, ?)";
            $login_stmt = $conn->prepare($login_sql);
            if ($login_stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $login_stmt->bind_param("is", $user['id'], $session_id);
            $login_stmt->execute();
            $login_stmt->close();

            // Close main statement and connection before redirect
            $stmt->close();
            $conn->close();

            // Redirect user based on role
            if ($user['role'] === 'admin') {
                header('Location: ../Admin/adminpage.php');
            } else {
                header('Location: ../index.php');
            }
            exit();
        } else {
            $_SESSION['error'] = "Invalid username or password";
        }
    } else {
        $_SESSION['error'] = "Invalid username or password";
    }

    // Close main statement and connection in case of failure
    $stmt->close();
    $conn->close();

    // Redirect to login page on error
    header('Location: ../user-login.php');
    exit();
}
