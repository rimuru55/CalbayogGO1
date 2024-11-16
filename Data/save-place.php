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

    // Function to add the place
    function addPlaceToList($conn, $list_id, $content_id) {
        $sql_check = "SELECT * FROM favorite_list_places WHERE favorite_list_id = ? AND content_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $list_id, $content_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            $stmt_check->close();

            $sql = "INSERT INTO favorite_list_places (favorite_list_id, content_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $list_id, $content_id);

            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Place added to list'];
            } else {
                return ['success' => false, 'message' => 'Failed to add place to list'];
            }
        } else {
            return ['success' => false, 'message' => 'Place already in list'];
        }
    }

    // Call the function and output the result
    echo json_encode(addPlaceToList($conn, $list_id, $content_id));
}
