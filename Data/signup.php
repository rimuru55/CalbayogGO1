<?php
header('Content-Type: application/json'); // Set response header to JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "calbayog_go";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
        exit();
    }

    // Ensure that required fields are present
    if (!isset($_POST['firstname'], $_POST['lastname'], $_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm-password'])) {
        echo json_encode(["status" => "error", "message" => "Required fields are missing."]);
        exit();
    }

    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    // Validate password
    if ($password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "Passwords do not match."]);
        exit();
    }

    // Check if email or username already exists
    $check_sql = "SELECT id FROM users WHERE email = ? OR username = ?";
    $stmt = $conn->prepare($check_sql);
    if ($stmt) {
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "Email or Username already exists."]);
            $stmt->close();
            exit();
        }

        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Database query error: " . $conn->error]);
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $sql = "INSERT INTO users (firstname, lastname, username, email, password) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssss", $firstname, $lastname, $username, $email, $hashed_password);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Account created successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Database query error: " . $conn->error]);
    }

    $conn->close();
}
