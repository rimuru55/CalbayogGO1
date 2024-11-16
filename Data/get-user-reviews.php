<?php
include '../includes/db.php';

if (isset($_GET['user_id'])) {
    $userId = $_GET['user_id'];

    // Fetch user reviews and ratings
    $sql = "SELECT r.rating, r.review, c.title AS content_title
            FROM ratings r
            JOIN contents c ON r.content_id = c.id
            WHERE r.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }

    $stmt->close();

    echo json_encode(['reviews' => $reviews]);
} else {
    echo json_encode(['reviews' => []]);
}
?>
