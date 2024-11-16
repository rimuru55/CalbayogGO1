<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/CalbayogGO/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $list_id = $_POST['list_id'];
    $content_id = $_POST['content_id'];

    // Function to remove the place
    function removePlaceFromList($conn, $list_id, $content_id) {
        $sql = "DELETE FROM favorite_list_places WHERE favorite_list_id = ? AND content_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $list_id, $content_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Place removed from list'];
        } else {
            return ['success' => false, 'message' => 'Failed to remove place from list'];
        }
    }

    // Call the function and output the result
    echo json_encode(removePlaceFromList($conn, $list_id, $content_id));
}
