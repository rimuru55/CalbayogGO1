<?php
// Database connection
include('../includes/db.php');

// Set response type to JSON
header('Content-Type: application/json');

// Handle GET request - Fetch existing announcements
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Start the session to get the logged-in user ID
    session_start();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if ($user_id) {
        // Fetch announcements and unread count for the logged-in user
        $sql = "
            SELECT a.id, a.title, a.message, a.created_at, a.picture, c.cover_photo, c.title AS place_title, ua.is_read, a.place_tag 
            FROM announcements a
            LEFT JOIN contents c ON a.place_tag = c.id  -- Join with contents to fetch cover photo and title if place is tagged
            LEFT JOIN user_announcements ua ON a.id = ua.announcement_id AND ua.user_id = ?
            ORDER BY a.id DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $announcements = [];
        $unread_count = 0;
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if ($row['is_read'] == 0) {
                    $unread_count++;
                }
                $announcements[] = $row;
            }
        }

        // Return the unread count and announcements as a JSON response
        echo json_encode([
            'unread_count' => $unread_count,
            'announcements' => $announcements
        ]);
    }
}

$conn->close();

?>
