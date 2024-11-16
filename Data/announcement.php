<?php
// Database connection
include('../includes/db.php');

// Set response type to JSON
header('Content-Type: application/json');

// Check if the user is logged in
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;

// Fetch unread announcements count for a user
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if ($isLoggedIn) {
        // Query to fetch the count of unread announcements for the logged-in user
        $sql_unread = "
            SELECT COUNT(*) as unread_count 
            FROM user_announcements 
            WHERE user_id = ? AND is_read = 0
        ";
        $stmt_unread = $conn->prepare($sql_unread);
        $stmt_unread->bind_param("i", $user_id);
        $stmt_unread->execute();
        $result_unread = $stmt_unread->get_result();
        $unread_data = $result_unread->fetch_assoc();
        $unread_count = $unread_data['unread_count'] ?? 0;
        $stmt_unread->close();

        // Fetch unread announcements count and announcements
        $sql_announcements = "
            SELECT a.id, a.title, a.message, a.target_audience, a.created_at, a.picture, c.cover_photo, c.title AS place_title
            FROM announcements a
            LEFT JOIN contents c ON a.place_tag = c.id
            ORDER BY a.id DESC
        ";
        $result = $conn->query($sql_announcements);

        $announcements = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $announcements[] = $row;
            }
        }

        // Return both unread count and announcements
        echo json_encode([
            'unread_count' => $unread_count,
            'announcements' => $announcements
        ]);
    }
}

// Handle POST request - Use an action parameter to determine what to do
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Create new announcement
    if ($action == "create") {
        $title = $_POST['title'];
        $message = $_POST['message'];
        $target_audience = $_POST['target_audience'];
        $urgency = $_POST['urgency'];
        $send_now = isset($_POST['send_now']) && $_POST['send_now'] == '1' ? 1 : 0;
        $scheduled_time = $send_now ? null : $_POST['scheduled_time'];
        $notification_type = isset($_POST['notification_type']) ? implode(",", $_POST['notification_type']) : '';

        // Optional place tag
        $place_tag = isset($_POST['place_tag']) && !empty($_POST['place_tag']) ? $_POST['place_tag'] : NULL;

        // Handle picture upload
        $picture_name = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
            $target_dir = "../uploads/"; // Adjusted to go up one directory and then into "uploads"
            $imageFileType = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
            $picture_name = uniqid() . '.' . $imageFileType;
            $target_file = $target_dir . $picture_name;

            // Move uploaded file to the "uploads" folder
            if (!move_uploaded_file($_FILES['picture']['tmp_name'], $target_file)) {
                echo json_encode(["error" => "Failed to upload the picture."]);
                exit();
            }
        }

        // Insert the announcement into the database
        $sql = "
            INSERT INTO announcements (title, message, target_audience, urgency, send_now, scheduled_time, notification_type, place_tag, picture) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssissis", $title, $message, $target_audience, $urgency, $send_now, $scheduled_time, $notification_type, $place_tag, $picture_name);

        if ($stmt->execute()) {
            // Get the new announcement ID
            $announcement_id = $stmt->insert_id;

            // Add entry to user_announcements for all users
            $users_sql = "SELECT id FROM users";
            $users_result = $conn->query($users_sql);

            if ($users_result->num_rows > 0) {
                while ($user = $users_result->fetch_assoc()) {
                    $sql_user_ann = "INSERT INTO user_announcements (user_id, announcement_id, is_read) VALUES (?, ?, 0)";
                    $stmt_user_ann = $conn->prepare($sql_user_ann);
                    $stmt_user_ann->bind_param("ii", $user['id'], $announcement_id);
                    $stmt_user_ann->execute();
                }
            }

            echo json_encode(["message" => "Announcement created successfully."]);
        } else {
            error_log("MySQL Error: " . $stmt->error);  // Log to server error log
            echo json_encode(["error" => "Error: " . $stmt->error]);
        }

        $stmt->close();
    } elseif ($action == "edit") {
        // Edit existing announcement
        $id = $_POST['id'];
        $title = $_POST['title'];
        $message = $_POST['message'];
        $target_audience = $_POST['target_audience'];
        $urgency = $_POST['urgency'];

        // Optional place tag update
        $place_tag = isset($_POST['place_tag']) && !empty($_POST['place_tag']) ? $_POST['place_tag'] : NULL;

        // Update the announcement in the database
        $sql = "
            UPDATE announcements 
            SET title=?, message=?, target_audience=?, urgency=?, place_tag=? 
            WHERE id=?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $title, $message, $target_audience, $urgency, $place_tag, $id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Announcement updated successfully."]);
        } else {
            echo json_encode(["error" => "Error: " . $stmt->error]);
        }

        $stmt->close();
    } elseif ($action == "delete") {
        // Delete announcement
        $id = $_POST['id'];

        $sql = "DELETE FROM announcements WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Announcement deleted successfully."]);
        } else {
            echo json_encode(["error" => "Error: " . $stmt->error]);
        }

        $stmt->close();
    }
}

$conn->close();
?>
